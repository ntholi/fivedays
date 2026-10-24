# Shared helpers for bin/ scripts. Source it; don't run it.
# shellcheck shell=bash

FIVEDAYS_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export FIVEDAYS_ROOT

# Every script logs to logs/<command>-<time>.log as well as the terminal.
start_log() {
  local name="$1"
  mkdir -p "$FIVEDAYS_ROOT/logs"
  LOG_FILE="$FIVEDAYS_ROOT/logs/${name}-$(date +%Y%m%d-%H%M%S).log"
  export LOG_FILE
  exec > >(tee -a "$LOG_FILE") 2>&1
  log "log file: $LOG_FILE"
}

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }
ok() { printf '[%s] ok    %s\n' "$(date +%H:%M:%S)" "$*"; }
warn() { printf '[%s] WARN  %s\n' "$(date +%H:%M:%S)" "$*"; }

# die "<what happened>" "<what to do>"
die() {
  printf '\n[%s] FAIL  %s\n' "$(date +%H:%M:%S)" "$1" >&2
  if [[ -n "${2:-}" ]]; then printf '      What to do: %s\n' "$2" >&2; fi
  if [[ -n "${LOG_FILE:-}" ]]; then printf '      Log: %s\n' "$LOG_FILE" >&2; fi
  exit 1
}

# confirm "<word>": the user must type the exact word to continue.
confirm() {
  local word="$1" answer
  [[ -t 0 ]] || die "Confirmation needed but there is no terminal" "Run this command in an interactive terminal."
  printf 'Type "%s" to continue: ' "$word"
  read -r answer
  [[ "$answer" == "$word" ]] || die "Not confirmed (typed \"$answer\")" "Nothing was changed by this step. Rerun when ready."
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "\"$1\" is not installed" "${2:-Install it and rerun.}"
}

# Loads .env (mode 600 only) into the environment.
load_env() {
  local file="${1:-$FIVEDAYS_ROOT/.env}"
  [[ -f "$file" ]] || die "$file not found" "Run: just init"
  local mode
  mode="$(stat -c %a "$file")"
  [[ "$mode" == "600" ]] || die "$file has mode $mode, expected 600" "Run: chmod 600 $file"
  set -a
  # shellcheck disable=SC1090
  source "$file"
  set +a
}

# version_ge 2.40.3 2.20 → true
version_ge() {
  [[ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n1)" == "$2" ]]
}

# docker compose for the local stack (compose.yaml + compose.dev.yaml, versions.env + .env).
compose_local() {
  docker compose --project-directory "$FIVEDAYS_ROOT" --env-file "$FIVEDAYS_ROOT/versions.env" --env-file "$FIVEDAYS_ROOT/.env" \
    -f "$FIVEDAYS_ROOT/compose.yaml" -f "$FIVEDAYS_ROOT/compose.dev.yaml" "$@"
}

# ---- Remote hosts (used only by the host commands in bin/; agents never ssh themselves) ----
# A host spec is "<host>" or "<host>:<port>"; the port form targets the local stand-in hosts in
# test/hosts/. SSH_KEY, REMOTE_USER and SSH_OPTS can be overridden for those stand-ins.
SSH_KEY="${SSH_KEY:-$HOME/.ssh/LightsailDefaultKey-eu-central-1.pem}"
REMOTE_USER="${REMOTE_USER:-ubuntu}"
SSH_OPTS="${SSH_OPTS:--o StrictHostKeyChecking=accept-new}"
# Where the stack lives on a host, and how compose is called there.
HOST_DIR=/opt/fivedays
HOST_COMPOSE="cd $HOST_DIR && docker compose --env-file versions.env --env-file .env -f compose.yaml"

host_name() { printf '%s' "${1%%:*}"; }
host_port() { [[ "$1" == *:* ]] && printf '%s' "${1##*:}" || printf '22'; }

# ssh_args <spec>: the ssh arguments (without the command) for a host spec.
ssh_args() {
  local spec="$1"
  # shellcheck disable=SC2206
  local opts=($SSH_OPTS)
  printf '%s\n' -i "$SSH_KEY" -p "$(host_port "$spec")" -o BatchMode=yes -o ConnectTimeout=15 \
    -o ServerAliveInterval=30 -o ServerAliveCountMax=6 "${opts[@]}" "$REMOTE_USER@$(host_name "$spec")"
}

# on_host <spec> <bash script...>: runs the script as root on the host; stdin is passed through.
on_host() {
  local spec="$1"; shift
  local args
  mapfile -t args < <(ssh_args "$spec")
  ssh "${args[@]}" "sudo bash -c $(printf '%q' "$*")"
}

# on_host_script <spec> <local file> [args...]: pipes a local script to root bash on the host.
on_host_script() {
  local spec="$1" file="$2"; shift 2
  local args
  mapfile -t args < <(ssh_args "$spec")
  ssh "${args[@]}" "sudo bash -s -- $(printf '%q ' "$@")" <"$file"
}

# copy_to_host <spec> <local file> <remote path> [mode]: writes a file as root on the host.
copy_to_host() {
  local spec="$1" src="$2" dest="$3" mode="${4:-644}"
  on_host "$spec" "install -D -m $mode /dev/stdin $(printf '%q' "$dest")" <"$src"
}

# host_compose <spec> <compose args...>: docker compose for the stack in $HOST_DIR on the host.
host_compose() {
  local spec="$1"; shift
  on_host "$spec" "$HOST_COMPOSE $(printf '%q ' "$@")"
}

# Loads .env.prod (laptop only: holds keys that never go to a host).
load_prod_env() { load_env "$FIVEDAYS_ROOT/.env.prod"; }

need_ssh_key() {
  [[ -f "$SSH_KEY" ]] || die "SSH key $SSH_KEY not found" "Run: just doctor"
}
