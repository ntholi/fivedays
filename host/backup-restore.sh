#!/usr/bin/env bash
# Database restore for one stack, used by bin/restore (never run by cron). On a host it runs as root in
# /opt/fivedays; for `just restore local` it runs on the laptop against the local project. It never asks:
# bin/restore asks for the typed confirmation before calling `restore`.
#
#   list                      the *.dump files in the backups volume (date, bytes, name)
#   receive <name>            stdin → /backups/<name> in the stack's backups volume
#   discard <name>            removes bin/restore's own upload (restore-upload-* only)
#   check <name>              /backups/<name> is a readable pg_dump -Fc file; prints "tables=<n>" for the
#                             current database (0 = empty)
#   restore <name> <mode> [--local] [--remove-after] [--tag=<tag>]
#        mode --empty-only    restore only if the database has no tables (nothing is dropped)
#        mode --replace       pre-restore dump of the current database to /backups/pre-restore-<time>.dump
#                             (checked), then drop and recreate the database, then restore
#        --local              local stack: clears any stored tool_objectfs S3 key, so a laptop can never
#                             reach a bucket; email and push stay off through MOODLE_NOEMAIL=1
#        --remove-after       delete /backups/<name> after a successful restore (bin/restore's upload copy)
#        --tag=<tag>          host only: before migrate starts, set IMAGE_TAG=<tag> in the env file and
#                             PREVIOUS_IMAGE_TAG to the tag that was live (rolling back past a version bump:
#                             a pre-upgrade dump must start with the old code, or migrate re-upgrades it)
#      Then: Redis restarted (drops sessions and cached data of the old database), migrate runs (upgrade.php
#      when the dump is older than the code, then setup.php), caches purged, app/cron/web started, login
#      page checked.
set -euo pipefail
source "$(dirname "$0")/backup-lib.sh"

cmd="${1:-}"; name="${2:-}"
if [[ "$cmd" == "list" ]]; then
  backup_init_dir
  pgtool sh -c 'cd /backups && find . -name "*.dump" -type f -printf "%TY-%Tm-%Td %TH:%TM  %10s  %P\n" | sort'
  exit 0
fi
[[ -n "$cmd" && -n "$name" ]] || bfail "Usage: backup-restore.sh list | receive|check|discard|restore <name> ..." "Run it through: just restore"
[[ "$name" =~ ^[A-Za-z0-9._/-]+$ && "$name" != .* && "$name" != */.* ]] || bfail "Bad dump name \"$name\"" "Use a plain file name from the backups volume, e.g. pre-upgrade-sha-abc-20261024-010203.dump"
backup_init_dir
file="/backups/$name"

case "$cmd" in
  receive)
    pgtool sh -c 'cat >"$1"' sh "$file"
    exit 0
    ;;
  discard)
    [[ "$name" == restore-upload-* ]] || bfail "discard only removes bin/restore's own uploads (restore-upload-*)" "Nothing was removed."
    pgtool rm -f "$file"
    exit 0
    ;;
  check)
    size="$(pg_dump_check "$file")" || bfail "$file is missing, empty or not a pg_dump -Fc file" "Pass a dump made by pg_dump -Fc (just backup or migrate's pre-upgrade dumps)."
    [[ -n "$(dc ps -q --status running db)" ]] || dc up -d --wait db >/dev/null
    printf 'dump_bytes=%s\ntables=%s\n' "$size" "$(db_table_count)"
    exit 0
    ;;
  restore) ;;
  *) bfail "Unknown command \"$cmd\"" "Run it through: just restore" ;;
esac

mode="${3:-}"
[[ "$mode" == "--empty-only" || "$mode" == "--replace" ]] || bfail "Restore mode missing" "Run it through: just restore"
shift 3
local_stack=0; remove_after=0; new_tag=""
for a in "$@"; do
  case "$a" in
    --local) local_stack=1 ;;
    --remove-after) remove_after=1 ;;
    --tag=*) new_tag="${a#--tag=}"
      [[ "$new_tag" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || bfail "Bad image tag \"$new_tag\"" "Pass e.g. --tag=sha-abc1234" ;;
    *) bfail "Unknown option \"$a\"" "Run it through: just restore" ;;
  esac
done
(( local_stack )) || backup_lock
[[ -z "$new_tag" || $local_stack -eq 0 ]] || bfail "--tag is for hosts only" "Locally, check out and build the code you want instead."
if [[ -n "$new_tag" ]]; then
  image="${IMAGE:-ghcr.io/ntholi/fivedays-moodle}"
  for suffix in "" -web; do
    docker image inspect "$image:$new_tag$suffix" >/dev/null 2>&1 || docker pull -q "$image:$new_tag$suffix" >/dev/null ||
      bfail "Image $image:$new_tag$suffix is not on this host and cannot be pulled" "Nothing was changed. Check the tag (just rollback prints it)."
  done
  bok "image $image:$new_tag present"
fi

blog "restore of $name into project $PROJECT ($mode)"
size="$(pg_dump_check "$file")" || bfail "$file is missing, empty or not a pg_dump -Fc file" "Nothing was changed."
bok "dump readable ($size bytes)"
dc up -d --wait db redis >/dev/null
tables="$(db_table_count)"
bok "current database has $tables tables"

if (( tables > 0 )); then
  [[ "$mode" == "--replace" ]] || bfail "The database of $PROJECT is not empty ($tables tables)" "Nothing was changed. Rerun just restore and type the confirmation it asks for."
  pre="/backups/pre-restore-$(date +%Y%m%d-%H%M%S).dump"
  blog "pre-restore dump of the current database → $pre"
  dc exec -T db pg_dump -U moodle -d moodle -Fc | pgtool sh -c 'cat >"$1"' sh "$pre"
  pre_size="$(pg_dump_check "$pre")" || bfail "The pre-restore dump $pre is empty or unreadable" "Nothing was dropped. Check disk space and the db logs."
  bok "pre-restore dump ok ($pre_size bytes); to undo: just restore <target> $(basename "$pre")"
fi

blog "stopping migrate, app, cron and web"
dc stop migrate app cron web >/dev/null 2>&1 || true

if (( tables > 0 )); then
  blog "dropping and recreating database moodle"
  dc exec -T db psql -U moodle -d postgres -XqtA -v ON_ERROR_STOP=1 \
    -c 'DROP DATABASE moodle WITH (FORCE)' -c 'CREATE DATABASE moodle OWNER moodle'
fi

blog "pg_restore (this takes a few minutes for a full prod dump)"
pgtool cat "$file" | dc exec -T db pg_restore -U moodle -d moodle --no-owner --exit-on-error
restored="$(db_table_count)"
(( restored > 0 )) || bfail "pg_restore finished but the database has no tables" "Restore the pre-restore dump printed above."
bok "restored $restored tables"

if (( local_stack )); then
  cleared="$(dc exec -T db psql -U moodle -d moodle -XqtAc \
    "UPDATE mdl_config_plugins SET value = '' WHERE plugin = 'tool_objectfs' AND name IN ('s3_key', 's3_secret') AND value <> '' RETURNING name" | grep -c . || true)"
  bok "local: $cleared stored tool_objectfs S3 key(s) cleared; email and push stay off (MOODLE_NOEMAIL=1)"
fi

if [[ -n "$new_tag" && "$new_tag" != "${IMAGE_TAG:-}" ]]; then
  live_tag="${IMAGE_TAG:-}"
  tmp="$FIVEDAYS_ENV_FILE.tmp"
  { grep -vE '^(IMAGE_TAG|PREVIOUS_IMAGE_TAG)=' "$FIVEDAYS_ENV_FILE"; printf 'IMAGE_TAG=%s\nPREVIOUS_IMAGE_TAG=%s\n' "$new_tag" "$live_tag"; } >"$tmp"
  chmod 600 "$tmp"; mv "$tmp" "$FIVEDAYS_ENV_FILE"
  export IMAGE_TAG="$new_tag" PREVIOUS_IMAGE_TAG="$live_tag"
  bok "IMAGE_TAG=$new_tag, PREVIOUS_IMAGE_TAG=$live_tag in $FIVEDAYS_ENV_FILE"
fi

blog "restarting redis (drops sessions and caches of the replaced database)"
dc restart redis >/dev/null
dc up -d --wait redis >/dev/null

blog "running migrate (upgrade if the dump is older than the code, then setup.php)"
dc up -d --force-recreate --no-deps migrate >/dev/null
migrate_id="$(dc ps -a -q migrate)"
state="$(docker wait "$migrate_id")"
dc logs --no-log-prefix migrate | grep -E '^\[migrate|^(ok|changed|FAIL) ' || true
[[ "$state" == "0" ]] || bfail "migrate exited $state after the restore" "Read the FAIL line above. app, cron and web were not started."
bok "migrate finished"
# shellcheck disable=SC2016
dc run --rm --no-deps -T migrate sh -c 'cd "$MOODLE_ROOT" && php admin/cli/purge_caches.php'
bok "caches purged"
dc up -d >/dev/null 2>&1 || true

# Local stacks publish LOCAL_PORT; a host only has the internal listener on 127.0.0.1:8080 (https wwwroot).
if [[ -n "${LOCAL_PORT:-}" ]]; then url="http://localhost:$LOCAL_PORT/login/index.php"; hdr="Host: localhost:$LOCAL_PORT"
else url="http://127.0.0.1:8080/login/index.php"; hdr="Host: $(sed -E 's#^https?://([^/]+).*#\1#' <<<"$MOODLE_WWWROOT")"; fi
code=""
for _ in $(seq 1 90); do
  code="$(curl -s -o /dev/null -w '%{http_code}' -H "$hdr" "$url" || true)"
  [[ "$code" == "200" ]] && break
  sleep 2
done
[[ "$code" == "200" ]] || bfail "Login page $url returned $code after the restore" "Read the app and web logs (just logs app / just logs web)."
bok "login page 200 ($url)"

if (( remove_after )); then pgtool rm -f "$file"; bok "removed the uploaded copy $file"; fi
bok "restore done"
