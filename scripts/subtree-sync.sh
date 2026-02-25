#!/bin/bash
# Sync monorepo with latest commits from legacy repositories.
# Usage:
#   ./scripts/subtree-sync.sh              # sync all modules
#   ./scripts/subtree-sync.sh front        # sync only front
#   ./scripts/subtree-sync.sh screen infra # sync screen and infra
#
# This is a transitional tool for the migration period.
# Once all devs work in the monorepo, archive the legacy repos and remove this script.

set -euo pipefail

# Module definitions: name -> prefix + remote + branch
declare -A PREFIXES=(
  [front]="apps/front"
  [screen]="apps/screen"
  [global-service]="apps/global-service"
  [infra]="infra"
)

declare -A REMOTES=(
  [front]="origin-front"
  [screen]="origin-screen"
  [global-service]="origin-global-service"
  [infra]="origin-infra"
)

# Default branch to pull from (override with SYNC_BRANCH env var)
BRANCH="${SYNC_BRANCH:-main}"

# If args given, sync only those modules; otherwise sync all
if [ $# -gt 0 ]; then
  MODULES=("$@")
else
  MODULES=("front" "screen" "global-service" "infra")
fi

for module in "${MODULES[@]}"; do
  prefix="${PREFIXES[$module]:-}"
  remote="${REMOTES[$module]:-}"

  if [ -z "$prefix" ] || [ -z "$remote" ]; then
    echo "Unknown module: $module"
    echo "Available modules: front, screen, global-service, infra"
    exit 1
  fi

  echo ""
  echo "=== Syncing $module ($remote/$BRANCH -> $prefix) ==="
  git subtree pull --prefix="$prefix" "$remote" "$BRANCH" -m "📦 sync($module): pull latest from $remote/$BRANCH"
  echo "✓ $module synced"
done

echo ""
echo "All done."