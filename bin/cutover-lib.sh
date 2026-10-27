# Shared by bin/rehearse, bin/cutover and bin/cutover-rollback. Source it; don't run it.
# shellcheck shell=bash
#
# What lives here:
#   - run directory, SSH options (own known_hosts, host keys pinned per machine with HostKeyAlias, so a host
#     keeps its key when the static IP moves) and the remote helpers host/cutover-old.sh / host/cutover-new.sh;
#   - cutover.state (key=value lines on the laptop) and build/cutover/report.md;
#   - Lightsail (static IP, instance addresses) through the pinned amazon/aws-cli image and the CUTOVER_* key;
#   - Vercel (luct's MOODLE_TOKEN and LMS_SYNC_PAUSED, redeploy) through the vercel CLI;
#   - the public URL checks and the ephemeral host-to-host key.
#
# Test stubs (CUTOVER_STUBS=static-ip,vercel,cert) replace Lightsail, Vercel and the public certificate check,
# and are refused unless every host spec is a local stand-in (127.0.0.1:<port>, test/hosts/standin).
#   static-ip  Lightsail is a file (build/stub/lightsail): the static IP "moves" by changing its owner, instance
#              addresses are the stand-ins' SSH specs and container IPs, and the "public URL" goes to the
#              owner's container (curl --resolve).
#   vercel     luct's production env is a file (build/stub/vercel.env); a redeploy is a log line.
#   cert       the public HTTPS check trusts the new stand-in's Caddy local CA (a .localhost site) and accepts the
#              old stand-in's self-signed certificate (-k); real runs use the system trust store, no -k.

source "$FIVEDAYS_ROOT/bin/deploy"

# CUTOVER_STATE_DIR (local stand-ins only, test/dry-run) keeps a dry run's cutover.state, cutover.done,
# rehearsal.ok and work files away from the real ones (luct's deploy reads fivedays/cutover.done).
if [[ -n "${CUTOVER_STATE_DIR:-}" ]]; then
  STATE_HOME="$CUTOVER_STATE_DIR"
  WORK_HOME="$CUTOVER_STATE_DIR"
else
  STATE_HOME="$FIVEDAYS_ROOT"
  WORK_HOME="$FIVEDAYS_ROOT/build"
fi
CUTOVER_STATE="$STATE_HOME/cutover.state"
# shellcheck disable=SC2034 # used by the scripts that source this file
CUTOVER_DONE="$STATE_HOME/cutover.done"
# shellcheck disable=SC2034
REHEARSAL_OK="$STATE_HOME/rehearsal.ok"
STUB_DIR="$WORK_HOME/stub"
LUCT_DIR="${LUCT_DIR:-$HOME/dev/luct}"
KEY_MARKER=fivedays-cutover-ephemeral
STUBS=""

set -a
# shellcheck disable=SC1091
source "$FIVEDAYS_ROOT/versions.env"
set +a

# ---- environment ----

# cl_load_env: checks the env file (ENV_FILE, default .env.prod) and reads the settings the cutover needs.
cl_load_env() {
  (load_env "$ENV_FILE" >/dev/null) || exit 1
  WWWROOT="$(env_value MOODLE_WWWROOT)"
  WWWROOT="${WWWROOT//\"/}"
  DOMAIN="${WWWROOT#*://}"
  DOMAIN="${DOMAIN%%/*}"
  [[ "$WWWROOT" == https://* && -n "$DOMAIN" ]] || die "MOODLE_WWWROOT in $ENV_FILE is \"$WWWROOT\", not an https URL" "Write the env file with just init prod."
  IMAGE_NAME="$(env_value IMAGE)"
  IMAGE_NAME="${IMAGE_NAME:-$DEFAULT_IMAGE}"
  OLD_INSTANCE="$(env_value CUTOVER_OLD_INSTANCE)"
  OLD_INSTANCE="${OLD_INSTANCE:-moodle}"
  NEW_INSTANCE="$(env_value CUTOVER_NEW_INSTANCE)"
  NEW_INSTANCE="${NEW_INSTANCE:-fivedays-moodle}"
}

# cl_overlay_env <out> KEY=VALUE...: the env file with these keys replaced (mode 600), for deploy_write_files.
cl_overlay_env() {
  local out="$1" kv keys=()
  shift
  for kv in "$@"; do keys+=("${kv%%=*}"); done
  local pattern
  pattern="$(IFS='|'; printf '%s' "${keys[*]}")"
  (umask 077 && { grep -Ev "^[[:space:]]*(export[[:space:]]+)?($pattern)=" "$ENV_FILE" || true; printf '%s\n' "$@"; } >"$out")
  chmod 600 "$out"
}

# ---- stubs ----

# cl_stubs_init <host specs...>: reads CUTOVER_STUBS; refuses stubs unless every spec is 127.0.0.1:<port>.
cl_stubs_init() {
  local s spec
  if [[ -n "${CUTOVER_STATE_DIR:-}" ]]; then
    all_local "$@" || die "CUTOVER_STATE_DIR is set for real hosts" "It is for the local dry run only; unset it."
    mkdir -p "$CUTOVER_STATE_DIR"
  fi
  STUBS="${CUTOVER_STUBS:-}"
  [[ -n "$STUBS" ]] || return 0
  for s in ${STUBS//,/ }; do
    [[ "$s" =~ ^(static-ip|vercel|cert)$ ]] || die "Unknown stub \"$s\" in CUTOVER_STUBS" "Use static-ip, vercel and/or cert."
  done
  for spec in "$@"; do
    [[ "$spec" =~ ^127\.0\.0\.1:[0-9]+$ ]] ||
      die "CUTOVER_STUBS=$STUBS with host $spec: stubs are only for the local stand-ins (127.0.0.1:<port>)" "Unset CUTOVER_STUBS for real hosts."
  done
  warn "TEST STUBS ON: $STUBS (local stand-ins only)"
}
stub_on() { [[ ",$STUBS," == *",$1,"* ]]; }
all_local() {
  local spec
  for spec in "$@"; do [[ "$spec" =~ ^127\.0\.0\.1:[0-9]+$ ]] || return 1; done
}

# ---- run directory and SSH ----

# cl_ssh_init <run dir> <fresh 0|1>: own known_hosts in the run dir (reset when fresh).
cl_ssh_init() {
  RUN_DIR="$1"
  mkdir -p "$RUN_DIR"
  chmod 700 "$RUN_DIR"
  KNOWN_HOSTS="$RUN_DIR/known_hosts"
  if [[ "$2" == 1 ]]; then rm -f "$KNOWN_HOSTS"; fi
  touch "$KNOWN_HOSTS"
  SSH_OPTS="-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=$KNOWN_HOSTS -o HashKnownHosts=no -o LogLevel=ERROR"
  export SSH_OPTS SSH_KEY
  need_cmd ssh
  need_ssh_key
}

# cl_hosts <old ssh spec> <new ssh spec>: the current SSH address of each machine; host keys are pinned to the
# machine (fivedays-old / fivedays-new), not to the address.
cl_hosts() {
  OLD_SSH="$1"
  NEW_SSH="$2"
  SSH_HOST_ALIASES="$OLD_SSH=fivedays-old $NEW_SSH=fivedays-new"
  export SSH_HOST_ALIASES
}

old_run() { on_host_script "$OLD_SSH" "$FIVEDAYS_ROOT/host/cutover-old.sh" "$@"; }
new_run() { on_host_script "$NEW_SSH" "$FIVEDAYS_ROOT/host/cutover-new.sh" "$@"; }
kv() { sed -n "s/^$1=//p" <<<"$2" | tail -n1; }

# ---- state (cutover.state) ----

st_get() { [[ -f "$CUTOVER_STATE" ]] && sed -n "s/^$1=//p" "$CUTOVER_STATE" | tail -n1 || true; }
st_set() {
  local tmp="$CUTOVER_STATE.tmp"
  { grep -v "^$1=" "$CUTOVER_STATE" 2>/dev/null || true; printf '%s=%s\n' "$1" "$2"; } >"$tmp"
  mv "$tmp" "$CUTOVER_STATE"
}

# ---- report notes (warnings and failed non-fatal checks, one line each) ----

note_warn() { warn "$1"; printf '%s\n' "$1" >>"$RUN_DIR/warnings"; }
note_nonfatal() { warn "NON-FATAL CHECK FAILED: $1"; printf '%s\n' "$1" >>"$RUN_DIR/nonfatal"; }

# ---- Lightsail ----

aws_ls() {
  AWS_ACCESS_KEY_ID="$(env_value CUTOVER_AWS_ACCESS_KEY_ID)" AWS_SECRET_ACCESS_KEY="$(env_value CUTOVER_AWS_SECRET_ACCESS_KEY)" \
    docker run --rm -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION=eu-central-1 \
    "amazon/aws-cli:$AWSCLI_VERSION" lightsail "$@"
}
stub_ls_get() { sed -n "s/^$1=//p" "$STUB_DIR/lightsail" | tail -n1; }
stub_ls_set() {
  { grep -v "^$1=" "$STUB_DIR/lightsail" 2>/dev/null || true; printf '%s=%s\n' "$1" "$2"; } >"$STUB_DIR/lightsail.tmp"
  mv "$STUB_DIR/lightsail.tmp" "$STUB_DIR/lightsail"
}
# The stand-in container behind a 127.0.0.1:<port> SSH spec, and its IP on the stand-in network.
standin_container() { docker ps --filter "publish=${1##*:}" --format '{{.Names}}' | head -n1; }
standin_ip() { docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$(standin_container "$1")"; }

# stub_ls_init <old spec> <new spec>: the stub "world" (once; it survives runs like Lightsail does).
stub_ls_init() {
  mkdir -p "$STUB_DIR"
  [[ -f "$STUB_DIR/lightsail" ]] && return 0
  printf 'static_name=StaticIp-luct\nowner=%s\n%s_pub=%s\n%s_priv=%s\n%s_pub=%s\n%s_priv=%s\n' "$OLD_INSTANCE" \
    "$OLD_INSTANCE" "$1" "$OLD_INSTANCE" "$(standin_ip "$1")" "$NEW_INSTANCE" "$2" "$NEW_INSTANCE" "$(standin_ip "$2")" >"$STUB_DIR/lightsail"
  ok "stub Lightsail world written: $STUB_DIR/lightsail (static IP on $OLD_INSTANCE)"
}

# ls_static_ip <instance>: "<name> <ip>" of the static IP attached to that instance (nothing if none).
ls_static_ip() {
  if stub_on static-ip; then
    [[ "$(stub_ls_get owner)" == "$1" ]] && printf '%s %s\n' "$(stub_ls_get static_name)" "stub:$(stub_ls_get static_name)"
    return 0
  fi
  aws_ls get-static-ips --query "staticIps[?attachedTo=='$1'].[name, ipAddress]" --output text
}
# ls_instance <instance>: "<public> <private>" address (stub: SSH spec and container IP).
ls_instance() {
  if stub_on static-ip; then printf '%s %s\n' "$(stub_ls_get "$1_pub")" "$(stub_ls_get "$1_priv")"; return 0; fi
  aws_ls get-instance --instance-name "$1" --query 'instance.[publicIpAddress, privateIpAddress]' --output text
}
# ls_bucket_versioning <bucket>: Enabled | Suspended | NeverEnabled. Asked of the Lightsail API with the cutover
# key, because Lightsail bucket access keys are object-only and get AccessDenied on s3:GetBucketVersioning.
ls_bucket_versioning() {
  if stub_on static-ip; then echo Enabled; return 0; fi
  aws_ls get-buckets --bucket-name "$1" --query 'buckets[0].objectVersioning' --output text
}
# ls_move_static_ip <name> <to instance>: detach, attach, and wait until Lightsail reports it attached.
ls_move_static_ip() {
  local name="$1" to="$2" state
  if stub_on static-ip; then
    stub_ls_set owner ""
    stub_ls_set owner "$to"
    ok "stub: static IP $name now on $to"
    return 0
  fi
  aws_ls detach-static-ip --static-ip-name "$name" >/dev/null || true
  aws_ls attach-static-ip --static-ip-name "$name" --instance-name "$to" >/dev/null ||
    die "Attaching static IP $name to $to failed" "Attach it in the Lightsail console (Networking), then rerun."
  for _ in $(seq 1 60); do
    state="$(aws_ls get-static-ip --static-ip-name "$name" --query 'staticIp.[attachedTo, isAttached]' --output text)"
    [[ "$state" == "$to"*True* ]] && { ok "static IP $name attached to $to"; return 0; }
    sleep 5
  done
  die "Static IP $name is not attached to $to after 5 minutes (state: $state)" "Check it in the Lightsail console."
}
# ls_wait_public <instance> <not this address>: waits for the instance's public address after a move.
ls_wait_public() {
  local inst="$1" not="$2" pub priv
  for _ in $(seq 1 60); do
    read -r pub priv < <(ls_instance "$inst")
    if [[ -n "$pub" && "$pub" != None && "$pub" != "$not" ]] || stub_on static-ip; then printf '%s %s\n' "$pub" "$priv"; return 0; fi
    sleep 5
  done
  die "$inst has no new public address after 5 minutes" "Check the instance in the Lightsail console."
}

# The address the public name currently reaches (stub: the static-IP owner's container).
public_target_ip() {
  if stub_on static-ip; then stub_ls_get "$(stub_ls_get owner)_priv"; return 0; fi
  getent ahostsv4 "$DOMAIN" | awk 'NR == 1 { print $1 }'
}

# public_curl <path> [curl args...]: HTTPS to the public name, exactly as a browser or luct reaches it.
public_curl() {
  local path="$1" extra=()
  shift
  if stub_on static-ip; then extra+=(--resolve "$DOMAIN:443:$(public_target_ip)"); fi
  if stub_on cert; then
    # The new stand-in's certificate must verify against its Caddy local CA (no -k): until that root is fetched
    # the check fails like a missing certificate would. Only the old stand-in's self-signed one gets -k.
    if [[ "$(stub_ls_get owner 2>/dev/null)" == "$NEW_INSTANCE" ]]; then
      extra+=(--cacert "$RUN_DIR/stub-caddy-root.crt")
    else
      extra+=(-k)
    fi
  fi
  curl -s -m 60 "${extra[@]}" "$@" "https://$DOMAIN$path"
}
public_status() { public_curl "$1" -o /dev/null -w '%{http_code}' || true; }
# public_server: who answered the login page: nginx (old host) from its Server header, or "Caddy" (new host).
# Caddy's reverse proxy (php_fastcgi) drops its own Server header from proxied responses and sends "Via: 1.1 Caddy".
public_server() {
  local headers server
  headers="$(public_curl /login/index.php -o /dev/null -D - | tr -d '\r' || true)"
  server="$(sed -n 's/^[Ss]erver: //p' <<<"$headers" | head -n1)"
  if [[ -z "$server" ]] && grep -qiE '^via: .*caddy' <<<"$headers"; then server="Caddy (Via header)"; fi
  printf '%s' "$server"
}
# public_site_info <token file>: userid from core_webservice_get_site_info over the public URL (empty on failure).
public_site_info() {
  public_curl /webservice/rest/server.php --data-urlencode "wstoken@$1" -d wsfunction=core_webservice_get_site_info \
    -d moodlewsrestformat=json | grep -o '"userid":[0-9]*' | head -n1 | cut -d: -f2 || true
}

# ---- Vercel (luct production env) ----

vercel_cli() { (cd "$LUCT_DIR" && vercel "$@"); }
# vercel_check: CLI logged in and luct linked.
vercel_check() {
  if stub_on vercel; then [[ -f "$STUB_DIR/vercel.env" ]] || die "Stub Vercel env $STUB_DIR/vercel.env missing" "The dry-run driver writes it."; ok "stub: Vercel"; return 0; fi
  need_cmd vercel "Install the Vercel CLI (luct uses it)."
  [[ -f "$LUCT_DIR/.vercel/project.json" ]] || die "$LUCT_DIR/.vercel/project.json missing" "Run vercel link in luct."
  vercel_cli whoami >/dev/null 2>&1 || die "The Vercel CLI is not logged in" "Run: vercel login"
  ok "Vercel CLI logged in; luct linked ($(grep -o '"projectName": *"[^"]*"' "$LUCT_DIR/.vercel/project.json" | cut -d'"' -f4))"
}
# vercel_env_get <KEY> <out file>: writes the production value (mode 600); empty file if unset or unreadable.
vercel_env_get() {
  local key="$1" out="$2" tmp
  (umask 077 && : >"$out")
  if stub_on vercel; then sed -n "s/^$key=//p" "$STUB_DIR/vercel.env" | tail -n1 | tr -d '\n' >"$out"; return 0; fi
  tmp="$(mktemp -d "$RUN_DIR/vercel-pull.XXXXXX")"
  # The pulled file holds every production secret: removed on any exit of this subshell, interrupts included.
  (
    trap 'rm -rf "$tmp"' EXIT
    trap 'exit 130' INT TERM HUP
    vercel_cli env pull "$tmp/env" --environment=production --yes >/dev/null 2>&1 || die "vercel env pull failed" "Check: vercel whoami, and that luct is linked."
    sed -n "s/^$key=//p" "$tmp/env" | tail -n1 | sed 's/^"\(.*\)"$/\1/' | tr -d '\n' >"$out"
  )
}
# vercel_env_set <KEY> <value file>: sets the production value (from the file, never on a command line).
vercel_env_set() {
  local key="$1" file="$2"
  if stub_on vercel; then
    { grep -v "^$key=" "$STUB_DIR/vercel.env" || true; printf '%s=%s\n' "$key" "$(cat "$file")"; } >"$STUB_DIR/vercel.env.tmp"
    mv "$STUB_DIR/vercel.env.tmp" "$STUB_DIR/vercel.env"
    # Non-secret keys only, for the dry run's evidence of when sync was paused and resumed.
    [[ "$key" != LMS_SYNC_PAUSED ]] || printf '%s LMS_SYNC_PAUSED=%s\n' "$(date +%H:%M:%S)" "$(cat "$file")" >>"$STUB_DIR/vercel.history"
    ok "stub: Vercel production $key set"
    return 0
  fi
  vercel_cli env add "$key" production --force --yes <"$file" >/dev/null || die "vercel env add $key failed" "Set $key in the Vercel dashboard (production), then rerun."
  ok "Vercel production $key set"
}
# vercel_redeploy: rebuilds the newest READY production deployment so it picks up the env change.
vercel_redeploy() {
  local url
  if stub_on vercel; then printf '%s redeploy\n' "$(date -u +%FT%TZ)" >>"$STUB_DIR/vercel.redeploys"; ok "stub: Vercel production redeployed"; return 0; fi
  url="$(vercel_cli ls --environment production --status READY --json --limit 1 2>/dev/null | grep -o '"url": *"[^"]*"' | head -n1 | cut -d'"' -f4)"
  [[ -n "$url" ]] || die "No READY production deployment found with vercel ls" "Redeploy luct from the Vercel dashboard."
  log "redeploying $url (production)"
  vercel_cli redeploy "$url" --environment production >/dev/null || die "vercel redeploy $url failed" "Redeploy luct from the Vercel dashboard."
  ok "Vercel production redeployed from $url"
}
# lms_sync_pause 1|0 (D34): LMS_SYNC_PAUSED in luct, then redeploy.
lms_sync_pause() {
  local f="$RUN_DIR/lms-paused.value"
  printf '%s' "$1" >"$f"
  vercel_env_set LMS_SYNC_PAUSED "$f"
  rm -f "$f"
  vercel_redeploy
}

# ---- ephemeral host-to-host key ----

# ekey_install <old priv> <old pub> <new priv> <new pub>: a fresh ed25519 key; the public half authorised on
# the old host for ubuntu, only from the new host's addresses; the private half root-only on the new host,
# with the old host's pinned host key. Then the new host connects (private address first).
ekey_install() {
  local old_priv="$1" old_pub="$2" new_priv="$3" new_pub="$4" from addr hostkeys out
  local key="$RUN_DIR/ephemeral_ed25519"
  rm -f "$key" "$key.pub"
  ssh-keygen -q -t ed25519 -N '' -C "$KEY_MARKER run=$(date -u +%Y%m%dT%H%M%SZ)" -f "$key"
  from="$new_priv"
  [[ "$new_pub" =~ ^[0-9.]+$ ]] && from="$from,$new_pub"
  old_run key-add "$KEY_MARKER" "from=\"$from\",restrict,port-forwarding $(cat "$key.pub")" >/dev/null ||
    die "Could not authorise the ephemeral key on the old host" "Read the error above; nothing was copied yet."
  # The old host's key as this laptop pinned it (alias fivedays-old), for each address the new host may use.
  hostkeys="$(awk '$1 == "fivedays-old" { $1 = ""; print substr($0, 2) }' "$KNOWN_HOSTS")"
  [[ -n "$hostkeys" ]] || die "No pinned host key for the old host in $KNOWN_HOSTS" "This is a bug in bin/cutover-lib.sh."
  on_host "$NEW_SSH" "install -d -m 700 -o root -g root $HOST_DIR/cutover"
  copy_to_host "$NEW_SSH" "$key" "$HOST_DIR/cutover/id_ed25519" 600
  for addr in "$old_priv" "${old_pub%%:*}"; do
    [[ -n "$addr" ]] && sed "s/^/$addr /" <<<"$hostkeys"
  done >"$RUN_DIR/old_known_hosts"
  copy_to_host "$NEW_SSH" "$RUN_DIR/old_known_hosts" "$HOST_DIR/cutover/known_hosts" 600
  rm -f "$key" "$RUN_DIR/old_known_hosts"
  local addrs=("$old_priv")
  [[ "${old_pub%%:*}" =~ ^[0-9.]+$ && "${old_pub%%:*}" != 127.* ]] && addrs+=("${old_pub%%:*}")
  out="$(new_run old-connect "${addrs[@]}")" || die "The new host cannot reach the old host with the ephemeral key" "Check port 22 between the hosts (Lightsail firewall)."
  ok "ephemeral key: new host → old host at $(kv old_addr "$out") (private address first)"
}
# ekey_remove: removes the key from both hosts (whichever answer) and checks it is gone. Returns 1 if not.
ekey_remove() {
  local bad=0 out
  rm -f "$RUN_DIR/ephemeral_ed25519" "$RUN_DIR/ephemeral_ed25519.pub"
  out="$(old_run key-remove "$KEY_MARKER" 2>&1)" || true
  if [[ "$(kv remaining "$out")" == 0 ]]; then ok "ephemeral key removed from the old host"; else warn "ephemeral key NOT confirmed gone from the old host: $out"; bad=1; fi
  out="$(new_run key-remove 2>&1)" || true
  if [[ "$(kv remaining "$out")" == 0 ]]; then ok "ephemeral key removed from the new host"; else warn "ephemeral key NOT confirmed gone from the new host: $out"; bad=1; fi
  return "$bad"
}

# copy_contract: docker/contract → $HOST_DIR/contract on the new host (contract test, cleanup).
copy_contract() {
  tar -C "$FIVEDAYS_ROOT/docker" -cf - contract |
    on_host "$NEW_SSH" "rm -rf $HOST_DIR/contract && tar -C $HOST_DIR --no-same-owner -xf - && chown -R root:root $HOST_DIR/contract && chmod -R a+rX,go-w $HOST_DIR/contract"
}

# fmt_secs <seconds>: 1h 02m 03s.
fmt_secs() { printf '%dh %02dm %02ds' $(($1 / 3600)) $(($1 % 3600 / 60)) $(($1 % 60)); }

# installxml_check <old host name> <transfer image>: dbtransfer refuses unless the code's schema equals the
# source's (public/lib/dtl/database_exporter.php:137), so the -transfer image's two plugin install.xml files must
# have the sha256 the pre-check recorded on the old host (section "Schema files of installed plugins") and the
# old host has now. PRECHECK_REPORT=<file> replaces the saved report, for local stand-ins only.
installxml_check() {
  local name="$1" image="$2" report expected image_sums old_sums
  if [[ -n "${PRECHECK_REPORT:-}" ]]; then
    all_local "$OLD_SSH" "$NEW_SSH" || die "PRECHECK_REPORT is set but the hosts are not local stand-ins" "Unset PRECHECK_REPORT for real hosts."
    report="$PRECHECK_REPORT"
  else
    report="$(find "$FIVEDAYS_ROOT/precheck" -maxdepth 1 -name "$name-*.md" 2>/dev/null | sort | tail -n1)"
  fi
  [[ -n "$report" && -f "$report" ]] || die "No pre-check report for $name in precheck/" "Run: just precheck $name"
  expected="$(awk '/^## /{ on = ($0 ~ /^## Schema files of installed plugins/) } on && /^[0-9a-f]{64}  /{ printf "installxml %s %s\n", $2, $1 }' "$report" | sort)"
  [[ "$(wc -l <<<"$expected")" == 2 && -n "$expected" ]] ||
    die "$report has no \"Schema files of installed plugins\" section with both install.xml hashes" "Run: just precheck $name (the section was added after that report was saved)."
  image_sums="$(new_run installxml "$image" | sort)"
  old_sums="$(old_run inspect | grep '^installxml ' | sort)"
  if [[ "$image_sums" != "$expected" ]]; then
    printf 'pre-check %s:\n%s\nimage %s:\n%s\n' "$report" "$expected" "$image" "$image_sums"
    die "The -transfer image's plugin install.xml files differ from the old host's: dbtransfer would refuse" "Build the image from plugins whose db/install.xml matches prod, or rerun just precheck if prod changed."
  fi
  [[ "$old_sums" == "$expected" ]] || die "The old host's install.xml files changed since the pre-check ($report)" "Rerun just precheck $name and check the plugins on the old host."
  ok "install.xml sha256 of both plugins: image = pre-check ($(basename "$report")) = old host now"
}

# new_is_public_target <new spec>: true when the public name reaches the new host (never wipe it then).
new_is_public_target() {
  if stub_on static-ip; then [[ "$(stub_ls_get owner)" == "$NEW_INSTANCE" ]]; return; fi
  local target new_ips
  target="$(public_target_ip)"
  new_ips="$(getent ahostsv4 "$(host_name "$1")" | awk '{ print $1 }' | sort -u)"
  [[ -n "$target" ]] && grep -qx "$target" <<<"$new_ips"
}
