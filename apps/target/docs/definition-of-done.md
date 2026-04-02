# Definition of Done (DoD)

A ticket can only move to _Done_ status if it satisfies **all** the mandatory criteria for its category.

## User Stories

### Mandatory criteria

| #   | Criterion                                                                   | CI Verification                                                |
| --- | --------------------------------------------------------------------------- | -------------------------------------------------------------- |
| 1   | **Code merged** into `main` branch via approved Merge Request               | MR merged, feature branch deleted                              |
| 2   | **Code review** performed by at least 1 developer                           | MR has >= 1 approval                                           |
| 3   | **Unit tests** written for added/modified business logic                    | Coverage of new Domain/Application files > 0                   |
| 4   | **Integration tests** written for impacted API endpoints                    | New tests in `tests/Integration/` if endpoint created/modified |
| 5   | **All tests pass** (unit + integration)                                     | CI pipeline green                                              |
| 6   | **Linters pass** without errors (ECS, PHPStan, ESLint, Prettier, Stylelint) | CI `lint` job green                                            |
| 7   | **PHPStan level 9** passes without new errors                               | CI `phpstan` job green                                         |
| 8   | **No regression** on existing tests                                         | CI pipeline green, no broken tests                             |
| 9   | **Acceptance criteria** validated (Given/When/Then verified)                | Manual or automated test for each scenario                     |
| 10  | **Translations** up to date (EN + FR) if UI change                          | `locales/en.json` and `fr.json` files modified                 |
| 11  | **Doctrine migration** created if database schema change                    | Migration file present in `api/migrations/`                    |
| 12  | **OpenAPI documentation** up to date if endpoint created/modified           | Consistent API Platform annotations                            |

### Additional criteria by label

| Label    | Additional criterion                                                     |
| -------- | ------------------------------------------------------------------------ |
| `Front`  | Tested on Chrome and Firefox, responsive verified                        |
| `Front`  | RGAA 4.1 accessibility respected (labels, contrast, keyboard navigation) |
| `Prompt` | Versioned prompt template updated in `api/templates/prompts/`            |
| `Prompt` | AI output validated on a representative sample                           |
| `Back`   | Endpoints documented in OpenAPI with response examples                   |

## Bugs

### Mandatory criteria

| #   | Criterion                                           | CI Verification                                  |
| --- | --------------------------------------------------- | ------------------------------------------------ |
| 1   | **Code merged** into `main` via approved MR         | MR merged                                        |
| 2   | **Code review** performed                           | MR has >= 1 approval                             |
| 3   | **Non-regression test** added covering the bug case | New test that would have failed before the fix   |
| 4   | **All tests pass**                                  | CI pipeline green                                |
| 5   | **Linters and PHPStan pass**                        | CI jobs green                                    |
| 6   | **Bug not reproducible** after fix                  | Manual verification on ticket reproduction steps |
| 7   | **No side effects** on adjacent features            | Manual test of related flows                     |

### Additional Legacy bug criteria

| #   | Criterion                                            |
| --- | ---------------------------------------------------- |
| 1   | Fix on `ST9_3` branch only                           |
| 2   | Backward compatibility preserved                     |
| 3   | Manual test performed (no automated tests available) |
| 4   | Enriched logging if applicable                       |

## Cross-cutting rules

- **No warnings** introduced in CI (PHPStan, ESLint).
- **Conventional commits** respected (`feat:`, `fix:`, `refactor:`, etc.) with ticket reference (`TAR-XXXX`).
- **Feature branch deleted** after merge.
- **Jira ticket** updated: status _Done_, resolution comment if relevant.
- **No `console.log`**, `var_dump`, `dd()` or remaining debug code.
- **No secrets** committed (API keys, tokens, passwords).
- **Staging deployment** successful without Sentry errors.

## CI Automation

Criteria marked "CI Verification" are validated automatically in the GitLab pipeline:

```
Merge Request → CI Pipeline
  ├── task api:cs:check        → PHP code style
  ├── task api:phpstan:check   → Static analysis level 9
  ├── task api:test             → PHPUnit tests (unit + integration)
  ├── task pwa:eslint:check    → TypeScript/Vue linting
  ├── task lint                 → Prettier + Stylelint
  └── If KO → merge blocked

Post-merge → Staging deployment
  └── Sentry monitoring 24h → no new errors
```
