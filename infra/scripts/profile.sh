#!/bin/bash
# Manage Docker Compose profiles: interactive multi-select, add/remove, list.
#
# Usage:
#   bash infra/scripts/profile.sh                # interactive (gum or bash fallback)
#   bash infra/scripts/profile.sh list           # show active + available
#   bash infra/scripts/profile.sh add <name>     # activate + start services + migrate
#   bash infra/scripts/profile.sh remove <name>  # stop services + deactivate

set -euo pipefail

# ─── Helpers ────────────────────────────────────────────

discover_known_profiles() {
  # Parse Compose `profiles:` declarations in both inline (`profiles: [a, b]`)
  # and block-sequence (`profiles:\n  - a\n  - b`) forms — both are valid YAML
  # and both are accepted by Docker Compose.
  awk '
    # Inline form: profiles: [a, b, c]
    match($0, /profiles:[[:space:]]*\[[^]]*\]/) {
      s = substr($0, RSTART, RLENGTH)
      sub(/^profiles:[[:space:]]*\[/, "", s)
      sub(/\][[:space:]]*$/, "", s)
      gsub(/[[:space:]]/, "", s)
      n = split(s, arr, ",")
      for (i = 1; i <= n; i++) if (arr[i] != "") print arr[i]
      in_block = 0
      next
    }
    # Block-sequence header: a bare "profiles:" line
    /^[[:space:]]*profiles:[[:space:]]*(#.*)?$/ {
      in_block = 1
      next
    }
    # Block-sequence item: "  - name" (with optional trailing comment)
    in_block && /^[[:space:]]*-[[:space:]]+[^[:space:]#]/ {
      item = $0
      sub(/^[[:space:]]*-[[:space:]]+/, "", item)
      sub(/[[:space:]]*#.*$/, "", item)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", item)
      if (item != "") print item
      next
    }
    # Any other line ends the block
    { in_block = 0 }
  ' infra/compose.yaml infra/compose.local.yaml 2>/dev/null \
    | sort -u | tr '\n' ' ' | sed 's/ $//'
}

read_current_profiles() {
  grep '^COMPOSE_PROFILES=' .env 2>/dev/null | cut -d= -f2 || true
}

write_env_profiles() {
  local new_value="$1"
  if grep -q '^COMPOSE_PROFILES=' .env; then
    # Input is validated against KNOWN_PROFILES upstream — sed with | delimiter is safe.
    sed -i "s|^COMPOSE_PROFILES=.*|COMPOSE_PROFILES=${new_value}|" .env
  else
    echo "COMPOSE_PROFILES=${new_value}" >> .env
  fi
}

add_to_csv() {
  local csv="$1" item="$2"
  if echo "$csv" | grep -qwF "$item"; then
    echo "$csv"
  else
    echo "${csv:+${csv},}${item}"
  fi
}

remove_from_csv() {
  local csv="$1" item="$2"
  # `grep -vx` exits 1 when the result is empty (e.g. removing the last item);
  # `|| true` keeps the pipeline successful so callers can rely on `set -e`.
  # `-F` treats the pattern as a fixed string (profile names may contain `.`).
  echo "$csv" | tr ',' '\n' | { grep -vxF "$item" || true; } | tr '\n' ',' | sed 's/,$//'
}

is_alembic_profile() {
  case "$1" in
    screen|stream) return 0 ;;
    *) return 1 ;;
  esac
}

start_profile_services() {
  local profile="$1"
  echo ""
  echo "🐳 Starting '${profile}' services..."
  docker compose --profile "$profile" up -d --build
  if is_alembic_profile "$profile"; then
    echo ""
    echo "🗃️  Running migrations for '${profile}'..."
    docker compose exec "$profile" alembic upgrade head
  else
    echo "  ℹ️  No alembic migrations for '${profile}'"
  fi
}

stop_profile_services() {
  local profile="$1"
  echo ""
  echo "🛑 Stopping '${profile}' services..."
  docker compose --profile "$profile" stop
}

require_known_profile() {
  local profile="$1" known="$2"
  if [ -z "$profile" ] || ! echo "$known" | grep -qwF "$profile"; then
    echo "❌ Unknown profile: '${profile:-<empty>}'"
    echo "   Available: ${known// /, }"
    exit 1
  fi
}

# ─── Subcommand: list ───────────────────────────────────

cmd_list() {
  local known active
  known=$(discover_known_profiles)
  active=$(read_current_profiles)
  echo "Active:    ${active:-(none)}"
  echo "Available: ${known// /, }"
}

# ─── Subcommand: add ────────────────────────────────────

cmd_add() {
  local profile="${1:-}"
  local known current new
  known=$(discover_known_profiles)
  require_known_profile "$profile" "$known"

  current=$(read_current_profiles)
  if echo "$current" | grep -qwF "$profile"; then
    echo "ℹ️  '${profile}' already in COMPOSE_PROFILES"
  else
    new=$(add_to_csv "$current" "$profile")
    write_env_profiles "$new"
    echo "✅ Set COMPOSE_PROFILES=${new}"
  fi

  start_profile_services "$profile"
  echo ""
  echo "✅ Profile '${profile}' is ready."
}

# ─── Subcommand: remove ─────────────────────────────────

cmd_remove() {
  local profile="${1:-}"
  local known current new
  known=$(discover_known_profiles)
  require_known_profile "$profile" "$known"

  current=$(read_current_profiles)
  if ! echo "$current" | grep -qwF "$profile"; then
    echo "ℹ️  '${profile}' is not in COMPOSE_PROFILES"
    exit 0
  fi

  stop_profile_services "$profile"
  new=$(remove_from_csv "$current" "$profile")
  write_env_profiles "$new"
  echo ""
  echo "✅ Removed '${profile}' — COMPOSE_PROFILES=${new:-(none)}"
}

# ─── Interactive selection (gum) ───────────────────────

select_with_gum() {
  local known="$1" active="$2"
  local pre_selected
  pre_selected=$(echo "$active" | tr ',' '\n' | sed '/^$/d' | paste -sd, -)
  # shellcheck disable=SC2086 # word splitting on $known is intentional
  gum choose --no-limit \
    --header="Select active modules (space=toggle, enter=confirm)" \
    ${pre_selected:+--selected="$pre_selected"} \
    $known \
    | tr '\n' ',' | sed 's/,$//'
}

# ─── Interactive selection (bash fallback) ─────────────

select_with_bash() {
  local known="$1" active="$2"
  local -a profile_list
  read -ra profile_list <<<"$known"

  echo "🧩 Active modules (toggle by entering numbers, comma-separated):" >&2
  local i=1
  for p in "${profile_list[@]}"; do
    if echo "$active" | grep -qwF "$p"; then
      echo "  [x] ${i}) ${p}" >&2
    else
      echo "  [ ] ${i}) ${p}" >&2
    fi
    ((i++))
  done
  echo "" >&2

  local input
  read -r -p "Toggle [Enter to keep current]: " input

  # Build the new selection by toggling chosen indices against `active`
  local -A is_selected=()
  for p in "${profile_list[@]}"; do
    if echo "$active" | grep -qwF "$p"; then
      is_selected["$p"]=1
    fi
  done

  if [ -n "$input" ]; then
    local IFS=', '
    read -ra tokens <<<"$input"
    for tok in "${tokens[@]}"; do
      [ -z "$tok" ] && continue
      if ! [[ "$tok" =~ ^[0-9]+$ ]] || (( tok < 1 || tok > ${#profile_list[@]} )); then
        echo "❌ Invalid selection: '${tok}' (expected 1..${#profile_list[@]})" >&2
        exit 1
      fi
      local target="${profile_list[$((tok - 1))]}"
      if [ -n "${is_selected[$target]:-}" ]; then
        unset 'is_selected[$target]'
      else
        is_selected["$target"]=1
      fi
    done
  fi

  # Output preserves the original ordering of `known`
  local -a out
  out=()
  for p in "${profile_list[@]}"; do
    [ -n "${is_selected[$p]:-}" ] && out+=("$p")
  done
  (IFS=,; echo "${out[*]:-}")
}

# ─── Subcommand: interactive (default) ──────────────────

cmd_interactive() {
  local known active selected
  known=$(discover_known_profiles)
  if [ -z "$known" ]; then
    echo "ℹ️  No profiles declared in compose files — nothing to select."
    exit 0
  fi
  active=$(read_current_profiles)

  if command -v gum &> /dev/null; then
    selected=$(select_with_gum "$known" "$active")
  else
    echo "ℹ️  gum not found — using bash fallback (install: https://github.com/charmbracelet/gum)"
    selected=$(select_with_bash "$known" "$active")
  fi

  # Compute diff
  local -a additions removals
  additions=()
  removals=()
  for p in $known; do
    local in_active in_selected
    in_active=0; in_selected=0
    echo "$active" | grep -qwF "$p" && in_active=1
    echo "$selected" | grep -qwF "$p" && in_selected=1
    if [ "$in_active" = "0" ] && [ "$in_selected" = "1" ]; then
      additions+=("$p")
    elif [ "$in_active" = "1" ] && [ "$in_selected" = "0" ]; then
      removals+=("$p")
    fi
  done

  if [ "${#additions[@]}" = "0" ] && [ "${#removals[@]}" = "0" ]; then
    echo "ℹ️  No changes — COMPOSE_PROFILES=${active:-(none)}"
    exit 0
  fi

  echo ""
  echo "📋 Plan:"
  [ "${#removals[@]}" -gt 0 ] && echo "   − remove: ${removals[*]}"
  [ "${#additions[@]}" -gt 0 ] && echo "   + add:    ${additions[*]}"

  for p in "${removals[@]}"; do
    stop_profile_services "$p"
    local current
    current=$(read_current_profiles)
    write_env_profiles "$(remove_from_csv "$current" "$p")"
  done

  for p in "${additions[@]}"; do
    local current new
    current=$(read_current_profiles)
    new=$(add_to_csv "$current" "$p")
    write_env_profiles "$new"
    start_profile_services "$p"
  done

  local final
  final=$(read_current_profiles)
  echo ""
  echo "✅ COMPOSE_PROFILES=${final:-(none)}"
}

# ─── Dispatch ───────────────────────────────────────────

case "${1:-}" in
  "")           cmd_interactive ;;
  list)         cmd_list ;;
  add)          shift; cmd_add "${1:-}" ;;
  remove)       shift; cmd_remove "${1:-}" ;;
  -h|--help|help)
    sed -n '2,8p' "$0" | sed 's/^# \?//'
    ;;
  *)
    echo "❌ Unknown subcommand: '$1'"
    echo "Usage: $0 [list|add <name>|remove <name>]   (no arg = interactive)"
    exit 1
    ;;
esac
