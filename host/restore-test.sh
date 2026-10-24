#!/usr/bin/env bash
# Monthly restore test (D23), as root on the host via /etc/cron.d/fivedays. Restores the newest db dump in
# R2 (restic host = project, tag pgdump) into a throwaway Postgres container that is on no network,
# checks it looks like this Moodle (table count, mdl_user rows, mdl_config version), then removes only
# that container and its anonymous volume. The result goes to backup.status (last_restore_test_ok or
# last_restore_test_failed). FIVEDAYS_DIR (default /opt/fivedays) lets a local test point it elsewhere.
set -euo pipefail
source "$(dirname "$0")/backup-lib.sh"

backup_init_dir
backup_lock
scratch="fivedays-restoretest-$(date +%Y%m%d%H%M%S)-$$"
step="start"
on_exit() {
  local rc=$?
  docker rm -f -v "$scratch" >/dev/null 2>&1 || true
  if (( rc != 0 )); then
    status_set last_restore_test_failed "$(now_utc) step=$step exit=$rc"
    printf '[%s] FAIL  restore test failed at step "%s"; backup.status records it\n' "$(date +%H:%M:%S)" "$step" >&2
  fi
}
trap on_exit EXIT

blog "restore test for project $PROJECT (throwaway container $scratch)"
step="find-snapshot"
restic_use_key write
read -r snap snap_time < <(restic_latest --host "$PROJECT" --tag pgdump) ||
  bfail "No db snapshot for host $PROJECT in $RESTIC_REPOSITORY" "Run: just backup <host>"
bok "newest db snapshot ${snap:0:8} from $snap_time"

step="start-scratch"
docker run -d --name "$scratch" --network none --shm-size 256m \
  -e POSTGRES_USER=moodle -e POSTGRES_DB=moodle -e POSTGRES_PASSWORD="$(head -c 18 /dev/urandom | base64)" \
  "postgres:${POSTGRES_VERSION}-bookworm" >/dev/null
# The image runs a temporary server for initdb first; wait for the real one.
for _ in $(seq 1 90); do
  docker logs "$scratch" 2>&1 | grep -q 'PostgreSQL init process complete' &&
    docker exec "$scratch" pg_isready -q -U moodle -d moodle && break
  sleep 2
done
docker exec "$scratch" pg_isready -q -U moodle -d moodle || bfail "Scratch Postgres did not start" "Read: docker logs $scratch"

step="restore"
blog "restoring the dump into $scratch"
restic_run -- dump --no-lock "$snap" /backups/nightly/moodle.dump |
  docker exec -i "$scratch" pg_restore -U moodle -d moodle --no-owner --exit-on-error

step="check"
q() { docker exec "$scratch" psql -U moodle -d moodle -XtAc "$1"; }
tables="$(q "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")"
users="$(q 'SELECT count(*) FROM mdl_user')"
version="$(q "SELECT value FROM mdl_config WHERE name = 'version'")"
(( tables >= 300 )) || bfail "Restored database has only $tables tables (a Moodle 5.2 site has 400+)" "Check the nightly dump; run: just backup <host>"
(( users > 0 )) || bfail "Restored mdl_user is empty" "Check the nightly dump; run: just backup <host>"
[[ -n "$version" ]] || bfail "Restored mdl_config has no version" "Check the nightly dump; run: just backup <host>"
bok "restored: $tables tables, $users users, Moodle version $version"

step="status"
status_set last_restore_test_ok "$(now_utc) snapshot=${snap:0:8} snapshot_time=$snap_time tables=$tables users=$users version=$version"
bok "restore test passed; $STATUS_FILE updated; $scratch removed"
