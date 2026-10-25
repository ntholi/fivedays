#!/usr/bin/env bash
# Runs as root ON THE NEW HOST (the Docker stack in /opt/fivedays). bin/rehearse, bin/cutover and
# bin/cutover-rollback pipe it through `sudo bash -s -- <command> [args]`. It never reads stdin (the script
# itself arrives on stdin); secrets reach it as root-only files under $CUT, written by bin/ with copy_to_host.
#
# Data moves host to host: this host pulls from the old host with the cutover's ephemeral key ($CUT/id_ed25519,
# authorised on the old host for user ubuntu, used with `sudo rsync` and an SSH tunnel to the old MariaDB socket).
#
# Commands:
#   preflight                       key=value: provisioned, stack containers, volumes empty, free disk
#   pull-images <image> <tag>       docker pull <tag>, <tag>-web, <tag>-transfer
#   installxml <image>              sha256 of our two plugins' install.xml in that (transfer) image
#   old-connect <addr>...           first old-host address that answers with the ephemeral key → $CUT/old.addr
#   source-creds                    the old host's backupuser (my_print_defaults /root/.my.cnf) → $CUT/source.env
#   tunnel-test <image> <socket>    SSH tunnel to the old MariaDB socket + a SELECT through it, from <image>
#   pull-files cutover|rehearsal    rsync -aH --numeric-ids --delete of the old moodledata into the volume
#   rehearsal-db-up <version>       temporary official MariaDB container $RDB (database moodle) on the stack network
#   rehearsal-db-load               streams the old host's newest nightly dump into it
#   rehearsal-db-down               removes it
#   transfer cutover|rehearsal <image> [socket]  dbtransfer's migrate.php from the -transfer image into Postgres
#   db-tables                       number of tables in the new Postgres database
#   db-reset                        drops and recreates the empty "moodle" database (refused once Moodle ran on it)
#   maintenance-off                 admin/cli/maintenance.php --disable on the new stack
#   token-count                     FiveDays service tokens in Postgres, without fivedays_ws's own
#   ws-token                        prints the fivedays_ws token (the caller captures it; never logged)
#   admin-token-from-db             a site admin's token → $CUT/admin-token (rehearsals)
#   smoke <domain>                  internal-listener checks: login page, $CUT/admin-token, 3 user tokens, fivedays_ws
#   contract <domain> [file]        contract test ($DIR/contract) with CONTRACT_CLEANUP=1 (and CONTRACT_ONLY=<file>)
#   cleanup <course ids>            contract cleanup.php for courses the upload QA created
#   objectfs                        push every file to the bucket (host/cutover-objectfs.php), then list the bucket
#                                   and compare every distinct mdl_files.contenthash and its size
#   bucket-check                    the host .env's bucket: versioning, object count, keys that are not objectfs's
#   caddy-root                      Caddy's local CA root certificate (test stand-ins with a .localhost site)
#   logstore-since <unix time>      logstore_standard_log rows written since then
#   wipe-rehearsal <bucket>         rehearsal only: stack down, temporary MariaDB gone, fivedays_* volumes
#                                   recreated empty, every object in <bucket> deleted, host .env removed
#   key-remove                      deletes $CUT (ephemeral key, known_hosts, source creds) and stops leftovers
set -euo pipefail

DIR=/opt/fivedays
CUT=$DIR/cutover
KEY=$CUT/id_ed25519
RDB=fivedays-rehearsal-mariadb
HELPER=fivedays-cutover-net
XFER=fivedays-cutover-transfer
TUNNEL_PORT=3307
EMPTY_SHA1=da39a3ee5e6b4b0d3255bfef95601890afd80709

fail() { printf 'FAIL  %s\n' "$1" >&2; exit 1; }
say() { printf '%s\n' "$*"; }

envval() { [[ -f "$DIR/.env" ]] && sed -n "s/^$1=//p" "$DIR/.env" | tail -n1 | sed 's/^"\(.*\)"$/\1/' || true; }
verval() { sed -n "s/^$1=//p" "$DIR/versions.env" | tail -n1; }
PROJECT="$(envval COMPOSE_PROJECT_NAME)"
PROJECT="${PROJECT:-fivedays}"
NET="${PROJECT}_default"
VOLUMES=("${PROJECT}_moodledata" "${PROJECT}_pgdata" "${PROJECT}_caddydata" "${PROJECT}_backups")

dc() { docker compose --project-directory "$DIR" --env-file "$DIR/versions.env" --env-file "$DIR/.env" -f "$DIR/compose.yaml" "$@"; }
psqlq() { dc exec -T db psql -U moodle -d moodle -XqtA -v ON_ERROR_STOP=1 -c "$1" </dev/null; }
volpath() { docker volume inspect -f '{{.Mountpoint}}' "$1"; }

# ssh_old <command>: runs a command on the old host as ubuntu with the ephemeral key (old.addr from old-connect).
ssh_opts() {
  local addr="$1"
  printf '%s\n' -i "$KEY" -o BatchMode=yes -o ConnectTimeout=10 -o ServerAliveInterval=30 -o ServerAliveCountMax=6 \
    -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$CUT/known_hosts" -o LogLevel=ERROR -p 22 "ubuntu@$addr"
}
old_addr() { [[ -s "$CUT/old.addr" ]] || fail "no $CUT/old.addr (run old-connect first)"; cat "$CUT/old.addr"; }
ssh_old() {
  local opts
  mapfile -t opts < <(ssh_opts "$(old_addr)")
  ssh "${opts[@]}" "$@"
}

# Leftovers of an interrupted run (bin/cutover --resume): tunnel, helper and transfer containers, rsync.
stop_leftovers() {
  pkill -f "fivedays-cutover-tunnel" 2>/dev/null || true
  pkill -f "rsync .*fivedays-cutover-rsync" 2>/dev/null || true
  docker rm -f "$XFER" "$HELPER" >/dev/null 2>&1 || true
}

# open_tunnel <image> <socket>: a container on the stack network whose network namespace holds an SSH tunnel
# 127.0.0.1:$TUNNEL_PORT → the old host's MariaDB socket, so a container joined to it reaches both the old
# MariaDB and the new Postgres (as "db") without opening anything in the host firewall.
open_tunnel() {
  local image="$1" socket="$2" net="${3:-$NET}" pid opts
  docker rm -f "$HELPER" >/dev/null 2>&1 || true
  pkill -f "fivedays-cutover-tunnel" 2>/dev/null || true
  docker run -d --name "$HELPER" --network "$net" --entrypoint sleep "$image" infinity >/dev/null
  pid="$(docker inspect -f '{{.State.Pid}}' "$HELPER")"
  mapfile -t opts < <(ssh_opts "$(old_addr)")
  # -o SetEnv names the process in `ps` so leftovers can be found; -L forwards to the Unix socket, so MariaDB
  # sees a local socket client and matches 'backupuser'@'localhost' exactly as the pre-check does.
  nsenter -t "$pid" -n ssh -f -N -o ExitOnForwardFailure=yes -o SetEnv=FIVEDAYS=fivedays-cutover-tunnel \
    -L "127.0.0.1:$TUNNEL_PORT:$socket" "${opts[@]}" || fail "the SSH tunnel to the old MariaDB socket $socket did not open"
  for _ in $(seq 1 20); do
    nsenter -t "$pid" -n bash -c "echo >/dev/tcp/127.0.0.1/$TUNNEL_PORT" 2>/dev/null && return 0
    sleep 1
  done
  fail "the SSH tunnel to the old MariaDB did not start listening"
}
close_tunnel() {
  pkill -f "fivedays-cutover-tunnel" 2>/dev/null || true
  docker rm -f "$HELPER" >/dev/null 2>&1 || true
}

# ws <token> <function> [curl args...]: one REST call through the internal listener, like registry-web makes it.
ws() {
  local token="$1" fn="$2"
  shift 2
  curl -s -m 60 -H "Host: $DOMAIN" --data-urlencode "wstoken=$token" -d "wsfunction=$fn" -d moodlewsrestformat=json "$@" \
    http://127.0.0.1:8080/webservice/rest/server.php
}
site_userid() { ws "$1" core_webservice_get_site_info | grep -o '"userid":[0-9]*' | head -n1 | cut -d: -f2; }

cmd="${1:-}"
shift || true
case "$cmd" in
  preflight)
    [[ -f "$DIR/.provisioned" ]] || fail "$DIR/.provisioned missing: run just provision"
    say "running_containers=$(docker ps -q --filter "label=com.docker.compose.project=$PROJECT" | wc -l)"
    for v in "${VOLUMES[@]}"; do
      if ! docker volume inspect "$v" >/dev/null 2>&1; then say "volume_${v#"${PROJECT}_"}=missing"; continue; fi
      n="$(find "$(volpath "$v")" -mindepth 1 -maxdepth 1 | wc -l)"
      say "volume_${v#"${PROJECT}_"}=$([[ "$n" == 0 ]] && echo empty || echo "$n entries")"
    done
    say "free_mb=$(df -Pm /var/lib/docker | awk 'NR == 2 { print $4 }')"
    say "rehearsal_db=$(docker ps -a -q --filter "name=^$RDB\$" | wc -l)"
    ;;
  pull-images)
    image="$1" tag="$2"
    for s in "" -web -transfer; do docker pull -q "$image:$tag$s" >/dev/null || fail "docker pull $image:$tag$s failed"; done
    say "pulled=$image:$tag,-web,-transfer"
    ;;
  installxml)
    docker run --rm --entrypoint sh "$1" -c 'cd /var/www/moodle/public && sha256sum local/fivedays/db/install.xml grade/grading/form/fivedays/db/install.xml' |
      awk '{ printf "installxml %s %s\n", $2, $1 }'
    ;;
  old-connect)
    [[ -s "$KEY" && -s "$CUT/known_hosts" ]] || fail "ephemeral key or known_hosts missing in $CUT"
    for addr in "$@"; do
      [[ -n "$addr" ]] || continue
      mapfile -t opts < <(ssh_opts "$addr")
      if ssh "${opts[@]}" true 2>/dev/null; then
        printf '%s\n' "$addr" >"$CUT/old.addr"
        chmod 600 "$CUT/old.addr"
        say "old_addr=$addr"
        exit 0
      fi
    done
    fail "the old host answered on none of: $* (ephemeral key, port 22)"
    ;;
  source-creds)
    # my_print_defaults prints the [client]/[mysql] options of /root/.my.cnf normalised (--user=…, --password=…;
    # MariaDB's prints passwords as they are, MySQL 8's would mask them). Read on the old host as root, written
    # here root-only, never printed.
    creds="$(ssh_old "sudo my_print_defaults --defaults-file=/root/.my.cnf client mysql")"
    user="$(sed -n 's/^--user=//p' <<<"$creds" | tail -n1)"
    pass="$(sed -n 's/^--password=//p' <<<"$creds" | tail -n1)"
    [[ -n "$user" && -n "$pass" ]] || fail "/root/.my.cnf on the old host has no [client] user and password"
    [[ ! "$pass" =~ ^\*+$ ]] || fail "my_print_defaults masked the password (MySQL, not MariaDB?)"
    umask 077
    printf 'MOODLE_DB_USER=%s\nMOODLE_DB_PASSWORD=%s\n' "$user" "$pass" >"$CUT/source.env"
    say "source_user=$user"
    ;;
  tunnel-test)
    image="$1" socket="$2"
    [[ -s "$CUT/source.env" ]] || fail "no $CUT/source.env (run source-creds)"
    # Before phase 3 the stack's network doesn't exist yet: a throwaway one.
    docker network create fivedays-cutover-test >/dev/null 2>&1 || true
    open_tunnel "$image" "$socket" fivedays-cutover-test
    set +e
    out="$(docker run --rm --network "container:$HELPER" --env-file "$CUT/source.env" --entrypoint php "$image" -r '
      $db = @new mysqli("127.0.0.1", getenv("MOODLE_DB_USER"), getenv("MOODLE_DB_PASSWORD"), "moodle", '"$TUNNEL_PORT"');
      if ($db->connect_errno) { fwrite(STDERR, "connect: " . $db->connect_error . "\n"); exit(1); }
      $r = $db->query("SELECT COUNT(*) FROM mdl_config");
      if (!$r) { fwrite(STDERR, "query: " . $db->error . "\n"); exit(1); }
      echo "mdl_config_rows=" . $r->fetch_row()[0] . "\n";' 2>&1)"
    xrc=$?
    set -e
    close_tunnel
    docker network rm fivedays-cutover-test >/dev/null 2>&1 || true
    say "$out"
    exit "$xrc"
    ;;
  pull-files)
    mode="$1"
    vol="$(volpath "${PROJECT}_moodledata")"
    addr="$(old_addr)"
    mapfile -t opts < <(ssh_opts "$addr")
    excludes=(--exclude=/climaintenance.html --exclude=/cache/ --exclude=/localcache/ --exclude=/temp/ --exclude=/sessions/ --exclude=/lock/)
    # The rehearsal pairs last night's dump with today's files: a file deleted since the dump sits in trashdir/
    # (same <2>/<2>/<sha1> layout, lib/filestorage/file_system_filedir.php), so it is copied back into filedir/.
    [[ "$mode" == rehearsal ]] || excludes+=(--exclude=/trashdir/)
    ssh_cmd="ssh $(printf '%q ' "${opts[@]:0:$((${#opts[@]} - 1))}") -o SetEnv=FIVEDAYS=fivedays-cutover-rsync"
    set +e
    rsync -aH --numeric-ids --delete --stats "${excludes[@]}" --rsync-path="sudo rsync" -e "$ssh_cmd" \
      "ubuntu@$addr:/var/www/data/moodledata/" "$vol/" | grep -E '^(Number of (regular )?files|Total file size|Total transferred)'
    rc="${PIPESTATUS[0]}"
    set -e
    [[ "$rc" == 0 ]] || fail "rsync exited $rc"
    if [[ "$mode" == rehearsal && -d "$vol/trashdir" ]]; then
      rsync -a --ignore-existing "$vol/trashdir/" "$vol/filedir/"
    fi
    old_files="$(ssh_old "sudo find /var/www/data/moodledata/filedir -type f ! -name warning.txt | wc -l")"
    new_files="$(find "$vol/filedir" -type f ! -name warning.txt | wc -l)"
    say "rsync=0 old_filedir_files=$old_files new_filedir_files=$new_files"
    ;;
  rehearsal-db-up)
    version="$1"
    docker network inspect "$NET" >/dev/null 2>&1 || fail "network $NET missing (start the db service first)"
    docker rm -f -v "$RDB" >/dev/null 2>&1 || true
    umask 077
    pw="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    printf 'MARIADB_ROOT_PASSWORD=%s\n' "$pw" >"$CUT/rehearsal-db.env"
    docker pull -q "mariadb:$version" >/dev/null
    # No volume of its own: the datadir is an anonymous volume removed with the container (-v).
    docker run -d --name "$RDB" --network "$NET" --env-file "$CUT/rehearsal-db.env" -e MARIADB_DATABASE=moodle \
      "mariadb:$version" --innodb-buffer-pool-size=256M --innodb-flush-log-at-trx-commit=2 --max-allowed-packet=256M >/dev/null
    for _ in $(seq 1 90); do
      docker exec "$RDB" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -e "SELECT 1" moodle' >/dev/null 2>&1 && break
      sleep 2
    done
    docker exec "$RDB" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -e "SELECT 1" moodle' >/dev/null || fail "temporary MariaDB did not start"
    say "rehearsal_db=$RDB"
    ;;
  rehearsal-db-load)
    dump="$(ssh_old "sudo find /var/backups/moodle -maxdepth 1 -name 'moodle_backup_*.sql' -type f -printf '%T@ %p\n' | sort -n | tail -n1 | cut -d' ' -f2-")"
    [[ -n "$dump" ]] || fail "no nightly dump in /var/backups/moodle on the old host"
    say "dump=$dump"
    trailer="$(mktemp)"
    # Compressed on the wire (gzip -1 on the old host), never written to this host's disk.
    set +e
    ssh_old "sudo gzip -1 -c $(printf '%q' "$dump")" | gunzip | tee >(tail -c 300 >"$trailer") |
      docker exec -i "$RDB" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot moodle'
    rc=("${PIPESTATUS[@]}")
    set -e
    sleep 2
    [[ "${rc[*]}" == "0 0 0 0" ]] || fail "loading the dump failed (exit codes ssh/gunzip/tee/mariadb: ${rc[*]})"
    grep -q -- '-- Dump completed' "$trailer" || fail "the dump has no \"-- Dump completed\" trailer: it is cut short"
    rm -f "$trailer"
    say "tables=$(docker exec "$RDB" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \"moodle\""')"
    ;;
  rehearsal-db-down)
    docker rm -f -v "$RDB" >/dev/null 2>&1 || true
    rm -f "$CUT/rehearsal-db.env"
    say "rehearsal_db=removed"
    ;;
  transfer)
    mode="$1" image="$2" socket="${3:-}"
    stop_leftovers
    pw="$(envval POSTGRES_PASSWORD)"
    wwwroot="$(envval MOODLE_WWWROOT)"
    umask 077
    {
      printf 'MOODLE_DB_TYPE=mariadb\nMOODLE_DB_NAME=moodle\nMOODLE_WWWROOT=%s\nMOODLE_REDIS_PASSWORD=unused\nTARGET_DB_PASSWORD=%s\n' "$wwwroot" "$pw"
      if [[ "$mode" == cutover ]]; then
        printf 'MOODLE_DB_HOST=127.0.0.1\nMOODLE_DB_PORT=%s\n' "$TUNNEL_PORT"
        cat "$CUT/source.env"
      else
        printf 'MOODLE_DB_HOST=%s\nMOODLE_DB_PORT=3306\nMOODLE_DB_USER=root\nMOODLE_DB_PASSWORD=%s\n' "$RDB" \
          "$(sed -n 's/^MARIADB_ROOT_PASSWORD=//p' "$CUT/rehearsal-db.env")"
      fi
    } >"$CUT/transfer.env"
    if [[ "$mode" == cutover ]]; then
      open_tunnel "$image" "$socket"
      network=(--network "container:$HELPER")
    else
      network=(--network "$NET")
    fi
    # dbtransfer (public/admin/tool/dbtransfer/cli/migrate.php): the source is the site's own $DB (config.php
    # with MOODLE_DB_TYPE=mariadb), only read (lib/dtl/database_exporter.php:152 export_table_recordset); the
    # target given on the command line must be empty (migrate.php:177-179). --dbport always given, or it prompts
    # (migrate.php:156-159); --dbsocket=0 means TCP. A tmpfs dataroot keeps caches off the real moodledata.
    set +e
    # shellcheck disable=SC2016
    docker run --rm --name "$XFER" "${network[@]}" --tmpfs /var/moodledata:mode=1777 --env-file "$CUT/transfer.env" \
      --entrypoint sh "$image" -c 'exec php public/admin/tool/dbtransfer/cli/migrate.php --dbtype=pgsql --dbhost=db \
        --dbname=moodle --dbuser=moodle --dbpass="$TARGET_DB_PASSWORD" --dbport=5432 --prefix=mdl_ --dbsocket=0'
    xrc=$?
    set -e
    rm -f "$CUT/transfer.env"
    [[ "$mode" != cutover ]] || close_tunnel
    [[ "$xrc" == 0 ]] || fail "migrate.php exited $xrc"
    say "transfer=0 tables=$(psqlq "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")"
    ;;
  db-tables)
    psqlq "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'"
    ;;
  db-reset)
    # Only for a database that Moodle never ran on (an interrupted transfer): the old host is still the source.
    if [[ "$(psqlq "SELECT count(*) FROM mdl_config_plugins WHERE plugin = 'tool_objectfs'" 2>/dev/null || echo 0)" != 0 ]]; then
      fail "the new database already ran Moodle's upgrade (tool_objectfs installed); refusing to reset it"
    fi
    stop_leftovers
    dc exec -T db psql -U moodle -d postgres -Xq -v ON_ERROR_STOP=1 \
      -c 'DROP DATABASE IF EXISTS moodle WITH (FORCE)' -c 'CREATE DATABASE moodle OWNER moodle' </dev/null
    say "db_reset=ok tables=$(psqlq "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")"
    ;;
  maintenance-off)
    # admin/cli/maintenance.php:99-110 (the old host's freeze set maintenance_enabled, which moved with the data).
    dc exec -T app php admin/cli/maintenance.php --disable </dev/null
    ;;
  token-count)
    # Without fivedays_ws's own token, which setup.php adds: this must equal the old host's count.
    psqlq "SELECT COUNT(*) FROM mdl_external_tokens t JOIN mdl_external_services s ON s.id = t.externalserviceid JOIN mdl_user u ON u.id = t.userid WHERE s.shortname = 'fivedays' AND u.username <> 'fivedays_ws'"
    ;;
  ws-token)
    psqlq "SELECT t.token FROM mdl_external_tokens t JOIN mdl_user u ON u.id = t.userid JOIN mdl_external_services s ON s.id = t.externalserviceid WHERE u.username = 'fivedays_ws' AND u.deleted = 0 AND s.shortname = 'fivedays' AND t.tokentype = 0 ORDER BY t.id LIMIT 1"
    ;;
  admin-token-from-db)
    admins="$(psqlq "SELECT value FROM mdl_config WHERE name = 'siteadmins'")"
    [[ "$admins" =~ ^[0-9,]+$ ]] || fail "no siteadmins in mdl_config"
    token="$(psqlq "SELECT t.token FROM mdl_external_tokens t JOIN mdl_external_services s ON s.id = t.externalserviceid WHERE t.userid IN ($admins) AND t.tokentype = 0 ORDER BY (s.shortname = 'fivedays') DESC, t.lastaccess DESC NULLS LAST LIMIT 1")"
    [[ -n "$token" ]] || fail "no site admin has a permanent web-service token"
    umask 077
    printf '%s\n' "$token" >"$CUT/admin-token"
    say "admin_token=saved"
    ;;
  smoke)
    DOMAIN="$1"
    failed=0
    check() { if [[ "$1" == ok ]]; then say "ok    $2"; else say "FAIL  $2"; failed=1; fi; }
    code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1:8080/login/index.php)"
    check "$([[ "$code" == 200 ]] && echo ok)" "login page $code (Host: $DOMAIN, internal listener)"
    [[ -s "$CUT/admin-token" ]] || fail "no $CUT/admin-token"
    uid="$(site_userid "$(cat "$CUT/admin-token")")"
    check "$([[ -n "$uid" ]] && echo ok)" "old admin token: core_webservice_get_site_info userid ${uid:-none}"
    samples="$(psqlq "
      (SELECT 'lecturer', t.userid, t.token FROM mdl_external_tokens t JOIN mdl_external_services s ON s.id = t.externalserviceid AND s.shortname = 'fivedays'
         JOIN mdl_user u ON u.id = t.userid AND u.deleted = 0 AND u.suspended = 0
        WHERE EXISTS (SELECT 1 FROM mdl_role_assignments ra JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'editingteacher' WHERE ra.userid = t.userid)
        ORDER BY t.lastaccess DESC NULLS LAST LIMIT 1)
      UNION ALL
      (SELECT 'student', t.userid, t.token FROM mdl_external_tokens t JOIN mdl_external_services s ON s.id = t.externalserviceid AND s.shortname = 'fivedays'
         JOIN mdl_user u ON u.id = t.userid AND u.deleted = 0 AND u.suspended = 0
        WHERE EXISTS (SELECT 1 FROM mdl_role_assignments ra JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'student' WHERE ra.userid = t.userid)
          AND NOT EXISTS (SELECT 1 FROM mdl_role_assignments ra JOIN mdl_role r ON r.id = ra.roleid AND r.shortname = 'editingteacher' WHERE ra.userid = t.userid)
        ORDER BY t.lastaccess DESC NULLS LAST LIMIT 2)")"
    n=0
    while IFS='|' read -r role userid token; do
      [[ -n "$token" ]] || continue
      n=$((n + 1))
      uid="$(site_userid "$token")"
      check "$([[ "$uid" == "$userid" ]] && echo ok)" "$role token (user $userid): core_webservice_get_site_info userid ${uid:-none}"
    done <<<"$samples"
    check "$([[ "$n" == 3 ]] && echo ok)" "sample per-user tokens found: $n of 3 (one lecturer, two students)"
    wstoken="$(psqlq "SELECT t.token FROM mdl_external_tokens t JOIN mdl_user u ON u.id = t.userid JOIN mdl_external_services s ON s.id = t.externalserviceid WHERE u.username = 'fivedays_ws' AND u.deleted = 0 AND s.shortname = 'fivedays' ORDER BY t.id LIMIT 1")"
    wsname="$(ws "$wstoken" core_webservice_get_site_info | grep -o '"username":"[^"]*"' | head -n1 | cut -d'"' -f4)"
    check "$([[ "$wsname" == fivedays_ws ]] && echo ok)" "fivedays_ws token: core_webservice_get_site_info username ${wsname:-none}"
    exit "$failed"
    ;;
  contract)
    DOMAIN="$1" only="${2:-}"
    [[ -f "$DIR/contract/run.php" ]] || fail "$DIR/contract/run.php missing (bin/ copies docker/contract there)"
    dc run --rm --no-deps -T -e CONTRACT_CLEANUP=1 -e "CONTRACT_ONLY=$only" -v "$DIR/contract:/contract:ro" app \
      php /contract/run.php http://web:8081 "$DOMAIN" </dev/null
    ;;
  cleanup)
    dc run --rm --no-deps -T -v "$DIR/contract:/contract:ro" app php /contract/cleanup.php "--courses=$1" </dev/null
    ;;
  objectfs)
    bucket="$(envval MOODLE_BUCKET)"
    [[ -n "$bucket" && "$bucket" != - ]] || fail "MOODLE_BUCKET is not set in $DIR/.env"
    dc run --rm --no-deps -T -v "$DIR/host:/fivedays-host:ro" app php /fivedays-host/cutover-objectfs.php </dev/null
    work="$(mktemp -d)"
    psqlq "SELECT contenthash, MAX(filesize) FROM mdl_files WHERE contenthash <> '$EMPTY_SHA1' GROUP BY contenthash" |
      awk -F'|' '{ printf "%s/%s/%s\t%s\n", substr($1, 1, 2), substr($1, 3, 2), $1, $2 }' | LC_ALL=C sort >"$work/expected"
    endpoint="$(envval MOODLE_BUCKET_ENDPOINT)"
    ep=()
    [[ -z "$endpoint" || "$endpoint" == - ]] || ep=(--endpoint-url "$endpoint")
    # One paginated listing (Key, Size) instead of a HEAD per object: the same answer for every key.
    AWS_ACCESS_KEY_ID="$(envval MOODLE_BUCKET_KEY)" AWS_SECRET_ACCESS_KEY="$(envval MOODLE_BUCKET_SECRET)" \
      docker run --rm --network "$NET" -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION=eu-central-1 \
      "amazon/aws-cli:$(verval AWSCLI_VERSION)" "${ep[@]}" s3api list-objects-v2 --bucket "$bucket" \
      --query 'Contents[].[Key, Size]' --output text | awk 'NF == 2 { printf "%s\t%s\n", $1, $2 }' | LC_ALL=C sort >"$work/bucket"
    missing="$(LC_ALL=C join -t $'\t' -v1 "$work/expected" "$work/bucket" | wc -l)"
    wrongsize="$(LC_ALL=C join -t $'\t' "$work/expected" "$work/bucket" | awk -F'\t' '$2 != $3' | wc -l)"
    say "bucket=$bucket hashes=$(wc -l <"$work/expected") objects=$(wc -l <"$work/bucket") missing=$missing wrong_size=$wrongsize"
    LC_ALL=C join -t $'\t' -v1 "$work/expected" "$work/bucket" | head -n 20 | sed 's/^/missing  /'
    rm -rf "$work"
    [[ "$missing" == 0 && "$wrongsize" == 0 ]] || fail "the bucket is missing $missing objects and $wrongsize have the wrong size"
    ;;
  bucket-check)
    # Preflight: the prod bucket answers with the host's keys, has versioning on (D19) and holds no objects yet.
    bucket="$(envval MOODLE_BUCKET)"
    endpoint="$(envval MOODLE_BUCKET_ENDPOINT)"
    ep=()
    [[ -z "$endpoint" || "$endpoint" == - ]] || ep=(--endpoint-url "$endpoint")
    docker network create fivedays-cutover-aws >/dev/null 2>&1 || true
    aws() {
      AWS_ACCESS_KEY_ID="$(envval MOODLE_BUCKET_KEY)" AWS_SECRET_ACCESS_KEY="$(envval MOODLE_BUCKET_SECRET)" docker run --rm \
        -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION=eu-central-1 --network fivedays-cutover-aws \
        "amazon/aws-cli:$(verval AWSCLI_VERSION)" "${ep[@]}" "$@"
    }
    versioning="$(aws s3api get-bucket-versioning --bucket "$bucket" --query Status --output text)" || fail "bucket $bucket does not answer with the host's keys"
    keys="$(aws s3api list-objects-v2 --bucket "$bucket" --query 'Contents[].Key' --output text | tr '\t' '\n' | grep -v '^None$' | grep . || true)"
    docker network rm fivedays-cutover-aws >/dev/null 2>&1 || true
    objects="$(grep -c . <<<"$keys" || true)"
    # objectfs keys are <2>/<2>/<sha1> of the content (classes/local/store/s3/client.php:298-302), so objects an
    # earlier, rolled-back attempt pushed hold exactly what this one would push; anything else is foreign.
    foreign="$(grep -cvE '^([0-9a-f]{2})/([0-9a-f]{2})/\1\2[0-9a-f]{36}$' <<<"$keys" || true)"
    [[ -n "$keys" ]] || foreign=0
    printf 'bucket=%s\nversioning=%s\nobjects=%s\nforeign=%s\n' "$bucket" "$versioning" "$objects" "$foreign"
    ;;
  caddy-root)
    docker run --rm -v "${PROJECT}_caddydata:/data:ro" --entrypoint cat "$(docker inspect -f '{{.Config.Image}}' "$(dc ps -q web)")" \
      /data/caddy/pki/authorities/local/root.crt
    ;;
  logstore-since)
    psqlq "SELECT COUNT(*) FROM mdl_logstore_standard_log WHERE timecreated >= $(( $1 + 0 ))"
    ;;
  wipe-rehearsal)
    bucket="$1"
    [[ "$(envval MOODLE_BUCKET)" == "$bucket" ]] || fail "$DIR/.env names bucket \"$(envval MOODLE_BUCKET)\", not the rehearsal bucket $bucket"
    endpoint="$(envval MOODLE_BUCKET_ENDPOINT)"
    ep=()
    [[ -z "$endpoint" || "$endpoint" == - ]] || ep=(--endpoint-url "$endpoint")
    key="$(envval MOODLE_BUCKET_KEY)" secret="$(envval MOODLE_BUCKET_SECRET)" awsv="$(verval AWSCLI_VERSION)"
    stop_leftovers
    dc down --remove-orphans >/dev/null 2>&1 || true
    docker rm -f -v "$RDB" >/dev/null 2>&1 || true
    for v in "${VOLUMES[@]}"; do
      docker volume rm "$v" >/dev/null 2>&1 || true
      docker volume create "$v" >/dev/null
    done
    # A user-defined network (Docker's DNS), so a stand-in S3 endpoint name resolves too.
    docker network create fivedays-cutover-aws >/dev/null 2>&1 || true
    aws() {
      AWS_ACCESS_KEY_ID="$key" AWS_SECRET_ACCESS_KEY="$secret" docker run --rm -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY \
        -e AWS_DEFAULT_REGION=eu-central-1 --network fivedays-cutover-aws "amazon/aws-cli:$awsv" "${ep[@]}" "$@"
    }
    aws s3 rm "s3://$bucket" --recursive --only-show-errors
    # shellcheck disable=SC2016
    left="$(aws s3api list-objects-v2 --bucket "$bucket" --query 'length(Contents || `[]`)' --output text)"
    docker network rm fivedays-cutover-aws >/dev/null 2>&1 || true
    rm -f "$DIR/.env" /etc/cron.d/fivedays
    for v in "${VOLUMES[@]}"; do
      [[ -z "$(find "$(volpath "$v")" -mindepth 1 -maxdepth 1)" ]] || fail "volume $v is not empty after the wipe"
    done
    say "volumes=empty bucket_objects=$left"
    [[ "$left" == 0 ]] || fail "$left objects are still in $bucket"
    ;;
  key-remove)
    stop_leftovers
    rm -rf "$CUT"
    say "remaining=$([[ -e "$CUT" ]] && echo 1 || echo 0)"
    ;;
  *) fail "unknown command \"$cmd\"" ;;
esac
