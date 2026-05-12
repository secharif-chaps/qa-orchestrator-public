#!/usr/bin/env bash
# Run promptfoo evals for all agents (or a single one).
#
# Runs every config independently so a failure in one agent does not abort
# the others. EXIT_CODE accumulates non-zero exits — the script exits non-zero
# if any agent fails, giving a full picture of regressions in one run.
#
# Env vars:
#   AGENT                  — optional, run a single agent (e.g. AGENT=profile)
#   PROMPTFOO_PYTHON       — Python interpreter to use (default: python3 on PATH)
#   RUN_ID                 — identifier stamped on output filenames to group all agents
#                            from the same run (default: current timestamp).
#                            In CI, set to sha-${CI_COMMIT_SHORT_SHA} for traceability.
#   PROMPTFOO_LLM_API_KEY  — required: eval-specific API key (falls back to LLM_API_KEY)
#   PROMPTFOO_LLM_BASE_URL — required: eval-specific gateway URL (falls back to LLM_BASE_URL)
#
# All positional args are forwarded to promptfoo (e.g. --no-cache).
#
# Usage:
#   bash run_evals.sh                                      # all agents
#   AGENT=profile bash run_evals.sh                        # single agent
#   bash run_evals.sh --no-cache                           # bypass LLM cache
#   RUN_ID=sha-a1b2c3d bash run_evals.sh                   # custom run ID
#   AGENT=profile bash run_evals.sh --no-cache             # combined

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Prefer the project venv if present (local dev), fall back to system python3 (CI)
_VENV_PY="$SCRIPT_DIR/../../../.venv/bin/python3"
PROMPTFOO_PYTHON="${PROMPTFOO_PYTHON:-$([ -f "$_VENV_PY" ] && echo "$_VENV_PY" || which python3)}"
export PROMPTFOO_PYTHON

if [ -n "${AGENT:-}" ]; then
  CONFIGS="configs/${AGENT}.yaml"
else
  CONFIGS="configs/*.yaml"
fi

RUN_ID="${RUN_ID:-$(date +%Y%m%d_%H%M%S)}"
EXIT_CODE=0

# Prefer eval-specific credentials; fall back to shared app vars for local dev.
PROMPTFOO_LLM_API_KEY="${PROMPTFOO_LLM_API_KEY:-${LLM_API_KEY:-}}"
PROMPTFOO_LLM_BASE_URL="${PROMPTFOO_LLM_BASE_URL:-${LLM_BASE_URL:-}}"

# Normalize base URL: strip trailing slash to prevent double-slash in endpoint URLs.
PROMPTFOO_LLM_BASE_URL="${PROMPTFOO_LLM_BASE_URL%/}"
export PROMPTFOO_LLM_API_KEY PROMPTFOO_LLM_BASE_URL

mkdir -p results

for config in $CONFIGS; do
  agent=$(basename "$config" .yaml)
  output_file="results/${agent}_${RUN_ID}.json"

  PYTHONPATH=../.. \
  npx --yes promptfoo@0.121.9 eval -c "$config" \
    --no-progress-bar \
    --output "$output_file" \
    "$@" || EXIT_CODE=$?

  # Promptfoo exits 0 even when the target is unreachable and 0 tests ran
  # (e.g. API 404, aborted scan). Detect this explicitly so CI does not
  # silently report success for a completely broken eval run.
  if [ -f "$output_file" ]; then
    total=$("$PROMPTFOO_PYTHON" -c "
import json, sys
try:
    with open('${output_file}') as f:
        d = json.load(f)
    s = d.get('results', {}).get('stats', {})
    print(s.get('successes', 0) + s.get('failures', 0) + s.get('errors', 0))
except Exception:
    print(0)
" 2>/dev/null || echo "0")
    if [ "${total:-0}" -eq 0 ]; then
      echo "ERROR: No tests ran for '${agent}' — target unreachable or eval aborted (check API endpoint/credentials)" >&2
      EXIT_CODE=1
    fi
  else
    echo "ERROR: No output file for '${agent}' — eval aborted before writing results" >&2
    EXIT_CODE=1
  fi
done

echo ""
echo "══════════════════════════════════════════════════════"
echo "  EVAL SUMMARY — ${RUN_ID}"
echo "══════════════════════════════════════════════════════"
"$PROMPTFOO_PYTHON" - "${RUN_ID}" results/ << 'PYEOF'
import json, glob, os, sys
run_id, results_dir = sys.argv[1], sys.argv[2]
files = sorted(glob.glob(f"{results_dir}*_{run_id}.json"))
total_p = total_f = total_e = 0
for f in files:
    try:
        d = json.load(open(f))
        s = d.get("results", {}).get("stats", {})
        p, fa, e = s.get("successes", 0), s.get("failures", 0), s.get("errors", 0)
        t = p + fa + e
        suffix = f"_{run_id}.json"
        base = os.path.basename(f)
        label = base[:-len(suffix)] if base.endswith(suffix) else base
        status = "PASS" if fa == 0 and e == 0 else "FAIL"
        print(f"  {label:<24} {p:>3}/{t:<3}  {status}")
        total_p += p; total_f += fa; total_e += e
    except Exception as ex:
        print(f"  {f}: parse error — {ex}")
grand = total_p + total_f + total_e
pct = f"{total_p/grand:.0%}" if grand else "n/a"
print(f"  {'─'*40}")
print(f"  {'TOTAL':<24} {total_p:>3}/{grand:<3}  {pct}")
PYEOF
echo "══════════════════════════════════════════════════════"
echo ""

exit $EXIT_CODE
