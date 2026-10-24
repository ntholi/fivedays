#!/usr/bin/env bash
# One-shot `migrate` service: wait for Postgres → install (only when allowed) or back up and upgrade →
# local_fivedays setup → purge caches if anything changed. app, cron and web start only after this exits 0.
set -euo pipefail
cd "$MOODLE_ROOT"

log() { printf '[migrate %s] %s\n' "$(date +%H:%M:%S)" "$*"; }
fail() { printf '[migrate %s] FAIL  %s\n' "$(date +%H:%M:%S)" "$*" >&2; exit 1; }

export PGHOST="${MOODLE_DB_HOST:-db}" PGDATABASE="${MOODLE_DB_NAME:-moodle}" PGUSER="${MOODLE_DB_USER:-moodle}"
export PGPASSWORD="$MOODLE_DB_PASSWORD"

for _ in $(seq 1 60); do
  pg_isready -q && break
  sleep 2
done
pg_isready -q || fail "Postgres at $PGHOST did not become ready in 120 s."

installed="$(psql -XtAc "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'mdl_config'")"
changed=0

if [[ "$installed" == "0" ]]; then
  [[ "${MOODLE_ALLOW_INSTALL:-0}" == "1" ]] ||
    fail "Database is empty; refusing to install on prod. Did the data transfer run?"
  log "empty database, installing Moodle (MOODLE_ALLOW_INSTALL=1)"
  php admin/cli/install_database.php --agree-license --lang=en \
    --adminuser=admin --adminpass="$MOODLE_ADMIN_PASSWORD" --adminemail="$MOODLE_ADMIN_EMAIL" \
    --fullname="$MOODLE_SITE_NAME" --shortname="$MOODLE_SITE_SHORTNAME"
  changed=1
else
  set +e
  php admin/cli/upgrade.php --is-pending >/dev/null
  pending=$?
  set -e
  case "$pending" in
    0) log "no upgrade pending" ;;
    2)
      dump="/backups/pre-upgrade-${IMAGE_TAG:-unknown}-$(date +%Y%m%d-%H%M%S).dump"
      log "upgrade pending, dumping the database to $dump first"
      pg_dump -Fc -f "$dump"
      [[ -s "$dump" ]] || fail "Dump $dump is empty; not upgrading."
      pg_restore --list "$dump" >/dev/null || fail "Dump $dump is not readable by pg_restore; not upgrading."
      log "dump ok ($(du -h "$dump" | cut -f1)), upgrading"
      php admin/cli/upgrade.php --non-interactive
      changed=1
      ;;
    *) fail "upgrade.php --is-pending exited $pending (expected 0 or 2)." ;;
  esac
fi

log "running local_fivedays setup"
php public/local/fivedays/cli/setup.php || fail "local/fivedays/cli/setup.php reported a FAIL (see the lines above)."

if (( changed )); then
  log "purging caches"
  php admin/cli/purge_caches.php
fi
log "done"
