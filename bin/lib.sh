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
