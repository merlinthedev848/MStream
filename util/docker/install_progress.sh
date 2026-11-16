#!/usr/bin/env bash
# Shared MediaCP-style install/update progress helpers for install.sh and mstream.sh.
# Focus: never leave GHCR / docker pulls looking hung; emit per-image status, heartbeats,
# stall warnings, and timeout/retry messaging.

# shellcheck disable=SC2034
: "${MSTREAM_PROGRESS_LOADED:=0}"
if [[ "${MSTREAM_PROGRESS_LOADED}" -eq 1 ]]; then
  return 0 2>/dev/null || exit 0
fi
MSTREAM_PROGRESS_LOADED=1

MSTREAM_CLR_RESET="${MSTREAM_CLR_RESET:-\033[0m}"
MSTREAM_CLR_BOLD="${MSTREAM_CLR_BOLD:-\033[1m}"
MSTREAM_CLR_CYAN="${MSTREAM_CLR_CYAN:-\033[36m}"
MSTREAM_CLR_BLUE="${MSTREAM_CLR_BLUE:-\033[34m}"
MSTREAM_CLR_GREEN="${MSTREAM_CLR_GREEN:-\033[32m}"
MSTREAM_CLR_YELLOW="${MSTREAM_CLR_YELLOW:-\033[33m}"
MSTREAM_CLR_RED="${MSTREAM_CLR_RED:-\033[31m}"

# Heartbeat while a pull log has not grown (seconds)
MSTREAM_PULL_HEARTBEAT_SECS="${MSTREAM_PULL_HEARTBEAT_SECS:-15}"
# Warn that GHCR is slow when no new log output for this long
MSTREAM_PULL_STALL_WARN_SECS="${MSTREAM_PULL_STALL_WARN_SECS:-60}"
# Hard timeout per image pull attempt (default 12 minutes)
MSTREAM_PULL_TIMEOUT_SECS="${MSTREAM_PULL_TIMEOUT_SECS:-720}"
# One automatic retry after timeout/failure
MSTREAM_PULL_RETRIES="${MSTREAM_PULL_RETRIES:-1}"

mstream_progress_now() {
  date +%s
}

mstream_progress_fmt_duration() {
  local total="${1:-0}"
  local mins=$((total / 60))
  local secs=$((total % 60))
  if [[ "$mins" -gt 0 ]]; then
    printf "%dm%02ds" "$mins" "$secs"
  else
    printf "%ds" "$secs"
  fi
}

mstream_step() {
  echo ""
  echo -e "${MSTREAM_CLR_BLUE}==>${MSTREAM_CLR_RESET} ${MSTREAM_CLR_BOLD}$1${MSTREAM_CLR_RESET}"
}

mstream_step_ok() {
  echo -e "      ${MSTREAM_CLR_GREEN}[PASS]${MSTREAM_CLR_RESET} $1"
}

mstream_step_info() {
  echo -e "      ${MSTREAM_CLR_CYAN}[INFO]${MSTREAM_CLR_RESET} $1"
}

mstream_step_warn() {
  echo -e "      ${MSTREAM_CLR_YELLOW}[WARN]${MSTREAM_CLR_RESET} $1"
}

mstream_step_fail() {
  echo -e "      ${MSTREAM_CLR_RED}[FAIL]${MSTREAM_CLR_RESET} $1"
}

mstream_docker_bin() {
  if command -v docker >/dev/null 2>&1; then
    echo docker
  elif command -v podman >/dev/null 2>&1; then
    echo podman
  else
    echo docker
  fi
}

# Tail last non-empty progress line from a docker pull log (best-effort).
mstream_pull_log_tail() {
  local logfile="$1"
  if [[ ! -s "$logfile" ]]; then
    echo "(waiting for registry response…)"
    return
  fi
  # Prefer a meaningful layer/status line; strip CR from docker's in-place progress.
  tr '\r' '\n' <"$logfile" 2>/dev/null \
    | grep -E 'Downloading|Extracting|Download complete|Pull complete|Already exists|Waiting|Retrying|digest:|Status:|error|denied|unauthorized' \
    | tail -n 1 \
    | sed 's/[[:space:]]\+/ /g' \
    | cut -c1-120 \
    || tr '\r' '\n' <"$logfile" 2>/dev/null | grep -v '^[[:space:]]*$' | tail -n 1 | cut -c1-120 \
    || echo "(pull in progress…)"
}

# Pull one image with live status lines, stall warnings, timeout, and retry.
# Usage: mstream_pull_image <image> <label>
# Returns 0 on success, non-zero on failure.
mstream_pull_image() {
  local image="$1"
  local label="${2:-image}"
  local docker_bin
  docker_bin="$(mstream_docker_bin)"
  local attempt=0
  local max_attempts=$((MSTREAM_PULL_RETRIES + 1))
  local start_ts elapsed
  start_ts="$(mstream_progress_now)"

  mstream_step "Pulling ${label}…"
  mstream_step_info "Image: ${MSTREAM_CLR_BOLD}${image}${MSTREAM_CLR_RESET}"
  if [[ "$image" == ghcr.io/* ]]; then
    mstream_step_info "GHCR pulls are often the longest step (several minutes on a normal VPS)."
    mstream_step_info "Quiet for ~${MSTREAM_PULL_STALL_WARN_SECS}s usually means a slow registry — not a frozen installer."
  fi

  if "$docker_bin" image inspect "$image" >/dev/null 2>&1; then
    mstream_step_info "Local copy present; docker will reuse layers and only fetch updates."
  fi

  while [[ "$attempt" -lt "$max_attempts" ]]; do
    attempt=$((attempt + 1))
    if [[ "$attempt" -gt 1 ]]; then
      mstream_step_warn "Retry ${attempt}/${max_attempts} for ${label}…"
    fi

    local logfile
    logfile="$(mktemp "${TMPDIR:-/tmp}/mstream-pull.XXXXXX")"
    local pull_pid
    local last_size=0
    local last_change_ts
    last_change_ts="$(mstream_progress_now)"
    local warned_stall=0
    local pull_rc=0

    # Run pull in background so we can emit heartbeats even when docker buffers progress.
    "$docker_bin" pull "$image" >"$logfile" 2>&1 &
    pull_pid=$!

    while kill -0 "$pull_pid" 2>/dev/null; do
      sleep "$MSTREAM_PULL_HEARTBEAT_SECS"
      elapsed=$(( $(mstream_progress_now) - start_ts ))
      local size
      size="$(wc -c <"$logfile" 2>/dev/null || echo 0)"
      size="${size//[[:space:]]/}"

      if [[ "$size" -gt "$last_size" ]]; then
        last_size="$size"
        last_change_ts="$(mstream_progress_now)"
        warned_stall=0
        echo -e "      ${MSTREAM_CLR_CYAN}[${label}]${MSTREAM_CLR_RESET} $(mstream_progress_fmt_duration "$elapsed") — $(mstream_pull_log_tail "$logfile")"
      else
        local stall=$(( $(mstream_progress_now) - last_change_ts ))
        echo -e "      ${MSTREAM_CLR_CYAN}[${label}]${MSTREAM_CLR_RESET} $(mstream_progress_fmt_duration "$elapsed") — still working… $(mstream_pull_log_tail "$logfile")"
        if [[ "$stall" -ge "$MSTREAM_PULL_STALL_WARN_SECS" && "$warned_stall" -eq 0 ]]; then
          warned_stall=1
          mstream_step_warn "No new pull output for ${stall}s. If this is GHCR, wait through layer downloads; cancel only if idle >10–15 minutes."
        fi
      fi

      if [[ "$elapsed" -ge "$MSTREAM_PULL_TIMEOUT_SECS" ]]; then
        mstream_step_warn "Hit per-image timeout (${MSTREAM_PULL_TIMEOUT_SECS}s) for ${label}; stopping this attempt…"
        kill "$pull_pid" 2>/dev/null || true
        wait "$pull_pid" 2>/dev/null || true
        pull_rc=124
        break
      fi
    done

    if [[ "$pull_rc" -ne 124 ]]; then
      wait "$pull_pid" 2>/dev/null
      pull_rc=$?
    fi

    # Show trailing docker status lines for visibility.
    if [[ -s "$logfile" ]]; then
      tr '\r' '\n' <"$logfile" | grep -E 'Status:|Digest:|error|denied|unauthorized|Pulled' | tail -n 5 \
        | while IFS= read -r line; do
            [[ -n "$line" ]] && echo -e "      $line"
          done
    fi

    if [[ "$pull_rc" -eq 0 ]]; then
      elapsed=$(( $(mstream_progress_now) - start_ts ))
      mstream_step_ok "${label} ready ($(mstream_progress_fmt_duration "$elapsed"))"
      rm -f "$logfile"
      return 0
    fi

    mstream_step_fail "${label} pull failed (exit ${pull_rc})."
    if [[ -s "$logfile" ]]; then
      mstream_step_info "Last log lines:"
      tr '\r' '\n' <"$logfile" | grep -v '^[[:space:]]*$' | tail -n 8 | sed 's/^/        /'
    fi
    rm -f "$logfile"

    if [[ "$attempt" -lt "$max_attempts" ]]; then
      mstream_step_info "Waiting 5s before retry…"
      sleep 5
    fi
  done

  elapsed=$(( $(mstream_progress_now) - start_ts ))
  mstream_step_fail "Could not pull ${label} after ${max_attempts} attempt(s) ($(mstream_progress_fmt_duration "$elapsed"))."
  mstream_step_info "Check network access to the registry (GHCR: ghcr.io). Re-run install/update when the link is stable."
  return 1
}

# Resolve compose command (docker compose / docker-compose / podman-compose).
mstream_compose_cmd() {
  if command -v podman >/dev/null 2>&1 && ! command -v docker >/dev/null 2>&1; then
    if command -v podman-compose >/dev/null 2>&1; then
      echo "podman-compose"
      return
    fi
  fi
  if docker compose version >/dev/null 2>&1; then
    echo "docker compose"
  elif command -v docker-compose >/dev/null 2>&1; then
    echo "docker-compose"
  else
    echo "docker compose"
  fi
}

# Resolve the image ref for a compose service (expanded env).
mstream_compose_service_image() {
  local svc="$1"
  local dc_cmd
  dc_cmd="$(mstream_compose_cmd)"
  local image=""

  # docker compose config --images (Compose v2.20+) may list all; prefer per-service config parse.
  # shellcheck disable=SC2086
  image="$($dc_cmd config 2>/dev/null | awk -v svc="$svc" '
    $0 ~ "^  " svc ":" {in_svc=1; next}
    in_svc && $1 == "image:" {
      img=$0
      sub(/^[[:space:]]*image:[[:space:]]*/, "", img)
      gsub(/"/, "", img)
      gsub(/'\''/, "", img)
      print img
      exit
    }
    in_svc && /^  [a-zA-Z0-9_-]+:/ {in_svc=0}
  ')"

  if [[ -n "$image" ]]; then
    echo "$image"
    return 0
  fi
  return 1
}

# Monitored `compose pull <service>` fallback when image ref cannot be resolved.
mstream_pull_compose_service_fallback() {
  local svc="$1"
  local label="${2:-$svc}"
  local dc_cmd
  dc_cmd="$(mstream_compose_cmd)"
  local logfile pull_pid start_ts elapsed last_size last_change_ts warned_stall pull_rc size

  mstream_step "Pulling ${label}…"
  logfile="$(mktemp "${TMPDIR:-/tmp}/mstream-pull.XXXXXX")"
  start_ts="$(mstream_progress_now)"
  last_size=0
  last_change_ts="$start_ts"
  warned_stall=0
  pull_rc=0

  # shellcheck disable=SC2086
  $dc_cmd pull "$svc" >"$logfile" 2>&1 &
  pull_pid=$!
  while kill -0 "$pull_pid" 2>/dev/null; do
    sleep "$MSTREAM_PULL_HEARTBEAT_SECS"
    elapsed=$(( $(mstream_progress_now) - start_ts ))
    size="$(wc -c <"$logfile" 2>/dev/null || echo 0)"
    size="${size//[[:space:]]/}"
    if [[ "$size" -gt "$last_size" ]]; then
      last_size="$size"
      last_change_ts="$(mstream_progress_now)"
      warned_stall=0
    fi
    echo -e "      ${MSTREAM_CLR_CYAN}[${label}]${MSTREAM_CLR_RESET} $(mstream_progress_fmt_duration "$elapsed") — $(mstream_pull_log_tail "$logfile")"
    if [[ $(( $(mstream_progress_now) - last_change_ts )) -ge $MSTREAM_PULL_STALL_WARN_SECS && "$warned_stall" -eq 0 ]]; then
      warned_stall=1
      mstream_step_warn "No new pull output for ${MSTREAM_PULL_STALL_WARN_SECS}s+. Slow registry responses are common; wait unless idle >10–15 minutes."
    fi
    if [[ "$elapsed" -ge "$MSTREAM_PULL_TIMEOUT_SECS" ]]; then
      mstream_step_warn "Timeout (${MSTREAM_PULL_TIMEOUT_SECS}s) pulling ${label}."
      kill "$pull_pid" 2>/dev/null || true
      wait "$pull_pid" 2>/dev/null || true
      pull_rc=124
      break
    fi
  done
  if [[ "$pull_rc" -ne 124 ]]; then
    wait "$pull_pid" 2>/dev/null
    pull_rc=$?
  fi
  if [[ "$pull_rc" -eq 0 ]]; then
    mstream_step_ok "${label} ready ($(mstream_progress_fmt_duration $(( $(mstream_progress_now) - start_ts ))))"
    rm -f "$logfile"
    return 0
  fi
  mstream_step_fail "Compose pull for ${label} failed (exit ${pull_rc})."
  tr '\r' '\n' <"$logfile" | grep -v '^[[:space:]]*$' | tail -n 8 | sed 's/^/        /' || true
  rm -f "$logfile"
  return "$pull_rc"
}

# Pull only the named compose services, one banner each (skips unused services).
# Usage: mstream_pull_compose_services [service ...]
# Default: web updater
mstream_pull_compose_services() {
  local services=("$@")
  if [[ "${#services[@]}" -eq 0 ]]; then
    services=(web updater)
  fi

  local image label svc

  mstream_step "Pulling container images"
  mstream_step_info "Services: ${services[*]} (unused compose services are not pulled)"
  mstream_step_info "Each image prints its own progress; GHCR (web) is usually the slowest."
  mstream_print_timing_expectations

  for svc in "${services[@]}"; do
    case "$svc" in
      web) label="web (GHCR)" ;;
      updater) label="updater (Watchtower)" ;;
      *) label="$svc" ;;
    esac

    image="$(mstream_compose_service_image "$svc" || true)"
    if [[ -n "$image" ]]; then
      if ! mstream_pull_image "$image" "$label"; then
        return 1
      fi
    else
      if ! mstream_pull_compose_service_fallback "$svc" "$label"; then
        return 1
      fi
    fi
  done
  return 0
}

mstream_print_timing_expectations() {
  mstream_step_info "Expected duration on a normal VPS with a healthy network: about 5–15 minutes."
  mstream_step_info "Longest step is usually pulling the web image from GHCR."
  mstream_step_info "\"Stuck\" means no layer/download activity for 10–15+ minutes — not a quiet progress heartbeat."
}
