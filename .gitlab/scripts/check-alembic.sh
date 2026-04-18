#!/usr/bin/env bash
# Sanity-check an Alembic migration directory before it lands on main.
#
# Catches mistakes alembic does not fail on by default:
#   1. Duplicate revision IDs — two files declaring the same `revision = "..."`.
#      Alembic only emits a UserWarning on stderr and keeps going; the second
#      file is silently ignored. This is how TAR-1583 happened: two "036"
#      migrations existed, one was never applied, schemas drifted.
#   2. Multi-head chains — `alembic upgrade head` refuses to run with more
#      than one head. Force the author to `alembic merge` before merging.
# Alembic's own commands already bail on cycles and orphan down_revisions,
# so we lean on `alembic history` to surface those.
#
# Usage:
#   .gitlab/scripts/check-alembic.sh <app_dir>
#
# Expects `alembic` on PATH and a valid alembic.ini in <app_dir>.
# Runs natively in CI (python-ci image) and is also reused by the pre-commit
# hook via `docker compose exec`.

set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "usage: $0 <app_dir>" >&2
  exit 2
fi

app_dir=$1
cd "$app_dir"

if [[ ! -f alembic.ini ]]; then
  echo "error: $app_dir/alembic.ini not found" >&2
  exit 2
fi

echo "→ Checking alembic in $app_dir"

# Capture stderr from `alembic heads` to catch the duplicate-revision warning.
heads_stderr=$(alembic heads 2>&1 >/dev/null || true)
heads_stdout=$(alembic heads 2>/dev/null || true)

# 1. Duplicate revision IDs
if grep -q "is present more than once" <<<"$heads_stderr"; then
  echo "✗ duplicate revision id detected:" >&2
  grep "is present more than once" <<<"$heads_stderr" >&2
  echo "  Two migration files declare the same revision. Rename one so the" >&2
  echo "  whole chain stays unique, or merge them with \`alembic merge\`." >&2
  exit 1
fi

# 2. Multi-head — more than one non-empty head line
heads_count=$(grep -c . <<<"$heads_stdout" || true)
if (( heads_count > 1 )); then
  echo "✗ multiple heads detected ($heads_count):" >&2
  echo "    ${heads_stdout//$'\n'/$'\n'    }" >&2
  echo "  Create a merge revision: \`alembic merge -m 'merge heads' <head1> <head2>\`" >&2
  exit 1
fi
if (( heads_count == 0 )); then
  echo "✗ no heads found — empty or broken migrations directory" >&2
  exit 1
fi

# 3. Chain integrity — history errors on cycles and orphan down_revisions
history_err=$(alembic history 2>&1 >/dev/null) || {
  echo "✗ alembic history failed (cycle or orphan down_revision?):" >&2
  echo "    ${history_err//$'\n'/$'\n'    }" >&2
  exit 1
}

echo "✓ alembic ok ($heads_count head, no duplicates)"