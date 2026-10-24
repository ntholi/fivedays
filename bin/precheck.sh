#!/usr/bin/env bash
# Read-only inventory of the old prod host. Runs ON the host as root (bin/precheck pipes it over SSH).
# It never writes: DB access uses the SELECT-only backupuser from /root/.my.cnf, git uses
# --no-optional-locks, and the only Moodle CLI call is uninstall_plugins.php without --run (dry listing).
# Output is Markdown on stdout. Secrets are never printed: config.php shows key names only.
set -euo pipefail

MOODLE_ROOT="${MOODLE_ROOT:-/var/www/html/sites/moodle}"
MYCNF=/root/.my.cnf

section() { printf '\n## %s\n\n' "$1"; }
code() { printf '```\n'; cat; printf '```\n'; }
cfgval() { grep -oP "^\s*\\\$CFG->$1\s*=\s*['\"]\K[^'\"]*" "$MOODLE_ROOT/config.php" | head -n1 || true; }

[[ -f "$MOODLE_ROOT/config.php" ]] || { echo "FAIL: $MOODLE_ROOT/config.php not found"; exit 1; }
[[ -f "$MYCNF" ]] || { echo "FAIL: $MYCNF (read-only backupuser) not found"; exit 1; }

dbname="$(cfgval dbname)"
prefix="$(cfgval prefix)"
q() { mysql --defaults-file="$MYCNF" --batch --skip-column-names "$dbname" -e "$1"; }
qh() { mysql --defaults-file="$MYCNF" --batch "$dbname" -e "$1"; }
setting() { q "SELECT CASE WHEN value IS NULL OR value = '' THEN '(empty)' ELSE value END FROM ${prefix}config WHERE name = '$1'" || true; }
isset() { q "SELECT CASE WHEN value IS NULL OR value = '' THEN 'empty' ELSE 'set' END FROM ${prefix}config WHERE name = '$1'" || true; }

printf '# Pre-check: %s\n\n' "$(hostname -f 2>/dev/null || hostname)"
printf 'Generated %s (UTC). Read-only.\n' "$(date -u '+%Y-%m-%d %H:%M')"

section "Host"
{
  echo "os: $(. /etc/os-release && echo "$PRETTY_NAME")"
  echo "cpus: $(nproc)"
  free -h | sed 's/^/  /'
  df -h / | sed 's/^/  /'
  echo "php: $(php -r 'echo PHP_VERSION;')"
  echo "mariadb: $(mysql --defaults-file="$MYCNF" --batch --skip-column-names -e 'SELECT VERSION()')"
  echo "nginx: $(nginx -v 2>&1)"
} | code

section "Moodle code"
vfile="$MOODLE_ROOT/public/version.php"
[[ -f "$vfile" ]] || vfile="$MOODLE_ROOT/version.php"
{
  echo "root: $MOODLE_ROOT"
  echo "version.php: $vfile"
  grep -E '^\$(version|release|branch|maturity)\s*=' "$vfile"
  echo "git describe: $(git -c safe.directory='*' -C "$MOODLE_ROOT" describe --tags --always 2>&1)"
  echo "git HEAD: $(git -c safe.directory='*' -C "$MOODLE_ROOT" rev-parse HEAD 2>&1)"
  echo "git branch: $(git -c safe.directory='*' -C "$MOODLE_ROOT" rev-parse --abbrev-ref HEAD 2>&1)"
  echo "remote: $(git -c safe.directory='*' -C "$MOODLE_ROOT" remote get-url origin 2>&1)"
  if git -c safe.directory='*' -C "$MOODLE_ROOT" ls-files --error-unmatch vendor/autoload.php >/dev/null 2>&1; then
    echo "vendor/ tracked: yes"
  else
    echo "vendor/ tracked: no (present on disk: $([[ -d $MOODLE_ROOT/vendor ]] && echo yes || echo no))"
  fi
} | code

# The install docs ran chmod -R/chown -R on the code, so every file's ctime and mode changed. Without
# trustctime/fileMode off, git re-hashes the whole tree; the timeout keeps it from loading the live host.
section "Local changes to Moodle code (git status; content changes and untracked files, mode changes ignored)"
timeout 180 git -c safe.directory='*' -c core.trustctime=false -c core.fileMode=false -C "$MOODLE_ROOT" \
  --no-optional-locks status --porcelain --untracked-files=normal 2>&1 | code ||
  echo "git status did not finish within 180 s; local edits unknown" | code

section "Schema files of installed plugins (sha256; cutover preflight compares them with the transfer image)"
(cd "$MOODLE_ROOT/public" && sha256sum local/fivedays/db/install.xml grade/grading/form/fivedays/db/install.xml) | code

section "config.php keys (values hidden except non-secret ones)"
{
  echo "dbtype: $(cfgval dbtype)"
  echo "dblibrary: $(cfgval dblibrary)"
  echo "dbname: $dbname"
  echo "prefix: $prefix"
  echo "wwwroot: $(cfgval wwwroot)"
  echo "dataroot: $(cfgval dataroot)"
  echo "all \$CFG keys set in config.php:"
  grep -oP '\$CFG->\K\w+(?=\s*=)' "$MOODLE_ROOT/config.php" | sort -u | sed 's/^/  /'
  echo "dboptions keys:"
  grep -oP "'\K\w+(?='\s*=>)" "$MOODLE_ROOT/config.php" | sort -u | sed 's/^/  /'
} | code

section "Non-core plugins (uninstall_plugins.php --show-contrib, dry listing)"
sudo -u www-data php "$MOODLE_ROOT/admin/cli/uninstall_plugins.php" --show-contrib 2>&1 | code

section "Settings"
{
  for name in release version auth enablewebservices enablemobilewebservice webserviceprotocols maxbytes slasharguments routerconfigured antiviruses smtphosts smtpsecure smtpauthtype noreplyaddress noemailever airnotifierurl airnotifierport airnotifiermobileappname airnotifierappname; do
    printf '%s: %s\n' "$name" "$(setting "$name")"
  done
  printf 'smtpuser: %s\n' "$(isset smtpuser)"
  printf 'smtppass: %s\n' "$(isset smtppass)"
  printf 'airnotifieraccesskey: %s\n' "$(isset airnotifieraccesskey)"
  printf 'registration_hubs: %s\n' "$(q "SELECT CONCAT(huburl, ' confirmed=', confirmed) FROM ${prefix}registration_hubs" | tr '\n' ' ')"
  printf 'airnotifier devices: %s\n' "$(q "SELECT COUNT(*) FROM ${prefix}message_airnotifier_devices")"
  printf 'enabled message outputs: %s\n' "$(q "SELECT GROUP_CONCAT(name) FROM ${prefix}message_processors WHERE enabled = 1")"
  printf 'tool_mobile typeoflogin: %s\n' "$(q "SELECT value FROM ${prefix}config_plugins WHERE plugin = 'tool_mobile' AND name = 'typeoflogin'")"
  printf 'clamav pathtoclam: %s\n' "$(q "SELECT value FROM ${prefix}config_plugins WHERE plugin = 'antivirus_clamav' AND name IN ('pathtoclam','runningmethod','pathtounixsocket','tcpsockethost')" | tr '\n' ' ')"
} | code

section "Web services"
qh "SELECT s.id, s.name, s.shortname, s.component, s.enabled, s.restrictedusers, s.requiredcapability, s.downloadfiles, s.uploadfiles,
       (SELECT COUNT(*) FROM ${prefix}external_tokens t WHERE t.externalserviceid = s.id) AS tokens,
       (SELECT COUNT(*) FROM ${prefix}external_services_users u WHERE u.externalserviceid = s.id) AS authorised_users,
       (SELECT COUNT(*) FROM ${prefix}external_services_functions f WHERE f.externalserviceid = s.id) AS functions
    FROM ${prefix}external_services s ORDER BY s.id" | code

section "Functions on the FiveDays service (shortname fivedays)"
q "SELECT f.functionname FROM ${prefix}external_services_functions f JOIN ${prefix}external_services s ON s.id = f.externalserviceid
    WHERE s.shortname = 'fivedays' ORDER BY f.functionname" | code

section "Web-service tokens (counts only)"
qh "SELECT t.externalserviceid AS service, t.tokentype, COUNT(*) AS tokens, COUNT(DISTINCT t.userid) AS users,
       COUNT(DISTINCT t.creatorid) AS creators, SUM(t.lastaccess > UNIX_TIMESTAMP() - 30 * 86400) AS used_30d,
       SUM(t.validuntil > 0) AS expiring
    FROM ${prefix}external_tokens t GROUP BY t.externalserviceid, t.tokentype ORDER BY 1, 2" | code
qh "SELECT t.externalserviceid AS service, t.creatorid, u.username AS creator, COUNT(*) AS tokens
    FROM ${prefix}external_tokens t JOIN ${prefix}user u ON u.id = t.creatorid
    GROUP BY t.externalserviceid, t.creatorid, u.username HAVING COUNT(*) > 20 ORDER BY 1, 4 DESC" | code

section "Outgoing mail (smtphosts empty means PHP mail() through the host's sendmail)"
{
  set +o pipefail # missing logs and empty greps are answers here, not failures
  echo "sendmail binary: $(command -v sendmail || echo none) $(readlink -f "$(command -v sendmail 2>/dev/null)" 2>/dev/null)"
  echo "php sendmail_path: $(php -r 'echo ini_get("sendmail_path");')"
  for svc in postfix exim4 msmtp nullmailer; do
    printf '%s: %s\n' "$svc" "$(dpkg-query -W -f='${Status}' "$svc" 2>/dev/null || echo 'not installed') / $(systemctl is-active "$svc" 2>/dev/null || true)"
  done
  if [[ -f /etc/postfix/main.cf ]]; then
    echo "postfix relayhost: $(postconf -h relayhost 2>/dev/null)"
    echo "postfix myhostname: $(postconf -h myhostname 2>/dev/null)"
  fi
  echo "mail.log lines: $(cat /var/log/mail.log /var/log/mail.log.1 2>/dev/null | wc -l)"
  echo "mail.log status counts (last 2 files):"
  cat /var/log/mail.log /var/log/mail.log.1 2>/dev/null | { grep -oP 'status=\w+' || true; } | sort | uniq -c | sed 's/^/  /'
  echo "mail.log last delivery line date: $(cat /var/log/mail.log 2>/dev/null | { grep 'status=' || true; } | tail -n1 | cut -c1-25)"
  echo "outbound port 25 reachable (gmail-smtp-in): $(timeout 5 bash -c '</dev/tcp/gmail-smtp-in.l.google.com/25' 2>/dev/null && echo yes || echo no)"
} | code

section "OAuth2 issuers"
qh "SELECT id, name, enabled, requireconfirmation, alloweddomains FROM ${prefix}oauth2_issuer ORDER BY id" | code

section "Data size"
{
  echo "moodledata: $(du -sh "$(cfgval dataroot)" 2>/dev/null | cut -f1)"
  echo "filedir: $(du -sh "$(cfgval dataroot)/filedir" 2>/dev/null | cut -f1), $(find "$(cfgval dataroot)/filedir" -type f | wc -l) files"
  echo "mdl_files rows: $(q "SELECT COUNT(*) FROM ${prefix}files")"
  echo "distinct contenthash: $(q "SELECT COUNT(DISTINCT contenthash) FROM ${prefix}files")"
  echo "DB size (MB): $(q "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024) FROM information_schema.tables WHERE table_schema = '$dbname'")"
  echo "backups dir: $(ls -1 /var/backups/moodle 2>/dev/null | tail -n 3 | tr '\n' ' ')"
} | code

section "Cron"
{
  echo "www-data:"; crontab -l -u www-data 2>&1 | sed 's/^/  /'
  echo "root:"; crontab -l 2>&1 | sed 's/^/  /'
} | code

section "Row count per table (baseline for just verify)"
tables="$(q "SELECT table_name FROM information_schema.tables WHERE table_schema = '$dbname' AND table_type = 'BASE TABLE' ORDER BY table_name")"
{
  for t in $tables; do
    printf '%s\t%s\n' "$t" "$(q "SELECT COUNT(*) FROM \`$t\`")"
  done
} | code
