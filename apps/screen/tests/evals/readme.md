# Screen Evals

LLM evaluation suite for screen agents using [promptfoo](https://www.promptfoo.dev/).

Covers: prompt regression detection, model comparison across providers, tiered test cases.

---

## Setup

```sh
cp .env.example .env
# fill in:
#   LLM_GATEWAY_API_KEY   — your gateway API key
#   LLM_GATEWAY_BASE_URL  — e.g. https://llm-gateway.ai.chapsvision.com/llm-gateway
#   LLM_RUBRIC_MODEL      — judge model, e.g. gpt-4o-mini-sweden
```

---

## Running evals

All commands run from the **monorepo root**.

### During prompt development — single agent, fast iteration

Run after every prompt change to catch regressions immediately:

```sh
task screen:evals:local -- profile
task screen:evals:local -- financial_classify
task screen:evals:local -- sanctions
task screen:evals:local -- planner
```

Uses cache by default. Only runs the primary provider (all 3 if you want comparison).

### All agents at once — full regression check

```sh
task screen:evals:local
```

### Model comparison — which LLM is best for this agent?

Runs all 3 providers, saves results to `eval-results/compare_report.json`:

```sh
task screen:evals:compare
task screen:evals:view   # open web UI to compare side-by-side
```

### CI mode — strict, no cache, 1 provider, exits non-zero on threshold failure

```sh
task screen:evals:ci
```

---

## Test tiers (A / B / C)

Each agent has exactly 3 test cases, each with a different threshold:

| Tier  | Threshold | Meaning                                       |
| ----- | --------- | --------------------------------------------- |
| **A** | `0.9`     | Must always pass — CI fails if this regresses |
| **B** | `0.7`     | Should pass — tracked but does not block CI   |
| **C** | `0.0`     | Advisory — hard case, never blocks CI         |

Score formula: `is-json(w:1) + schema_valid(w:1) + llm_rubric(w:10)` → total 12 points.
A wrong-but-valid-JSON answer scores `2/12 = 0.17` → fails at any threshold above 0.

---

## Simulated example: testing a prompt change

Say you modify the `profile` prompt. Here is exactly what to run and what to look for.

### 1. Run the profile eval (single provider, uses cache for unchanged parts)

```sh
task screen:evals:local -- profile
```

### 2. Read the result table

```
┌─────────────┬──────────────────────────────────────┬──────────────────┐
│ _agent_name │ [openai:chat:gpt-4o-mini-sweden]      │ threshold        │
├─────────────┼──────────────────────────────────────┼──────────────────┤
│ profile     │ [PASS] {"groupName":{"value":"LVMH"}… │ 0.9 ✓           │
│ profile     │ [PASS] {"groupName":{"value":"Docto"} │ 0.7 ✓           │
│ profile     │ [FAIL] {"groupName": null, ...}       │ 0.0 (advisory)  │
└─────────────┴──────────────────────────────────────┴──────────────────┘
Results: 2 passed, 1 failed
```

- **A (LVMH) passes at 0.9** → no regression on the must-pass case ✓
- **B (Doctolib) passes at 0.7** → acceptable ✓
- **C (Pennylane) fails at 0.0** → advisory only, ignored in CI ✓

### 3. If A or B fails — you have a regression

Open the web UI for the full output:

```sh
task screen:evals:view
```

Look at the rubric score breakdown to understand what degraded.

### 4. Run without cache to force a fresh LLM call

```sh
PROMPTFOO_CACHE_ENABLED=false task screen:evals:local -- profile
```

---

## Simulated example: comparing models for the sanctions agent

You want to know if `gemini-2.5-flash` is good enough to replace `gpt-4o-mini-sweden` for sanctions.

### 1. Run the compare task (all 3 providers)

```sh
task screen:evals:compare
```

### 2. Open the web UI

```sh
task screen:evals:view
```

The web UI shows a table with all providers as columns.
Compare scores for each test case across providers.

### 3. Interpret

If `gemini-2.5-flash` scores 11/12 on A and B cases for sanctions,
vs `gpt-4o-mini-sweden` scoring 12/12, Gemini is close enough with lower cost.

---

## Structure

```
evals/
├── promptfooconfig.yaml          # Shared evaluateOptions (cache, progress bar)
│                                 # NOTE: each agent config is self-contained
│                                 # (providers defined per-config — YAML anchors
│                                 #  don't cross files and config chaining has
│                                 #  path resolution issues in promptfoo)
├── configs/                      # One config per agent — self-contained
│   ├── profile.yaml
│   ├── financial_classify.yaml
│   ├── sanctions.yaml
│   └── planner.yaml
├── providers/
│   └── loaders.py                # Loads prompts from PROMPTS_REGISTRY
├── assertions/
│   └── validate_schema.py        # Pydantic schema validation (shared assertion)
├── tests/                        # Test cases per agent (A/B/C tiers)
│   ├── tests_profile.yaml
│   ├── tests_financial.yaml
│   ├── tests_sanctions.yaml
│   └── tests_planner.yaml
└── eval-results/                 # JSON output — gitignored, kept for evals:view
    ├── results_*.json            # Per-agent results (written by evals:local)
    ├── ci_report.json            # Written by evals:ci
    └── compare_report.json       # Written by evals:compare
```

---

## Adding a new agent eval

1. **Add a loader** in `providers/loaders.py`:

   ```python
   def load_my_agent(context: dict) -> list[dict]:
       return [
           {"role": "system", "content": PROMPTS_REGISTRY["my_agent"]},
           {"role": "user", "content": _user(context)},
       ]
   ```

2. **Add schema validation** in `assertions/validate_schema.py` (if agent has no production schema):

   ```python
   class MyAgentOutput(BaseModel):
       field_a: str
       field_b: Literal["x", "y"]

   _SCHEMAS["my_agent"] = MyAgentOutput
   ```

3. **Create the config** — copy any existing config and change 4 lines:

   ```sh
   cp configs/planner.yaml configs/my_agent.yaml
   # edit: description, load_my_agent, _agent_name, tests file, outputPath
   ```

4. **Create the test file** `tests/tests_my_agent.yaml` with 3 cases:

   ```yaml
   - description: 'A: [Easy company] — must pass'
     threshold: 0.9
     vars:
       user_message: '...'
       rubric: 'Response should ...'

   - description: 'B: [Medium company] — should pass'
     threshold: 0.7
     vars:
       user_message: '...'
       rubric: 'Response should ...'

   - description: 'C: [Hard company] — advisory'
     threshold: 0.0
     vars:
       user_message: '...'
       rubric: 'Response should ...'
   ```

5. **Run it**:
   ```sh
   task screen:evals:local -- my_agent
   ```
