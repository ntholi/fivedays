# Shared by the host scripts (host/backup.sh, host/restore-test.sh, host/backup-restore.sh; on the host
# as root, copied to /opt/fivedays/host by just deploy) and by bin/backup, bin/restore and
# bin/seed-from-prod on the laptop. Source it; don't run it.
#
# Everything runs in containers: the host has Docker but no restic, no pg tools and no aws.
# restic: restic/restic:$RESTIC_VERSION. pg_dump/pg_restore helpers: postgres:$POSTGRES_VERSION-bookworm.
# shellcheck shell=bash

blog() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }
bok() { printf '[%s] ok    %s\n' "$(date +%H:%M:%S)" "$*"; }
bwarn() { printf '[%s] WARN  %s\n' "$(date +%H:%M:%S)" "$*"; }
# bfail "<what happened>" "<what to do>"
bfail() {
  printf '[%s] FAIL  %s\n' "$(date +%H:%M:%S)" "$1" >&2
  if [[ -n "${2:-}" ]]; then printf '      What to do: %s\n' "$2" >&2; fi
  exit 1
}

# backup_init_dir: reads $FIVEDAYS_DIR/versions.env and the stack's env file.
#   FIVEDAYS_DIR            the stack directory (default /opt/fivedays, the host layout)
#   FIVEDAYS_ENV_FILE       its env file (default $FIVEDAYS_DIR/.env)
#   FIVEDAYS_COMPOSE_FILES  compose files in $FIVEDAYS_DIR, space separated (default compose.yaml)
# Sets PROJECT (COMPOSE_PROJECT_NAME), STATUS_FILE and RESTIC_CACHE_VOLUME.
backup_init_dir() {
  FIVEDAYS_DIR="${FIVEDAYS_DIR:-/opt/fivedays}"
  FIVEDAYS_ENV_FILE="${FIVEDAYS_ENV_FILE:-$FIVEDAYS_DIR/.env}"
  FIVEDAYS_COMPOSE_FILES="${FIVEDAYS_COMPOSE_FILES:-compose.yaml}"
  [[ -f "$FIVEDAYS_DIR/versions.env" ]] || bfail "$FIVEDAYS_DIR/versions.env not found" "Run: just deploy <host> <tag> (it copies the stack to $FIVEDAYS_DIR)."
  [[ -f "$FIVEDAYS_ENV_FILE" ]] || bfail "$FIVEDAYS_ENV_FILE not found" "Run: just deploy <host> <tag> (it writes the host env file)."
  set -a
  # shellcheck disable=SC1091
  source "$FIVEDAYS_DIR/versions.env"
  # shellcheck disable=SC1090
  source "$FIVEDAYS_ENV_FILE"
  set +a
  PROJECT="${COMPOSE_PROJECT_NAME:-fivedays}"
  STATUS_FILE="$FIVEDAYS_DIR/backup.status"
  RESTIC_CACHE_VOLUME="${RESTIC_CACHE_VOLUME:-${PROJECT}_restic_cache}"
}

# dc <compose args...>: docker compose for the stack in $FIVEDAYS_DIR (same as HOST_COMPOSE in bin/lib.sh).
dc() {
  local args=(--project-directory "$FIVEDAYS_DIR" --env-file "$FIVEDAYS_DIR/versions.env" --env-file "$FIVEDAYS_ENV_FILE") f
  for f in $FIVEDAYS_COMPOSE_FILES; do args+=(-f "$FIVEDAYS_DIR/$f"); done
  docker compose "${args[@]}" "$@"
}

# pgtool <cmd...>: runs a command in a throwaway postgres container with the backups volume at /backups.
# The command runs as www-data (uid 33, like migrate in the app image), and /backups is kept 33:33 mode
# 770 as the app image creates it, so migrate's pre-upgrade dumps still work if this mounted it first.
# stdin is passed only when it is not a terminal, so a tool never swallows what the user types next.
pgtool() {
  local in=(); [[ -t 0 ]] || in=(-i)
  # shellcheck disable=SC2016
  docker run --rm "${in[@]}" --network none -v "${PROJECT}_backups:/backups" "postgres:${POSTGRES_VERSION}-bookworm" \
    sh -c 'chown 33:33 /backups && chmod 770 /backups && exec gosu 33:33 "$@"' sh "$@"
}

# pg_dump_check <path in the backups volume>: non-empty and readable by pg_restore; prints its size in bytes.
pg_dump_check() {
  # shellcheck disable=SC2016
  pgtool sh -c 'test -s "$1" && pg_restore --list "$1" >/dev/null && stat -c %s "$1"' sh "$1"
}

# db_table_count: tables in the stack's database (0 = empty).
db_table_count() {
  dc exec -T db psql -U moodle -d moodle -XtAc "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'" </dev/null
}

# restic_use_key write|read: points restic at the R2 repo with the write key (host) or the read-only
# key (laptops). The repo lives under restic/ in BACKUP_BUCKET, so the bucket can hold nothing else of ours.
restic_use_key() {
  local key secret
  if [[ "$1" == "write" ]]; then key="${BACKUP_KEY:-}"; secret="${BACKUP_SECRET:-}"; else key="${BACKUP_READ_KEY:-}"; secret="${BACKUP_READ_SECRET:-}"; fi
  [[ -n "${BACKUP_S3_ENDPOINT:-}" && -n "${BACKUP_BUCKET:-}" && -n "$key" && -n "$secret" && -n "${RESTIC_PASSWORD:-}" ]] ||
    bfail "Backup settings missing (BACKUP_S3_ENDPOINT, BACKUP_BUCKET, the $1 key and RESTIC_PASSWORD)" "Add them with just init (they come from .env.prod)."
  export RESTIC_REPOSITORY="s3:${BACKUP_S3_ENDPOINT%/}/${BACKUP_BUCKET}/restic"
  export RESTIC_PASSWORD AWS_ACCESS_KEY_ID="$key" AWS_SECRET_ACCESS_KEY="$secret" AWS_DEFAULT_REGION="${BACKUP_S3_REGION:-auto}"
  RESTIC_CACHE_VOLUME="${RESTIC_CACHE_VOLUME:-fivedays-restic-cache}"
}

# restic_run [docker -v/-e args...] -- <restic args...>: restic in its pinned container. Secrets are passed
# by name (-e VAR), so they never appear on a command line.
restic_run() {
  local extra=()
  [[ -t 0 ]] || extra=(-i)
  while [[ $# -gt 0 && "$1" != "--" ]]; do extra+=("$1"); shift; done
  [[ "${1:-}" == "--" ]] && shift
  docker run --rm --network host \
    -e RESTIC_REPOSITORY -e RESTIC_PASSWORD -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION \
    -v "${RESTIC_CACHE_VOLUME}:/root/.cache/restic" "${extra[@]}" "restic/restic:${RESTIC_VERSION}" "$@"
}

# restic_ensure_repo: creates the repo on first use (restic exit code 10 = "repository does not exist").
# Any other error (wrong password, no access) stops: a new repo is never created over an unreadable one.
restic_ensure_repo() {
  local rc=0
  restic_run -- cat config >/dev/null 2>&1 || rc=$?
  case "$rc" in
    0) ;;
    10) blog "no restic repo at $RESTIC_REPOSITORY yet, creating it"
        restic_run -- init >/dev/null || bfail "restic init failed" "Check the bucket name and that the key may write to it." ;;
    *) restic_run -- cat config >/dev/null || true
       bfail "restic cannot open $RESTIC_REPOSITORY (exit $rc, message above)" "Check RESTIC_PASSWORD and the key in the env file against the password manager." ;;
  esac
}

# restic_latest <restic snapshot filters...>: prints "<id> <time>" of the newest matching snapshot, or nothing.
# Each snapshot in restic's JSON has exactly one "time" and one "id" key, in that order.
restic_latest() {
  local json
  json="$(restic_run -- snapshots --no-lock --json "$@")"
  paste -d' ' <(grep -o '"time":"[^"]*"' <<<"$json" | cut -d'"' -f4) <(grep -o '"id":"[0-9a-f]*"' <<<"$json" | cut -d'"' -f4) |
    sort | tail -n1 | awk 'NF == 2 {print $2, $1}'
}

# status_set <key> <value>: one key=value line per key in $STATUS_FILE, replaced atomically.
status_set() {
  local key="$1" value="$2" tmp="$STATUS_FILE.tmp"
  { grep -v "^$key=" "$STATUS_FILE" 2>/dev/null || true; printf '%s=%s\n' "$key" "$value"; } >"$tmp"
  mv "$tmp" "$STATUS_FILE"
}

# backup_lock: one backup, restore test or host restore at a time (waits up to an hour).
backup_lock() {
  exec 9>"$FIVEDAYS_DIR/backup.lock"
  flock -w 3600 9 || bfail "Another backup, restore test or restore is still running after an hour" "Look for it with: ps aux | grep fivedays"
}

now_utc() { date -u +%Y-%m-%dT%H:%M:%SZ; }
