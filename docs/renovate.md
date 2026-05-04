# Renovate — Automated dependency management

Renovate monitors and updates the monorepo's dependencies (Python, Node, PHP, Docker images) so the team spends less time on manual patch bumps and gets security fixes the day they're published.

The configuration lives at [`renovate.json`](../renovate.json) at the repo root. The CI job is defined in [`.gitlab-ci.yml`](../.gitlab-ci.yml) under `main:renovate`. This document explains how it runs, what it touches, and what a maintainer needs to do once to get it live.

## How it runs

Renovate is self-hosted via GitLab CI (no Mend.io hosted app). A scheduled pipeline with `JOB_TYPE=renovate` triggers the `main:renovate` job, which runs the upstream `renovate/renovate:41` image against this repo.

| Trigger             | When                                                                    | Who         |
| ------------------- | ----------------------------------------------------------------------- | ----------- |
| GitLab schedule     | Daily at 02:00 Europe/Paris                                             | Automatic   |
| Manual pipeline run | On demand (`CI/CD → Pipelines → Run pipeline` with `JOB_TYPE=renovate`) | Maintainers |

Renovate opens MRs on `renovate/*` branches. These branches are explicitly excluded from staging deployments — see the rule in `infra/.gitlab-ci.yml` on the `staging-deploy` job.

## Scope

| Module                 | Manager            | Manifest                      | Lockfile        |
| ---------------------- | ------------------ | ----------------------------- | --------------- |
| `apps/screen/`         | Poetry             | `pyproject.toml`              | `poetry.lock`   |
| `apps/global-service/` | Poetry             | `pyproject.toml`              | `poetry.lock`   |
| `apps/stream/`         | Poetry             | `pyproject.toml`              | `poetry.lock`   |
| `apps/front/`          | Yarn (npm manager) | `package.json`                | `yarn.lock`     |
| `apps/target/`         | Composer           | `composer.json`               | `composer.lock` |
| `infra/` + all apps    | Docker / Compose   | `Dockerfile`, `compose*.yaml` | —               |
| Root                   | GitLab CI          | `.gitlab-ci.yml`              | —               |

Managers not in this list (Kubernetes, Helm, Go modules, etc.) are disabled via `enabledManagers` to avoid false positives.

## Anti-flood limits

| Setting             | Value                                      | Rationale                                                    |
| ------------------- | ------------------------------------------ | ------------------------------------------------------------ |
| `prConcurrentLimit` | 5                                          | Max open Renovate MRs at once                                |
| `prHourlyLimit`     | 2                                          | Max MRs created per hour                                     |
| `minimumReleaseAge` | 3 days                                     | Wait for releases to stabilize (bypassed for security fixes) |
| `schedule`          | Nights (10pm–5am) + weekends, Europe/Paris | Keep dependency noise out of working hours                   |

## Grouping strategy

Related packages are grouped into a single MR to reduce review load. Each group
also carries a **module label** (`target`, `frontend`, `screen`,
`global-service`, `stream`) so `assign_reviewers.py` routes the MR to the right
CODEOWNERS — see `.gitlab/scripts/assign_reviewers.py`.

The `group:monorepos` preset (in `extends`) additionally groups packages
released from a single upstream monorepo (e.g. `@vue/*`, `@pinia/*`, …).

| Group                          | Packages                                                                                                | Module label                         | Schedule                           |
| ------------------------------ | ------------------------------------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------- |
| Symfony (target)               | `symfony/**`                                                                                            | `target`                             | Monday mornings                    |
| Doctrine (target)              | `doctrine/**`                                                                                           | `target`                             | Default                            |
| API Platform (target)          | `api-platform/**`                                                                                       | `target`                             | Default                            |
| PHPStan (target)               | `phpstan/**`                                                                                            | `target`                             | Default — automerge on patch/minor |
| PHP dev tooling (target)       | `phpunit/**`, `rector/**`, `zenstruck/**`, `symplify/easy-coding-standard`, `dama/doctrine-test-bundle` | `target`                             | Default — automerge on patch/minor |
| Vue ecosystem (front)          | `vue`, `@vue/**`, `vue-router`, `vue-i18n`                                                              | `frontend`                           | Default                            |
| VueUse (front)                 | `@vueuse/**`                                                                                            | `frontend`                           | Default — automerge on patch/minor |
| Pinia ecosystem (front)        | `pinia`, `@pinia/**`                                                                                    | `frontend`                           | Default                            |
| Tailwind (front)               | `tailwindcss`, `@tailwindcss/**`                                                                        | `frontend`                           | Default                            |
| Vitest (front)                 | `vitest`, `@vitest/**`, `@vue/test-utils`                                                               | `frontend`                           | Default — automerge on patch/minor |
| Vuellar (front)                | `@owlint/**`                                                                                            | `frontend`                           | Default                            |
| JavaScript dev tooling (front) | `eslint`, `@eslint/**`, `prettier`, `stylelint`, `typescript`                                           | `frontend`                           | Default — automerge on patch/minor |
| FastAPI + SQLAlchemy (python)  | `fastapi`, `sqlalchemy`, `alembic`, `pydantic`, `asyncpg`, `celery`, `langgraph`, `langchain-openai`, … | `screen`, `global-service`, `stream` | Default                            |
| Python dev tooling             | `ruff`, `mypy`, `black`, `pytest*`, `aiosqlite`                                                         | `screen`, `global-service`, `stream` | Default — automerge on patch/minor |
| Docker base images             | Any `dockerfile` / `docker-compose` update                                                              | (default)                            | Weekends only                      |

Packages outside these groups get individual MRs.

## Automerge policy

Automerge uses GitLab's native "Merge when pipeline succeeds" (`platformAutomerge: true`). A MR merges itself only if:

1. It's a **patch** or **minor** update.
2. The package belongs to one of the dedicated tooling groups above (PHPStan, PHP dev tooling, Vitest, JS dev tooling, Python dev tooling, VueUse).
3. The full CI pipeline is green.
4. The release is at least 3 days old.

**Never automerged** (manual review required):

- Any **major** update, regardless of package.
- Critical frameworks: `symfony/**`, `doctrine/**`, `api-platform/**`, `vue`, `@vue/**`, `vue-router`, `pinia`, `fastapi`, `sqlalchemy`, `alembic`, `pydantic`, `langgraph`.
- Lockfile maintenance MRs (monthly).

## Security updates

Vulnerability alerts bypass the grouping and scheduling rules entirely:

- Created **immediately** when detected (`prCreation: immediate`, `schedule: at any time`).
- Labels: `security`, `priority`, `dependencies`.
- No `minimumReleaseAge` delay.

## Commit convention

Renovate commits use this prefix (configured in `renovate.json`):

```
📦 chore(deps): update <package> to vX.Y.Z
```

This passes `commitlint.config.mjs`: the gitmoji prefix is required for all commits, and `chore`-type commits are exempt from the `TAR-xxx` Jira ticket requirement.

## One-time setup (maintainer)

These steps must be done in the GitLab UI before Renovate can run.

> **Note** — the four CI variables below (`RENOVATE_TOKEN`, `OWLINT_REGISTRY_URL`,
> `OWLINT_DEPLOY_KEY`, `RENOVATE_GITHUB_COM_TOKEN`) are already provisioned on this
> project. The steps are kept for reference/disaster recovery.

### 1. CI variables

**Settings → CI/CD → Variables** — already configured:

| Key                         | Purpose                                                                                                               | Required scopes / format                                                                                |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `RENOVATE_TOKEN`            | GitLab bot token used by Renovate to open MRs                                                                         | PAT or group bot token, scopes `api` + `write_repository`, role **Maintainer**, ✅ Masked, ✅ Protected |
| `OWLINT_REGISTRY_URL`       | Private `@owlint` npm registry URL (Vuellar) — see `apps/front/.yarnrc.yml`                                           | e.g. `https://git.fenrys.io/api/v4/projects/454/packages/npm`, ✅ Masked                                |
| `OWLINT_DEPLOY_KEY`         | Read token for the `@owlint` registry                                                                                 | Deploy token with `read_package_registry` scope, ✅ Masked, ✅ Protected                                |
| `RENOVATE_GITHUB_COM_TOKEN` | GitHub PAT used to fetch upstream release notes (Vue, Pinia, Symfony, Tailwind, …) and avoid the anonymous rate limit | GitHub PAT, scope `public_repo` (read-only), ✅ Masked, ✅ Protected                                    |

The `OWLINT_*` pair is referenced from `renovate.json` via `hostRules` +
`{{ secrets.OWLINT_REGISTRY_URL }}` / `{{ secrets.OWLINT_DEPLOY_KEY }}`, injected
into the job through the `RENOVATE_SECRETS` env var (see `.gitlab-ci.yml`).

`RENOVATE_GITHUB_COM_TOKEN` is mapped to the conventional `GITHUB_COM_TOKEN`
variable that Renovate reads natively.

### 2. Create the 4 MR labels

**Settings → Labels → New label** — create each:

| Label          | Color     |
| -------------- | --------- |
| `dependencies` | `#0366d6` |
| `renovate`     | `#1e90ff` |
| `security`     | `#ee0701` |
| `priority`     | `#d93f0b` |

### 3. Create the scheduled pipeline

**CI/CD → Schedules → New schedule**

- Description: `Renovate dependency updates`
- Interval pattern: custom → `0 2 * * *`
- Cron timezone: `Europe/Paris`
- Target branch: `main`
- Active: ✅
- Variable: `JOB_TYPE` = `renovate`

### 4. Enable "Merge when pipeline succeeds"

**Settings → Merge requests → Merge options** — check **"Enable merge when pipeline succeeds"**. Without this, automerge will silently not trigger.

## Testing the configuration

Before merging a change to `renovate.json`:

```bash
# Validate JSON syntax + Renovate schema
docker run --rm -v "$PWD":/repo -w /repo renovate/renovate:41 \
  renovate-config-validator renovate.json
```

For a dry-run against a real fork (no MRs created):

```bash
docker run --rm \
  -e RENOVATE_PLATFORM=gitlab \
  -e RENOVATE_ENDPOINT=https://git.mediaspeech.com/api/v4 \
  -e RENOVATE_TOKEN=$MY_PAT \
  -e LOG_LEVEL=debug \
  -v "$PWD/renovate.json":/usr/src/app/renovate.json \
  renovate/renovate:41 \
  --dry-run=full \
  chapsmind/chapsmind
```

After merge, trigger a manual run: **CI/CD → Pipelines → Run pipeline** on `main` with `JOB_TYPE=renovate`. Check the `main:renovate` job logs for `Dependency extraction complete` entries per manager.

## Troubleshooting

### No MRs after a scheduled run

1. Is the Dependency Dashboard issue closed? Reopen it.
2. Check `main:renovate` job logs — look for `ERROR` or `FATAL` entries.
3. Verify `RENOVATE_TOKEN` hasn't expired.
4. Check the schedule is active (CI/CD → Schedules).

### Automerge not working

1. Is "Merge when pipeline succeeds" enabled in project settings?
2. Does the MR have required approvals that aren't satisfied?
3. Is the package on the automerge whitelist? (see `packageRules` in `renovate.json`)
4. Has `minimumReleaseAge` (3 days) elapsed since the release?

### Too many MRs

Lower `prConcurrentLimit` / `prHourlyLimit`, extend `minimumReleaseAge`, or add more group rules to bundle related updates.

### A specific package keeps getting updated / should be pinned

Add it to `ignoreDeps` in `renovate.json`, or comment on the MR with `@renovatebot ignore this dependency`.

### Dependency Dashboard is missing

It's auto-created as a GitLab issue the first time Renovate runs. Find it under **Issues** filtered by the `renovate` label. If it was closed, reopen it to resume all updates.

## Contributing to the config

1. Create a feature branch: `chore/TAR-xxxx-tweak-renovate`.
2. Edit `renovate.json`.
3. Validate locally: `renovate-config-validator renovate.json`.
4. Trigger a manual dry-run pipeline (`JOB_TYPE=renovate` on the feature branch) to inspect what would change.
5. Open a MR — the regular CI (lint, tests) will run as usual.
6. After merge, the next scheduled run picks up the new rules.

## References

- [Renovate documentation](https://docs.renovatebot.com/)
- [Configuration options](https://docs.renovatebot.com/configuration-options/)
- [Package rules](https://docs.renovatebot.com/configuration-options/#packagerules)
- Staging deploy exclusion: `infra/.gitlab-ci.yml` (`staging-deploy` rule on `renovate/*` branches)
- Commit gate: `commitlint.config.mjs`
