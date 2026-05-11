# Definition of Ready (DoR)

A ticket can only enter a sprint if it satisfies **all** the mandatory criteria for its category.

## User Stories

### Mandatory criteria

| #   | Criterion                                                                                                   | CI Verification                                                           |
| --- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| 1   | **Single-module prefix** in title (`[TARGET]`, `[SCREEN]`, `[STREAM]`, `[EXPLORE]`, `[GLOBAL]`, `[LEGACY]`) | Title matches `^\[(TARGET\|SCREEN\|STREAM\|EXPLORE\|GLOBAL\|LEGACY)\] .+` |
| 2   | **Jira component** filled in, consistent with module prefix                                                 | `components` field not empty                                              |
| 3   | **Skill labels** assigned (`Back`, `Front`, `Prompt` — combinable)                                          | At least one label among `Back`, `Front`, `Prompt`                        |
| 4   | **User Story** complete: _As a / I want / So that_                                                          | Description contains all 3 blocks                                         |
| 5   | **Acceptance criteria** in _Given / When / Then_ format (at least main scenario + error case)               | Description contains at least 2 Given/When/Then blocks                    |
| 6   | **Scope** defined: _Included_ and _Excluded_ sections                                                       | Description contains both sections                                        |
| 7   | **Story points** estimated during refinement                                                                | `story_points` field > 0                                                  |
| 8   | **Parent epic** linked (if applicable)                                                                      | `epic` field filled or explicitly marked "None"                           |
| 9   | **Dependencies** identified (_Blocked by_ / _Blocks_)                                                       | Dependencies section present in description                               |
| 10  | **Achievable in 1 sprint**                                                                                  | Story points <= individual sprint capacity                                |

### Additional criteria by label

| Label    | Additional criterion                                             |
| -------- | ---------------------------------------------------------------- |
| `Front`  | Mockup or wireframe referenced if significant UI change          |
| `Prompt` | Versioned prompt template identified in `api/templates/prompts/` |
| `Back`   | Impacted API endpoints listed                                    |

## Bugs

### Mandatory criteria

| #   | Criterion                                                                                       | CI Verification                                                           |
| --- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| 1   | **Module prefix** of detection in title (where the bug is **detected**, not where it is caused) | Title matches `^\[(TARGET\|SCREEN\|STREAM\|EXPLORE\|GLOBAL\|LEGACY)\] .+` |
| 2   | **Jira component** filled in, consistent with prefix                                            | `components` field not empty                                              |
| 3   | **Skill label** assigned (`Back`, `Front`, `Prompt`)                                            | At least one label among `Back`, `Front`, `Prompt`                        |
| 4   | **Severity** assessed: Critical / Major / Minor / Cosmetic                                      | Description contains Severity field                                       |
| 5   | **Frequency** indicated: Systematic / Intermittent / Rare                                       | Description contains Frequency field                                      |
| 6   | **Reproduction steps** numbered                                                                 | Reproduction section with ordered list                                    |
| 7   | **Observed result** vs **Expected result** distinct                                             | Both sections present in description                                      |
| 8   | **Environment** complete (Env, Browser, OS, URL)                                                | Environment section with table                                            |
| 9   | **Story points** estimated                                                                      | `story_points` field > 0                                                  |
| 10  | **Impact** assessed (Users, Business impact, Workaround)                                        | Impact section present                                                    |

### Additional Legacy bug criteria

| #   | Criterion                                     |
| --- | --------------------------------------------- |
| 1   | `ST9_3` branch mentioned                      |
| 2   | File and line identified if available in logs |
| 3   | Proposed fix (suggestion, not mandatory)      |

## Cross-cutting rules

- **No title prefixed with `[BUG]` or `[STORY]`** — the Jira type already carries this information.
- **One ticket = one module.** If multi-module, split into linked tickets.
- **PO validation** obtained before sprint entry.
- **No implementation details** in stories (except Legacy) — focus on business need.

## CI Automation

Criteria marked "CI Verification" can be validated automatically via a Jira webhook or CI job on transition to _To do_ status:

```text
Transition → "Ready to do"
  ├── Verify title (module prefix regex)
  ├── Verify component not empty
  ├── Verify labels (Back|Front|Prompt)
  ├── Verify story points > 0
  ├── Verify description (mandatory sections)
  └── If KO → block transition + auto comment
```
