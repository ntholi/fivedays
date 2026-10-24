#!/usr/bin/env bash
# Nightly backup (D23), as root on the host: /etc/cron.d/fivedays runs it, and `just backup <host>` runs it now.
#   1. pg_dump -Fc of the stack's Postgres into the backups volume (nightly/moodle.dump.tmp), checked:
#      non-empty and pg_restore --list reads it; then it replaces nightly/moodle.dump (only one is kept).
#   2. restic snapshot of the dump (tag pgdump) and of moodledata without filedir (in the bucket), cache,
#      localcache, temp, sessions and trashdir (tag moodledata), to the R2 repo. restic host = project name.
#   3. Retention: 14 daily + 8 weekly per kind for this host, then prune. Other hosts' snapshots (the old
#      host's offsite copies) are never touched.
#   4. backup.status: last_backup_ok, or last_backup_failed with the step that failed.
# FIVEDAYS_DIR (default /opt/fivedays) lets a local test point it at a throwaway stack.
set -euo pipefail
source "$(dirname "$0")/backup-lib.sh"

backup_init_dir
backup_lock
started="$(now_utc)"
step="start"
on_exit() {
  local rc=$?
  pgtool rm -f /backups/nightly/moodle.dump.tmp >/dev/null 2>&1 || true
  if (( rc != 0 )); then
    status_set last_backup_failed "$(now_utc) step=$step exit=$rc"
    printf '[%s] FAIL  backup failed at step "%s"; backup.status records it\n' "$(date +%H:%M:%S)" "$step" >&2
  fi
}
trap on_exit EXIT

blog "backup of project $PROJECT in $FIVEDAYS_DIR (restic image $RESTIC_VERSION)"
step="preconditions"
for v in moodledata backups; do
  docker volume inspect "${PROJECT}_$v" >/dev/null 2>&1 || bfail "Volume ${PROJECT}_$v is missing" "Run: just provision <host>"
done
[[ -n "$(dc ps -q --status running db)" ]] || bfail "The db container of $PROJECT is not running" "Start the stack (just deploy) and rerun."
restic_use_key write
bok "volumes present, db running, backup settings present"
restic_ensure_repo
bok "restic repo $RESTIC_REPOSITORY opens"

step="dump"
blog "pg_dump -Fc → backups volume"
pgtool mkdir -p /backups/nightly
dc exec -T db pg_dump -U moodle -d moodle -Fc | pgtool sh -c 'cat >/backups/nightly/moodle.dump.tmp'
size="$(pg_dump_check /backups/nightly/moodle.dump.tmp)" || bfail "The dump is empty or pg_restore cannot read it" "Read the pg_dump error above; nothing was sent to R2."
pgtool mv -f /backups/nightly/moodle.dump.tmp /backups/nightly/moodle.dump
bok "dump ok ($size bytes, pg_restore --list reads it)"

# restic backup --json ends with a summary line holding snapshot_id.
snapshot_id() { grep -o '"snapshot_id":"[0-9a-f]*"' | cut -d'"' -f4; }

step="snapshot-db"
out="$(restic_run -v "${PROJECT}_backups:/backups:ro" -- backup --json --host "$PROJECT" --tag pgdump /backups/nightly/moodle.dump)"
db_snap="$(snapshot_id <<<"$out")"
[[ -n "$db_snap" ]] || bfail "restic printed no snapshot id for the dump" "Read the restic output above."
bok "db snapshot ${db_snap:0:8}"

step="snapshot-moodledata"
out="$(restic_run -v "${PROJECT}_moodledata:/var/moodledata:ro" -- backup --json --host "$PROJECT" --tag moodledata \
  --exclude /var/moodledata/filedir --exclude /var/moodledata/cache --exclude /var/moodledata/localcache \
  --exclude /var/moodledata/temp --exclude /var/moodledata/sessions --exclude /var/moodledata/trashdir \
  /var/moodledata)"
files_snap="$(snapshot_id <<<"$out")"
[[ -n "$files_snap" ]] || bfail "restic printed no snapshot id for moodledata" "Read the restic output above."
bok "moodledata snapshot ${files_snap:0:8}"

step="retention"
restic_run -- forget --host "$PROJECT" --group-by host,tags --keep-daily 14 --keep-weekly 8 --prune --quiet
bok "retention applied (14 daily, 8 weekly) and pruned"

step="status"
status_set last_backup_ok "$(now_utc) started=$started db=${db_snap:0:8} moodledata=${files_snap:0:8} dump_bytes=$size"
bok "backup done; $STATUS_FILE updated"
