# Screen — promptfoo test suite

LLM testing for screen agents. Two complementary test types, one toolchain.

```
tests/promptfoo/
├── shared/          # Judge model config + base test options (used by both)
├── evals/           # Regression tests on benign inputs (runs on MR)
└── redteam/         # Adversarial robustness probes (runs on schedule)
```

---

## Evals vs redteam — what each tests

|                | Evals                                             | Redteam                            |
| -------------- | ------------------------------------------------- | ---------------------------------- |
| **Input**      | Curated, realistic company inputs                 | Auto-generated adversarial attacks |
| **Goal**       | Detect prompt regressions, validate output schema | Detect safety/robustness failures  |
| **Cases**      | 3 hand-written tiers (A/B/C) per agent            | ~20 generated probes per agent     |
| **Blocking**   | Yes — A/B tiers block CI                          | No — advisory only                 |
| **CI trigger** | On MR when prompt files change                    | Scheduled (nightly/weekly)         |

Run **evals** after every prompt change. Run **redteam** when introducing a new agent or
periodically for safety audits.

---

## Evals

### Test tiers

Each agent has 3 cases with different pass thresholds:

| Tier  | Threshold | Meaning                                       |
| ----- | --------- | --------------------------------------------- |
| **A** | 0.9       | Must always pass — CI fails if this regresses |
| **B** | 0.7       | Should pass — tracked, does not block CI      |
| **C** | 0.0       | Advisory — hard case, never blocks CI         |

Score: `is-json(w:1) + schema_valid(w:1) + llm_rubric(w:10)` → 12 points total.

### Running evals

```sh
# All agents
task screen:evals

# Single agent (fast iteration during prompt development)
task screen:evals AGENT=planner

# Force fresh LLM calls (bypass cache)
task screen:evals -- --no-cache
task screen:evals AGENT=planner -- --no-cache

# Open web UI (full history, side-by-side comparison)
task screen:evals:view
```

### Workflow: after a prompt change

1. Edit the prompt in `app/agents/prompts/`.
2. Run `task screen:evals AGENT=<agent>` — uses cache for unchanged cases.
3. If an A or B case fails, open the web UI (`task screen:evals:view`) to inspect the rubric
   breakdown.
4. If all A/B pass, run `task screen:evals` to check no other agent regressed (prompts share the
   same LLM gateway — model updates can affect all).

### Why a bash loop (not `promptfoo eval -c a.yaml -c b.yaml`)

Passing multiple `-c` flags to promptfoo merges the configs: every test case gets paired with every
provider across all configs. Running each config in isolation via a loop preserves the correct
loader ↔ test mapping. See `evals/run_evals.sh`.

### Structure

```
evals/
├── configs/         # One self-contained config per agent (13 agents)
├── providers/
│   └── loaders.py   # Returns message lists — promptfoo owns the HTTP call
├── assertions/
│   └── validate_schema.py  # Pydantic output schema validation
├── tests/           # tests_<agent>.yaml — A/B/C cases
├── results/         # JSON output (gitignored) — kept for evals:view
└── run_evals.sh     # The loop runner — do not replace with multi -c
```

### Adding a new agent eval

1. **Register a loader** in `providers/loaders.py`:

   ```python
   def load_my_agent(context: dict) -> list[dict]:
       return [
           {"role": "system", "content": PROMPTS_REGISTRY["my_agent"]},
           {"role": "user", "content": context["vars"]["user_message"]},
       ]
   ```

2. **Register the schema** in `assertions/validate_schema.py`:

   ```python
   class MyAgentOutput(BaseModel):
       field_a: str
       field_b: str | None

   _SCHEMAS["my_agent"] = MyAgentOutput
   ```

3. **Create the config** — copy `configs/planner.yaml`, update: `description`, loader function name,
   `_agent_name` label, tests file path, `outputPath`.

4. **Create `tests/tests_my_agent.yaml`** with 3 cases (A/B/C tiers). Pick companies with increasing
   difficulty.

5. `task screen:evals AGENT=my_agent`

---

## Redteam

### How it works

1. **Generate once**: `promptfoo redteam generate` uses an LLM to craft adversarial probes targeting
   the agent's purpose. Output is committed to `probes/<agent>.yaml`.
2. **Run anywhere**: `promptfoo redteam eval -c probes/<agent>.yaml` replays the committed probes —
   no generation, reproducible, cheap.

Probes are committed because generation is expensive and non-deterministic. Regenerate only when the
agent's purpose, plugins, or `numTests` change.

### Running redteam

```sh
# Generate probes for an agent (only when purpose or plugins change — commit the output)
task screen:redteam:generate AGENT=planner

# Run the committed probes locally (no generation)
task screen:redteam:eval AGENT=planner

# Generate + run in one shot (local dev / exploration)
task screen:redteam AGENT=planner

# Open vulnerability report in browser
task screen:redteam:report
```

### Why a separate provider (`redteam_loaders.py`)

Redteam providers must return `{"output": "..."}` — the full agent response as a string. Promptfoo's
redteam mode calls the provider itself and expects raw output, not a message list. Eval loaders
return message lists because promptfoo makes the HTTP call there. The two interfaces are
incompatible; hence separate files.

Both import from `PROMPTS_REGISTRY` to stay in sync with production prompts.

### Content filtering

The LLM gateway may return HTTP 400 for adversarial probes caught by content filters. The provider
returns `[CONTENT_FILTERED_BY_GATEWAY]` in that case — the probe is scored as a pass (the model was
protected) rather than crashing the run.

### Structure

```
redteam/
├── configs/         # Authored source configs — purpose, plugins, target definition
│   └── planner.yaml
├── probes/          # Generated + committed probe files — run these in CI
│   └── planner.yaml
├── providers/
│   └── redteam_loaders.py  # Full provider: owns the HTTP call, returns {"output": "..."}
└── results/         # Scan results (gitignored)
```

### Adding a new agent to redteam

1. **Create `configs/<agent>.yaml`** — copy `configs/planner.yaml`, update:
   - `description`
   - `targets`: point to your provider function in `redteam_loaders.py`
   - `purpose`: describe what the agent does and what it must never do (be specific — this drives
     probe quality)
   - `plugins`: keep `prompt-extraction`, `hallucination`, `overreliance`, `excessive-agency`; add
     others if relevant
   - `outputPath`: `../results/redteam_<agent>.json`

2. **Add a provider function** in `providers/redteam_loaders.py`:

   ```python
   def my_agent(prompt: str, options: dict, context: dict) -> dict:
       return _call_llm([
           {"role": "system", "content": PROMPTS_REGISTRY["my_agent"]},
           {"role": "user", "content": prompt},
       ])
   ```

3. **Generate probes**:

   ```sh
   task screen:redteam:generate AGENT=my_agent
   ```

4. **Commit `probes/my_agent.yaml`** — CI will pick it up automatically (the scheduled job globs
   `probes/*.yaml`).

5. Verify locally: `task screen:redteam:eval AGENT=my_agent`

---

## CI

| Job                   | Trigger                                                           | Blocks merge               |
| --------------------- | ----------------------------------------------------------------- | -------------------------- |
| `screen:evals`        | MR — when `app/agents/prompts/**` or `tests/promptfoo/**` changes | No (`allow_failure: true`) |
| `screen:redteam:eval` | Scheduled pipeline with `REDTEAM_SCHEDULE=true`                   | No (`allow_failure: true`) |

Evals are advisory in CI today — they surface regressions without blocking. Promote A-tier failures
to blocking once the baseline is stable.

---

## Environment variables

| Variable                 | Purpose                                                    |
| ------------------------ | ---------------------------------------------------------- |
| `PROMPTFOO_LLM_API_KEY`  | Eval-specific API key (falls back to `LLM_API_KEY`)        |
| `PROMPTFOO_LLM_BASE_URL` | Eval-specific gateway URL (falls back to `LLM_BASE_URL`)   |
| `LLM_MODEL`              | Model for redteam target calls (default: `gpt-5.1-sweden`) |

Local: set `PROMPTFOO_LLM_API_KEY`/`PROMPTFOO_LLM_BASE_URL` in `.env`, or leave unset to fall back
to `LLM_API_KEY`/`LLM_BASE_URL`. CI: injected automatically as GitLab project variables.
