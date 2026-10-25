#!/usr/bin/env bash
# Runs as root ON THE HOST: bin/provision pipes it through `sudo bash -s` (never run it on the laptop).
# Prepares a bare Ubuntu 24.04 Lightsail host for the stack. Safe to rerun: every item prints "ok"
# (already right, nothing touched) or "changed" (fixed now), so a second run prints only "ok".
#
# Items: OS check, old-prod guard, Docker Engine + compose plugin (Docker's apt repo), Docker log
# defaults, ufw (SSH, 80, 443), unattended-upgrades, swap + swappiness (D22, 4 GB host), /opt/fivedays, the
# external volumes, FPM_MAX_CHILDREN (stored in /opt/fivedays/provision.env for bin/deploy), and the
# marker /opt/fivedays/.provisioned (written last, so a failed run leaves no marker).
set -euo pipefail
# errexit stops silently; name the command so a stop is never without a FAIL line.
trap 'echo "FAIL     line $LINENO exited $?: $BASH_COMMAND"' ERR
export DEBIAN_FRONTEND=noninteractive

DIR=/opt/fivedays
VOLUMES=(fivedays_moodledata fivedays_pgdata fivedays_caddydata fivedays_backups)
DOCKER_PACKAGES=(docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin)
# Docker's release signing key (https://docs.docker.com/engine/install/ubuntu/).
DOCKER_KEY_FINGERPRINT=9DC858229FC7DD38854AE2D88D81803C0EBFCD88
SWAP_FILE=/swapfile
# D22: 4 GB RAM. 2 GB of swap absorbs a burst of large PHP requests or the one-off MariaDB of the
# transfer/rehearsal instead of the OOM killer picking Postgres; swappiness 10 keeps the working set in
# RAM until it is really needed.
SWAP_MB=2048
SWAPPINESS=10

changes=0
apt_updated=0
apt_log="$(mktemp)"
trap 'rm -f "$apt_log"' EXIT

ok() { printf 'ok       %s\n' "$*"; }
changed() { printf 'changed  %s\n' "$*"; changes=$((changes + 1)); }
fail() {
  printf 'FAIL     %s\n' "$1"
  if [[ -n "${2:-}" ]]; then printf '         What to do: %s\n' "$2"; fi
  exit 1
}

# write_if_changed <path> <mode> <content>: returns 0 when it wrote, 1 when the file was already right.
write_if_changed() {
  local path="$1" mode="$2" content="$3"
  if [[ -f "$path" ]] && [[ "$(cat "$path")" == "$content" ]] && [[ "$(stat -c %a "$path")" == "$mode" ]]; then
    return 1
  fi
  install -D -m "$mode" -o root -g root /dev/null "$path"
  printf '%s\n' "$content" >"$path"
  return 0
}

installed() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q 'install ok installed'; }

# apt_install <pkg...>: installs the missing ones. Returns 0 when it installed something, 1 when all were there.
apt_install() {
  local missing=() p
  for p in "$@"; do installed "$p" || missing+=("$p"); done
  ((${#missing[@]})) || return 1
  if ((!apt_updated)); then
    apt-get update -q >"$apt_log" 2>&1 || { tail -n 20 "$apt_log"; fail "apt-get update failed (output above)" "Check the host's internet access and rerun."; }
    apt_updated=1
  fi
  apt-get install -y -q --no-install-recommends "${missing[@]}" >>"$apt_log" 2>&1 ||
    { tail -n 20 "$apt_log"; fail "apt-get install ${missing[*]} failed (output above)" "Fix the apt error above and rerun."; }
  return 0
}

# Kernel-wide settings (swap, vm.*) belong to the machine. In the test stand-in (a privileged container)
# they would change the laptop's kernel, so they are written to disk but not applied there.
in_container=0
if systemd-detect-virt --container --quiet 2>/dev/null; then in_container=1; fi

# ---- 1. OS and old-prod guard ----
# shellcheck disable=SC1091
source /etc/os-release
[[ "${ID:-}" == "ubuntu" && "${VERSION_ID:-}" == "24.04" ]] ||
  fail "This host runs ${PRETTY_NAME:-an unknown OS}, not Ubuntu 24.04" "Create the Lightsail instance from the Ubuntu 24.04 image (D22)."
ok "os: $PRETTY_NAME"
# The old prod host is Ubuntu 24.04 too, but runs Moodle on MariaDB + nginx outside Docker. Provisioning it
# would enable a firewall and install Docker under live prod, so refuse.
for p in mariadb-server mysql-server nginx apache2; do
  ! installed "$p" || fail "$p is installed: this looks like the old prod host, not a new bare host" \
    "Provision only the new Lightsail instance (D22). The old host is changed only by just cutover."
done
ok "not the old prod host (no MariaDB/MySQL/nginx/Apache packages)"

# ---- 2. Docker Engine + compose plugin from Docker's apt repo ----
for p in docker.io docker-compose podman-docker containerd runc; do
  ! installed "$p" || fail "Ubuntu's $p package is installed; it conflicts with Docker's packages" "Remove it (apt-get remove $p) and rerun."
done
# rsync and the ssh client: the cutover pulls moodledata and tunnels to the old MariaDB from this host.
if apt_install ca-certificates curl gnupg rsync openssh-client; then changed "packages: ca-certificates curl gnupg rsync openssh-client"; else ok "packages: ca-certificates curl gnupg rsync openssh-client"; fi

key=/etc/apt/keyrings/docker.asc
if [[ -s "$key" ]]; then
  ok "docker apt key"
else
  install -d -m 755 /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o "$key.tmp" || fail "Downloading Docker's apt key failed" "Check the host's internet access and rerun."
  chmod 644 "$key.tmp"
  mv "$key.tmp" "$key"
  changed "docker apt key"
fi
gpg --show-keys --with-colons "$key" 2>/dev/null | grep -q "^fpr:::::::::$DOCKER_KEY_FINGERPRINT:" ||
  fail "$key is not Docker's release key ($DOCKER_KEY_FINGERPRINT)" "Delete $key and rerun; if it fails again, stop and ask."
ok "docker apt key fingerprint $DOCKER_KEY_FINGERPRINT"

repo="Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: $key"
if write_if_changed /etc/apt/sources.list.d/docker.sources 644 "$repo"; then apt_updated=0; changed "docker apt repo"; else ok "docker apt repo"; fi

# Log defaults for containers started without their own logging options (compose.yaml sets the same
# limits per service). Written before the install so a fresh dockerd starts with it.
daemon_json='{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "20m",
    "max-file": "5"
  }
}'
daemon_changed=0
if write_if_changed /etc/docker/daemon.json 644 "$daemon_json"; then daemon_changed=1; fi

if apt_install "${DOCKER_PACKAGES[@]}"; then changed "docker: ${DOCKER_PACKAGES[*]}"; else ok "docker: ${DOCKER_PACKAGES[*]}"; fi
if systemctl is-enabled --quiet docker && systemctl is-active --quiet docker; then
  if ((daemon_changed)); then
    # A changed daemon.json on a running host needs a restart; the stack comes back by its restart policies.
    systemctl restart docker
    changed "docker log defaults (/etc/docker/daemon.json, docker restarted)"
  else
    ok "docker log defaults (/etc/docker/daemon.json)"
  fi
  ok "docker service enabled and running"
else
  systemctl enable --now docker >/dev/null 2>&1 || fail "Could not start the docker service" "Read: journalctl -u docker on the host (via just commands only), then rerun."
  if ((daemon_changed)); then systemctl restart docker; changed "docker log defaults (/etc/docker/daemon.json)"; fi
  changed "docker service enabled and running"
fi
docker compose version >/dev/null 2>&1 || fail "docker compose does not run" "Rerun; if it fails again, stop and ask."
ok "docker $(docker version -f '{{.Server.Version}}' 2>/dev/null), compose $(docker compose version --short)"

# ---- 3. Firewall: SSH first, then 80/443, then enable ----
# The SSH rule goes in before ufw is enabled and is checked, so enabling cannot cut this session.
# ufw keeps established connections (its before.rules accept RELATED,ESTABLISHED).
# Docker-published ports bypass ufw; compose publishes only 80/443 publicly and 8080 on 127.0.0.1.
if apt_install ufw; then changed "packages: ufw"; else ok "packages: ufw"; fi
# awk reads to the end: an early exit can kill sshd with SIGPIPE, which pipefail turns into a silent stop.
ssh_port="$(sshd -T 2>/dev/null | awk '$1 == "port" && !p { print $2; p = 1 }')"
ssh_port="${ssh_port:-22}"
for rule in "$ssh_port/tcp" 80/tcp 443/tcp 443/udp; do
  out="$(ufw allow "$rule")"
  if grep -qv '^Skipping' <<<"$out"; then changed "ufw allow $rule"; else ok "ufw allow $rule"; fi
done
ufw show added | grep -qx "ufw allow $ssh_port/tcp" || fail "The ufw rule for SSH port $ssh_port is missing; not enabling ufw" "Rerun; if it fails again, stop and ask."
if ufw status | grep -q '^Status: active'; then
  ok "ufw enabled (incoming: SSH $ssh_port, 80, 443 only)"
else
  ufw --force enable >/dev/null
  ufw status | grep -q '^Status: active' || fail "ufw did not become active" "Rerun; if it fails again, stop and ask."
  changed "ufw enabled (incoming: SSH $ssh_port, 80, 443 only)"
fi

# ---- 4. unattended-upgrades (Ubuntu security updates; no automatic reboot) ----
if apt_install unattended-upgrades; then changed "packages: unattended-upgrades"; else ok "packages: unattended-upgrades"; fi
auto='APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";'
if write_if_changed /etc/apt/apt.conf.d/20auto-upgrades 644 "$auto"; then changed "unattended-upgrades enabled (20auto-upgrades)"; else ok "unattended-upgrades enabled (20auto-upgrades)"; fi
if systemctl is-enabled --quiet unattended-upgrades; then
  ok "unattended-upgrades service enabled"
else
  systemctl enable --now unattended-upgrades >/dev/null 2>&1
  changed "unattended-upgrades service enabled"
fi

# ---- 5. Swap and swappiness ----
# /proc/swaps in the test container lists the laptop's swap, so it only counts on a real host.
other_swap=""
if ((!in_container)); then
  other_swap="$(awk 'NR > 1 && $1 != "'"$SWAP_FILE"'" { print $1 }' /proc/swaps | paste -sd ' ')"
fi
if [[ -f "$SWAP_FILE" ]]; then
  ok "swap file $SWAP_FILE ($(($(stat -c %s "$SWAP_FILE") / 1048576)) MB)"
elif [[ -n "$other_swap" ]]; then
  ok "swap: the host already has swap ($other_swap), $SWAP_FILE not added"
else
  fallocate -l "${SWAP_MB}M" "$SWAP_FILE"
  chmod 600 "$SWAP_FILE"
  mkswap "$SWAP_FILE" >/dev/null
  changed "swap file $SWAP_FILE ($SWAP_MB MB)"
fi
if [[ -f "$SWAP_FILE" ]]; then
  if grep -qE "^${SWAP_FILE}[[:space:]]+none[[:space:]]+swap" /etc/fstab; then
    ok "swap file in /etc/fstab"
  else
    printf '%s none swap sw 0 0\n' "$SWAP_FILE" >>/etc/fstab
    changed "swap file in /etc/fstab"
  fi
  if ((in_container)); then
    ok "swap on: not applied (test container; the kernel is the laptop's)"
  elif swapon --show=NAME --noheadings | grep -qx "$SWAP_FILE"; then
    ok "swap on"
  else
    swapon "$SWAP_FILE"
    changed "swap on"
  fi
fi
if write_if_changed /etc/sysctl.d/60-fivedays.conf 644 "vm.swappiness = $SWAPPINESS"; then changed "vm.swappiness = $SWAPPINESS in /etc/sysctl.d/60-fivedays.conf"; else ok "vm.swappiness = $SWAPPINESS in /etc/sysctl.d/60-fivedays.conf"; fi
if ((in_container)); then
  ok "vm.swappiness live: not applied (test container; the kernel is the laptop's)"
elif [[ "$(cat /proc/sys/vm/swappiness)" == "$SWAPPINESS" ]]; then
  ok "vm.swappiness live = $SWAPPINESS"
else
  sysctl -q -w "vm.swappiness=$SWAPPINESS"
  changed "vm.swappiness live = $SWAPPINESS"
fi

# ---- 6. /opt/fivedays and the external volumes ----
if [[ -d "$DIR" && "$(stat -c '%U:%G %a' "$DIR")" == "root:root 700" ]]; then
  ok "$DIR (root, 700)"
else
  install -d -m 700 -o root -g root "$DIR"
  chmod 700 "$DIR"
  changed "$DIR (root, 700)"
fi
for v in "${VOLUMES[@]}"; do
  if docker volume inspect "$v" >/dev/null 2>&1; then ok "volume $v"; else docker volume create "$v" >/dev/null; changed "volume $v"; fi
done

# ---- 7. FPM_MAX_CHILDREN for this host's RAM ----
# fpm_children <MemTotal MB>. The new host is a 4 GB Lightsail instance (D22, 2 vCPU). Reserved first, in MB:
#   OS, sshd, dockerd/containerd  384
#   Postgres                      768  (shared_buffers 128 MB + ~10 MB per connection: one per worker + cron)
#   Redis                         288  (maxmemory 256 MB in compose.yaml + overhead)
#   Caddy                         128
#   cron (one PHP CLI process)    192
#   one-off MariaDB (transfer and rehearsal only) 160; it runs while the site is idle, swap covers the rest
#   total                        1920
# Each php-fpm worker averages ~80 MB with Moodle (docker/php-fpm.conf); a rare 256 MB request
# (memory_limit) is absorbed by the 2 GB swap. 4 GB host: MemTotal ~3,900 MB → (3900 - 1920) / 80 = 24,
# plenty for 2 vCPUs since most request time is I/O. Floor 5 (a 2 GB host): fewer workers queue every page
# behind one slow download. Cap 32: more workers than that only add DB connections and contention.
fpm_children() {
  local children=$((($1 - 1920) / 80))
  ((children >= 5)) || children=5
  ((children <= 32)) || children=32
  printf '%s' "$children"
}
mem_mb=$(($(awk '/^MemTotal:/ { print $2 }' /proc/meminfo) / 1024))
children="$(fpm_children "$mem_mb")"
penv="# Written by host/provision.sh for this host's RAM (${mem_mb} MB); read by bin/deploy.
FPM_MAX_CHILDREN=$children"
if write_if_changed "$DIR/provision.env" 600 "$penv"; then changed "FPM_MAX_CHILDREN=$children (RAM $mem_mb MB) in $DIR/provision.env"; else ok "FPM_MAX_CHILDREN=$children (RAM $mem_mb MB) in $DIR/provision.env"; fi

# ---- 8. Marker (last: only a run that got this far counts) ----
if [[ -f "$DIR/.provisioned" ]]; then
  ok "marker $DIR/.provisioned ($(head -n1 "$DIR/.provisioned"))"
else
  install -m 600 -o root -g root /dev/null "$DIR/.provisioned"
  printf 'provisioned %s by host/provision.sh\n' "$(date -u +%FT%TZ)" >"$DIR/.provisioned"
  changed "marker $DIR/.provisioned"
fi

printf '\nprovision done: %d changed\n' "$changes"
