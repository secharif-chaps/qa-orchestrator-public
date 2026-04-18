#!/usr/bin/env bash
# Pre-commit wrapper around .gitlab/scripts/check-alembic.sh.
#
# Alembic is not installed on the host — each app ships it inside its own
# Docker image. We stream the shared check script into the running container
# via stdin (same pattern as ruff in lint-staged.config.mjs) so there is a
# single source of truth for the checks.
#
# Usage:
#   .husky/scripts/check-alembic.sh <compose-service-name>
# Example:
#   .husky/scripts/check-alembic.sh screen

set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "usage: $0 <compose-service-name>" >&2
  exit 2
fi

service=$1
repo_root=$(cd "$(dirname "$0")/../.." && pwd)
shared_script=$repo_root/.gitlab/scripts/check-alembic.sh

if [[ ! -f $shared_script ]]; then
  echo "error: shared script not found at $shared_script" >&2
  exit 2
fi

# The check script expects to be invoked with the app root as argument.
# Inside every backend container the app root is mounted at /app.
docker compose exec -T "$service" bash -s -- /app < "$shared_script"