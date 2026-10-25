#!/usr/bin/env bash
# Runs as root ON THE OLD PROD HOST (luct.click: nginx, php8.3-fpm, MariaDB, Moodle outside Docker). bin/rehearse,
# bin/cutover and bin/cutover-rollback pipe it through `sudo bash -s -- <command> [args]` (never run it on the
# laptop or the new host). Nothing here touches Moodle's data except `freeze`/`unfreeze`, which use Moodle's own
# admin/cli/maintenance.php. Database reads use the SELECT-only backupuser in /root/.my.cnf.
#
# Commands:
#   inspect                  key=value facts: layout, php-fpm, cron line, maintenance, MariaDB socket, sizes, dumps
#   key-add <marker> <line>  authorise the cutover's ephemeral public key for user ubuntu (replaces an older one)
#   key-remove <marker>      remove it again; prints "remaining=<n>" (must be 0)
#   freeze                   cutover phase 1: maintenance on, cron line commented out, running cron finished,
#                            php-fpm stopped. Safe to rerun.
#   unfreeze                 the reverse (cutover-rollback): cron line back, php-fpm started, maintenance off.
#   status                   key=value: php_fpm, fpm_processes, maintenance, cron_line, cron_running
#   tokens                   FiveDays service token count
#   sizes                    moodledata and database size in MB, filedir file count
set -euo pipefail

ROOT=/var/www/html/sites/moodle
DATAROOT=/var/www/data/moodledata
MYCNF=/root/.my.cnf
FPM=php8.3-fpm
AUTH_KEYS=/home/ubuntu/.ssh/authorized_keys
# The www-data crontab line from the pre-check; commented out with this prefix during the window.
CRON_MARK='#fivedays-cutover# '
CRON_PATTERN="$ROOT/admin/cli/cron.php"

fail() { printf 'FAIL  %s\n' "$1" >&2; exit 1; }
q() { mysql --defaults-file="$MYCNF" --batch --skip-column-names moodle -e "$1"; }
www() { (cd "$ROOT" && sudo -u www-data php "$@"); }

cron_state() {
  local tab
  tab="$(crontab -u www-data -l 2>/dev/null || true)"
  if grep -qF -- "$CRON_PATTERN" <<<"$(grep -v '^#' <<<"$tab")"; then echo present
  elif grep -qF -- "$CRON_MARK" <<<"$tab"; then echo commented
  else echo missing; fi
}
cron_running() { pgrep -c -f "$ROOT/admin/cli/(cron|adhoc_task|scheduled_task)\.php" || true; }
fpm_processes() { pgrep -c -f '^php-fpm: ' || true; }
maintenance() { [[ -f "$DATAROOT/climaintenance.html" ]] && echo on || echo off; }

cmd="${1:-}"
shift || true
case "$cmd" in
  inspect)
    [[ -f "$ROOT/config.php" ]] || fail "$ROOT/config.php not found: this is not the old Moodle host"
    [[ -r "$MYCNF" ]] || fail "$MYCNF (read-only backupuser) not found"
    command -v mysqldump >/dev/null || fail "mysqldump is not installed"
    echo "moodle_root=$ROOT"
    echo "dbtype=$(grep -oP "^\s*\\\$CFG->dbtype\s*=\s*['\"]\K[^'\"]*" "$ROOT/config.php" | head -n1)"
    echo "php_fpm=$(systemctl is-active "$FPM" 2>/dev/null || true)"
    echo "cron_line=$(cron_state)"
    echo "maintenance=$(maintenance)"
    echo "mysql_socket=$(mysql --defaults-file="$MYCNF" --batch --skip-column-names -e 'SELECT @@socket')"
    echo "free_mb=$(df -Pm / | awk 'NR == 2 { print $4 }')"
    dump="$(find /var/backups/moodle -maxdepth 1 -name 'moodle_backup_*.sql' -type f -printf '%T@ %s %p\n' 2>/dev/null | sort -n | tail -n1 || true)"
    if [[ -n "$dump" ]]; then
      echo "nightly_dump=${dump#* * }"
      echo "nightly_dump_bytes=$(awk '{ print $2 }' <<<"$dump")"
      echo "nightly_dump_age_h=$(( ($(date +%s) - ${dump%%.*}) / 3600 ))"
    fi
    (cd "$ROOT/public" && sha256sum local/fivedays/db/install.xml grade/grading/form/fivedays/db/install.xml) |
      awk '{ printf "installxml %s %s\n", $2, $1 }'
    ;;
  key-add)
    marker="$1" line="$2"
    [[ "$line" == *"$marker"* ]] || fail "the key line does not carry the marker $marker"
    install -d -m 700 -o ubuntu -g ubuntu /home/ubuntu/.ssh
    touch "$AUTH_KEYS"
    { grep -vF -- "$marker" "$AUTH_KEYS" || true; printf '%s\n' "$line"; } >"$AUTH_KEYS.fivedays-tmp"
    chown ubuntu:ubuntu "$AUTH_KEYS.fivedays-tmp"
    chmod 600 "$AUTH_KEYS.fivedays-tmp"
    mv "$AUTH_KEYS.fivedays-tmp" "$AUTH_KEYS"
    echo "added=$(grep -cF -- "$marker" "$AUTH_KEYS")"
    ;;
  key-remove)
    marker="$1"
    if [[ -f "$AUTH_KEYS" ]] && grep -qF -- "$marker" "$AUTH_KEYS"; then
      { grep -vF -- "$marker" "$AUTH_KEYS" || true; } >"$AUTH_KEYS.fivedays-tmp"
      chown ubuntu:ubuntu "$AUTH_KEYS.fivedays-tmp"
      chmod 600 "$AUTH_KEYS.fivedays-tmp"
      mv "$AUTH_KEYS.fivedays-tmp" "$AUTH_KEYS"
    fi
    echo "remaining=$(grep -cF -- "$marker" "$AUTH_KEYS" 2>/dev/null || true)"
    ;;
  freeze)
    # admin/cli/maintenance.php:77-89: --enable writes $CFG->dataroot/climaintenance.html, and lib/setup.php:356-371
    # then answers every web request with 503 (CLI scripts still run); it also sets maintenance_enabled in the
    # database, which bin/cutover turns off again on the new host. Idempotent (maintenance.php:78-81).
    www admin/cli/maintenance.php --enable >/dev/null
    tab="$(crontab -u www-data -l 2>/dev/null || true)"
    if [[ "$(cron_state)" == present ]]; then
      awk -v pat="$CRON_PATTERN" -v mark="$CRON_MARK" '!/^#/ && index($0, pat) { print mark $0; next } { print }' <<<"$tab" | crontab -u www-data -
    fi
    # A cron run that started before the line was commented out finishes on its own; wait up to 15 minutes.
    for _ in $(seq 1 180); do [[ "$(cron_running)" == 0 ]] && break; sleep 5; done
    [[ "$(cron_running)" == 0 ]] || fail "a Moodle cron process is still running after 15 minutes"
    systemctl stop "$FPM"
    for _ in $(seq 1 30); do [[ "$(fpm_processes)" == 0 ]] && break; sleep 1; done
    echo "php_fpm=$(systemctl is-active "$FPM" 2>/dev/null || true) fpm_processes=$(fpm_processes) maintenance=$(maintenance) cron_line=$(cron_state) cron_running=$(cron_running)"
    ;;
  unfreeze)
    tab="$(crontab -u www-data -l 2>/dev/null || true)"
    if [[ "$(cron_state)" == commented ]]; then
      sed "s|^$CRON_MARK||" <<<"$tab" | crontab -u www-data -
    fi
    systemctl start "$FPM"
    # admin/cli/maintenance.php:99-110: --disable clears maintenance_enabled and removes climaintenance.html.
    www admin/cli/maintenance.php --disable >/dev/null
    echo "php_fpm=$(systemctl is-active "$FPM" 2>/dev/null || true) fpm_processes=$(fpm_processes) maintenance=$(maintenance) cron_line=$(cron_state)"
    ;;
  status)
    echo "php_fpm=$(systemctl is-active "$FPM" 2>/dev/null || true)"
    echo "fpm_processes=$(fpm_processes)"
    echo "maintenance=$(maintenance)"
    echo "cron_line=$(cron_state)"
    echo "cron_running=$(cron_running)"
    ;;
  tokens)
    q "SELECT COUNT(*) FROM mdl_external_tokens t JOIN mdl_external_services s ON s.id = t.externalserviceid WHERE s.shortname = 'fivedays'"
    ;;
  sizes)
    echo "moodledata_mb=$(du -sm "$DATAROOT" | cut -f1)"
    echo "filedir_files=$(find "$DATAROOT/filedir" -type f ! -name warning.txt | wc -l)"
    echo "db_mb=$(q "SELECT ROUND(SUM(data_length + index_length) / 1048576) FROM information_schema.tables WHERE table_schema = 'moodle'")"
    ;;
  *) fail "unknown command \"$cmd\" (inspect, key-add, key-remove, freeze, unfreeze, status, tokens, sizes)" ;;
esac
