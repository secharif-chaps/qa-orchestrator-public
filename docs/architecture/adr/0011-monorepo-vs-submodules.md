# ADR-0011: Monorepo vs Git Submodules

## Status

**Status:** Accepted

**Date:** 2026-02-25

**Decision Makers:** Engineering Team

**Tags:** architecture, git, monorepo, dx

---

## 1. Context and Problem

The ChapsMind project was previously organized as **4 git submodules** (soon 6) within a workspace parent repository:

| Submodule | Stack | Commits | Active branches |
|---|---|---|---|
| `front` | Vue 3 / TypeScript | 641 | 22 |
| `back` | FastAPI / Python | 525 | 23 |
| `infra` | Docker / K8s | 56 | 11 |
| `global-service` | Python | 3 | 3 |
| *(planned)* `target-service` | Python | - | - |
| *(planned)* `screen-service` | Python | - | - |

**Organization**: 2 feature-oriented teams that work across all repos.

### Observed Problems

1. **47% of workspace commits are maintenance noise** (35/74 commits = "update submodule pointers")
2. **3 out of 4 submodules are desynchronized** at audit time (refs ahead of workspace parent)
3. **No defined git flow** -- no shared convention between teams
4. **Cross-stack features require 3 MRs** (front + back + workspace bump)
5. **16 feature scopes are shared between front and back** (admin, companies, folders, tasks, team, translation, tokens, etc.)

---

## 2. Options Evaluated

### Option A: Stay with Submodules (with improvements)
### Option B: Monorepo
### Option C: Pure multi-repo (without workspace parent)

---

## 3. Decision Matrix

### 3.1. Developer Experience (DX) Criteria

| Criterion | Weight | Submodules (A) | Monorepo (B) | Multi-repo (C) |
|---|---|---|---|---|
| **New dev onboarding** | High | 2/5 -- `git clone --recurse`, understand refs | **5/5** -- single `git clone` | 3/5 -- clone N repos |
| **Cross-stack feature (1 MR)** | High | 1/5 -- 3 MRs minimum (front + back + bump) | **5/5** -- 1 MR, 1 branch | 1/5 -- N MRs |
| **Unified review** | High | 1/5 -- review in 3 separate repos | **5/5** -- 1 MR, complete diff | 1/5 -- split context |
| **No ref maintenance** | High | 1/5 -- 47% of commits = maintenance | **5/5** -- eliminates the problem | 4/5 -- no refs but no link |
| **History / git blame / bisect** | Medium | 2/5 -- fragmented by repo | **5/5** -- unified | 1/5 -- fragmented |
| **Cross-repo code search** | Medium | 2/5 -- grep in each submodule | **5/5** -- global grep | 1/5 -- external tools |
| **CI complexity** | Medium | 3/5 -- CI per repo, simple | 4/5 -- `include:local` per app, separation of concerns | **4/5** -- independent CIs |
| **Deployment autonomy** | Medium | 4/5 -- independent deploy per repo | 3/5 -- conditional but possible | **5/5** -- natural |
| **Scalability (50+ services)** | Low | 3/5 -- each repo is small | 3/5 -- clone grows | **5/5** -- each repo stays small |
| **Cross-stack refactoring** | High | 1/5 -- manual coordination | **5/5** -- atomic | 1/5 -- manual coordination |

### 3.2. Weighted Score

| | Submodules (A) | Monorepo (B) | Multi-repo (C) |
|---|---|---|---|
| **Raw score** (sum) | 20 | **46** | 26 |
| **Weighted score** (x weight) | 28 | **67** | 35 |

> **Monorepo dominates on all high-weight criteria**, which correspond exactly to current pain points.

### 3.3. Eliminatory Criteria

| Eliminatory criterion | Submodules | Monorepo | Multi-repo |
|---|---|---|---|
| Feature teams touch multiple repos? | Yes -> friction | **Yes -> natural** | Yes -> friction |
| Would repo size block cloning? | No | **No (< 50 MB)** | No |
| GitLab CI supports conditional execution? | N/A | **Yes (`rules:changes`)** | N/A |
| More than 2 services to be added soon? | Worsens the problem | **Absorbs naturally** | Multiplies repos |

---

## 4. Git Flow Analysis

### 4.1. Current Situation: No Convention

Observations:
- `feat/*` and `fix/*` branches exist in front and back but without a shared convention
- Branches do not share names between front and back for the same feature
- Dead branches: `develop`, `pipeline`, `origin` linger in both repos
- No documented branch protection
- No CODEOWNERS

### 4.2. Proposed Git Flow (with Monorepo)

#### Branches and Environments

```
main (protected)                   <- Auto merge -> deploy integration
  |
  +-- feat/TAR-xxx-description     <- Feature branch (front + screen + infra)
  +-- fix/TAR-xxx-description      <- Bug fix
  +-- refactor/description         <- Refactoring
  +-- chore/description            <- Maintenance

release/preprod                    <- Tag/branch -> deploy preprod (business validation)
release/prod                       <- Tag/branch -> deploy prod (manual promotion)
```

#### Environment Promotion Pipeline

```
Feature branch        main               preprod             prod
     |                  |                    |                  |
     |   MR + review    |                    |                  |
     +----------------->|                    |                  |
     |                  |  auto deploy       |                  |
     |                  +------------------->|                  |
     |                  |                    |  manual          |
     |                  |                    |  promotion       |
     |                  |                    +----------------->|
     |                  |                    |                  |
   CI: lint           CI: build +         CI: build +        CI: build +
   + test             tag images          tag preprod        tag prod
   + build            + deploy integ      + deploy preprod   + deploy prod
```

| Environment | Branch/Trigger | Deploy | Purpose |
|---|---|---|---|
| **Integration** | `main` (auto on merge) | Automatic (CD) | Continuous technical validation |
| **Preprod** | Tag or `release/*` branch | Automatic (CD) | Business validation, QA |
| **Prod** | Manual promotion | Manual (button) | Production, zero downtime |

**CD objective**: eventually, merging to `main` triggers the full chain up to prod, with validation gates (E2E tests, smoke tests) between each stage.

#### Rules

| Rule | Description |
|---|---|
| **Single branch per feature** | `feat/TAR-xxx-description` touches `apps/front/` AND `apps/screen/` if needed |
| **Single MR** | 1 feature = 1 MR, even if it touches 3 apps |
| **Ticket prefix** | Always include Jira/GitLab number (`TAR-xxx`) |
| **No commit on main** | Everything goes through MR |
| **CODEOWNERS review** | Automatic approval required per touched domain |
| **CI must be green** | Merge blocked if pipeline fails |
| **Squash merge** | Clean history on main |
| **main = always deployable** | Every commit on main must be production-ready |

### 4.3. Flow Comparison by Approach

#### Cross-stack feature: "Add translation module"

**Submodules (previous)**:
```
1. git checkout -b feat/translation        <- in front/
2. ... dev + commit + push + MR front
3. git checkout -b feat/translation        <- in back/
4. ... dev + commit + push + MR back
5. Wait for front merge
6. Wait for back merge
7. cd workspace/
8. git submodule update
9. git add front back
10. git commit -m "bump submodules"
11. git push + MR workspace
12. Merge workspace

-> 3 MRs, 3 reviews, 12 steps, manual coordination
-> Risk: someone merges in between and breaks refs
```

**Monorepo (current)**:
```
1. git checkout -b feat/TAR-42-translation
2. ... modify apps/front/ + apps/screen/
3. git add + commit + push
4. Single MR with complete diff
5. Review: front team + back team (via CODEOWNERS)
6. Merge

-> 1 MR, 1 review, 6 steps, atomic
-> No desynchronization risk
```

**Gain**: 50% fewer steps, zero risk of broken refs, complete review.

#### Urgent hotfix: "Production API bug"

**Submodules**:
```
1. cd back/ && git checkout -b fix/api-bug
2. Fix + commit + push + MR
3. Wait for review + merge
4. cd workspace/ && git submodule update
5. Bump + push + MR workspace
6. Wait for workspace merge
7. Deploy

-> Latency: 2 review cycles
```

**Monorepo**:
```
1. git checkout -b fix/TAR-99-api-bug
2. Fix in apps/screen/ + commit + push + MR
3. Review + merge
4. Deploy

-> Latency: 1 review cycle
```

**Gain**: Hotfix reaches production 2x faster.

---

## 5. CI/CD Impact

### 5.1. Previous CI

The front and back CIs were nearly identical: Docker build + push on main.
No tests, no lint, no type-check in CI.

### 5.2. Distributed CI Architecture (`include:local`)

Each app owns its own `.gitlab-ci.yml` with its business logic. The root file orchestrates via `include:local`. This isolates concerns: each team maintains its CI independently.

#### File Structure

```
chapsmind/
+-- .gitlab-ci.yml                      <- Orchestrator: stages + includes
+-- apps/
|   +-- front/.gitlab-ci.yml            <- Front CI (lint, typecheck, build, deploy)
|   +-- screen/.gitlab-ci.yml           <- Screen CI (lint, test, build, deploy)
|   +-- global-service/.gitlab-ci.yml   <- Global service CI
|   +-- ...
+-- infra/.gitlab-ci.yml                <- Infra CI (validation)
```

#### Root File: `.gitlab-ci.yml`

```yaml
stages:
  - lint
  - test
  - build
  - deploy:integration
  - deploy:preprod
  - deploy:prod

include:
  - local: apps/front/.gitlab-ci.yml
  - local: apps/screen/.gitlab-ci.yml
  - local: apps/global-service/.gitlab-ci.yml
  - local: infra/.gitlab-ci.yml
```

> Adding a new service = adding one `include` line + creating the CI file in the service directory.

#### App File: `apps/front/.gitlab-ci.yml`

```yaml
# --- Frontend CI -----------------------------------------
# Maintained by the front team, isolated from the rest

.front-changes: &front-changes
  changes:
    - apps/front/**/*

front:lint:
  stage: lint
  rules:
    - <<: *front-changes
  script:
    - cd apps/front && yarn install --immutable && yarn lint

front:typecheck:
  stage: lint
  rules:
    - <<: *front-changes
  script:
    - cd apps/front && yarn install --immutable && yarn vue-tsc --noEmit

front:build:
  stage: build
  rules:
    - <<: *front-changes
  image: docker:29-cli
  script:
    - docker build -t $CI_REGISTRY_IMAGE/chapsmind-front:$CI_COMMIT_SHORT_SHA apps/front/
    - docker push $CI_REGISTRY_IMAGE/chapsmind-front:$CI_COMMIT_SHORT_SHA

# --- Deployment stages -----------------------------------
front:deploy:integration:
  stage: deploy:integration
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      <<: *front-changes
  environment:
    name: integration
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-front:integration
    - docker push $CI_REGISTRY_IMAGE/chapsmind-front:integration
    # kubectl set image ... or ArgoCD sync

front:deploy:preprod:
  stage: deploy:preprod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *front-changes
  environment:
    name: preprod
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-front:preprod
    - docker push $CI_REGISTRY_IMAGE/chapsmind-front:preprod

front:deploy:prod:
  stage: deploy:prod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *front-changes
  environment:
    name: production
  when: manual  # Manual gate -> explicit promotion
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-front:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-front:prod
    - docker push $CI_REGISTRY_IMAGE/chapsmind-front:prod
```

#### App File: `apps/screen/.gitlab-ci.yml`

```yaml
# --- Screen Backend CI -----------------------------------
# Maintained by the back team, isolated from the rest

.screen-changes: &screen-changes
  changes:
    - apps/screen/**/*

screen:lint:
  stage: lint
  rules:
    - <<: *screen-changes
  script:
    - cd apps/screen && ruff check .

screen:test:
  stage: test
  rules:
    - <<: *screen-changes
  script:
    - cd apps/screen && pytest

screen:build:
  stage: build
  rules:
    - <<: *screen-changes
  image: docker:29-cli
  script:
    - docker build -t $CI_REGISTRY_IMAGE/chapsmind-screen:$CI_COMMIT_SHORT_SHA apps/screen/
    - docker push $CI_REGISTRY_IMAGE/chapsmind-screen:$CI_COMMIT_SHORT_SHA

screen:deploy:integration:
  stage: deploy:integration
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      <<: *screen-changes
  environment:
    name: integration
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-screen:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-screen:integration
    - docker push $CI_REGISTRY_IMAGE/chapsmind-screen:integration

screen:deploy:preprod:
  stage: deploy:preprod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *screen-changes
  environment:
    name: preprod
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-screen:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-screen:preprod
    - docker push $CI_REGISTRY_IMAGE/chapsmind-screen:preprod

screen:deploy:prod:
  stage: deploy:prod
  rules:
    - if: $CI_COMMIT_TAG =~ /^v.*/
      <<: *screen-changes
  environment:
    name: production
  when: manual
  script:
    - docker tag $CI_REGISTRY_IMAGE/chapsmind-screen:$CI_COMMIT_SHORT_SHA $CI_REGISTRY_IMAGE/chapsmind-screen:prod
    - docker push $CI_REGISTRY_IMAGE/chapsmind-screen:prod
```

### 5.3. Advantages of Distributed CI Architecture

| Advantage | Detail |
|---|---|
| **Separation of concerns** | Each team maintains its CI in its own directory |
| **No monolithic CI file** | Root `.gitlab-ci.yml` stays < 15 lines |
| **Trivial service addition** | 1 `include` line + 1 `.gitlab-ci.yml` file in the new directory |
| **Conditional execution** | Only jobs for modified directories run (`rules:changes`) |
| **3 environments** | integration (auto), preprod (auto on tag), prod (manual promotion) |
| **Path to full CD** | Replace `when: manual` with automated gates (smoke tests, E2E) |

---

## 6. Automatic Domain-Based Review

### The Need

In a monorepo, an MR can touch `apps/front/` AND `apps/screen/`. A mechanism is needed to **automatically assign the right reviewers** based on modified files, without the author having to think about it.

### Native GitLab Solution: CODEOWNERS (Premium only)

GitLab natively offers the `CODEOWNERS` file that associates paths to owners and blocks merge without their approval. **This feature requires GitLab Premium** -- not available in our case.

### Chosen Alternative: Auto-assign Reviewers via CI

A custom Python script (`scripts/ci/assign_reviewers.py`) reproduces the CODEOWNERS behavior on GitLab Free:

1. A `CODEOWNERS` file is defined in the repo (same syntax as Premium)
2. A CI job triggers on every MR
3. The job analyzes modified files, matches owners, and **assigns reviewers via the GitLab API**

#### CODEOWNERS File

```
# .gitlab/CODEOWNERS
# Same syntax as GitLab Premium -- used by CI job

# Frontend
apps/front/              @chapsmind/team-front

# Screen Backend
apps/screen/             @chapsmind/team-back

# Services
apps/global-service/     @chapsmind/team-back

# Infrastructure
infra/                   @chapsmind/team-devops

# Documentation
docs/                    @chapsmind/team-leads
```

#### CI Auto-Assignment Job

```yaml
# In root .gitlab-ci.yml (or a dedicated include)

assign-reviewers:
  stage: lint
  image: python:3.12-slim
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
  script:
    - pip install requests
    - python scripts/ci/assign_reviewers.py
  variables:
    GITLAB_TOKEN: $REVIEWER_BOT_TOKEN  # Bot token with API access
```

The script (`scripts/ci/assign_reviewers.py`) parses the `CODEOWNERS` file, retrieves modified files via the MR API, and calls `PUT /projects/:id/merge_requests/:iid` to add `reviewer_ids`.

> Alternative options:
> - [Axolo](https://axolo.co/auto-assign-reviewer-for-gitlab) -- free SaaS that reads CODEOWNERS and assigns reviewers automatically via Slack
> - Custom bash script with `curl` + GitLab API

#### Concrete Example

An MR `feat/TAR-42-translation` modifies:
- `apps/front/src/components/TranslationPanel.vue`
- `apps/screen/app/api/translation.py`

The CI job detects that `apps/front/` and `apps/screen/` are touched -> automatically assigns `@chapsmind/team-front` and `@chapsmind/team-back` as reviewers on the MR.

#### Limitations Compared to GitLab Premium

| Feature | GitLab Premium | CI Alternative |
|---|---|---|
| Auto reviewer assignment | Native | Via CI job or Axolo |
| **Merge blocking** without owner approval | Native | No -- team convention only |
| Configuration | CODEOWNERS file only | CODEOWNERS + CI job + bot token |

Merge blocking is not possible without Premium. We compensate with a **team convention**: do not merge until all assigned reviewers have approved. If the team upgrades to GitLab Premium in the future, the CODEOWNERS file is already in place -- just enable the feature.

---

## 7. Monorepo Tooling (Nx, Turborepo, Bazel): Not Needed Today

### Why We Don't Need It

Monorepo tools (Nx, Turborepo, Bazel, Pants) solve three main problems: the **dependency graph** between shared packages, intelligent **task caching**, and **affected project detection** on changes.

In our case, these three problems are already covered or non-existent:

| Problem | Our situation | Covered by |
|---|---|---|
| Inter-app dependency graph | No shared code between front (Vue/TS) and back (Python) | N/A |
| Task caching | Each app = `docker build` | Docker layer cache |
| Affected detection | Which service to rebuild? | GitLab CI `rules:changes` (native, free) |
| Build performance | < 50 MB of code, 5-6 services | No performance issue |

Adding Nx would require installing Node.js as a prerequisite for Python devs (just to run Nx), writing custom plugins for FastAPI, and maintaining extra configuration -- all without measurable gain.

Turborepo is exclusively JS/TS, therefore incompatible with our Python backends. Bazel solves scale problems (hermetic builds, distributed execution) that don't apply at our scale.

### When to Reevaluate

Reconsider adding a tool if any of these signals appear:

| Signal | Tool to evaluate |
|---|---|
| Creation of **shared libs between Python services** (Pydantic schemas, internal SDK) | Nx with Python plugin, or Pants |
| **20+ services** and CI > 15 min | Nx for remote caching + affected graph |
| Need for **reproducible hermetic builds** (compliance, security) | Bazel |
| **Shared TypeScript types** between front and a potential Node BFF | Nx or Turborepo |

---

## 8. Risks and Mitigations

### 8.1. Migration Risks

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| **Loss of git history** | Low | High | `git subtree add` preserves the full history (commits, authors, dates). Validate on a test repo before the real migration. Old repos are archived, not deleted -- we can always go back. |
| **In-progress branches lost** | Medium | High | Plan migration after a "freeze": all in-progress MRs are merged or paused. Active branches are recreated in the monorepo after import. |
| **Dockerfiles/paths broken** | High | Medium | `COPY`, `context:` and volumes in Dockerfiles and docker-compose change (e.g. `./` -> `./apps/screen/`). Prepare a checklist of all paths to update. Test `docker compose up` before the switch. |
| **Resistance to change** | Medium | Medium | Present this ADR to the team. Do a live demo of monorepo DX (1 clone, 1 branch, 1 cross-stack MR). Progressive migration: work in monorepo in parallel with old repos for 1-2 sprints. |

### 8.2. Day-to-Day Monorepo Risks

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| **More complex CI** | Medium | Low | `include:local` architecture: each app manages its own CI in its directory. Root file stays < 15 lines. Each team is autonomous on its CI. |
| **Repo too large over time** | Very low | Low | Current repos < 50 MB combined. The problem appears beyond ~1 GB. With 6 services, we're far from that. If needed later: `git sparse-checkout` to clone only a subset. |
| **More frequent merge conflicts** | Low | Low | Teams touch different directories (`apps/front/` vs `apps/screen/`). Conflicts on shared files (docker-compose, root CI) are rare and easy to resolve. |
| **Loss of deployment autonomy** | Low | Medium | Conditional CI (`rules:changes`): only modified services are rebuilt and deployed. A merge touching only `apps/front/` triggers no screen job. Same de facto isolation as separate repos. |
| **MRs too large (cross-stack)** | Medium | Low | Convention: if an MR exceeds ~500 lines, split into sequential MRs (e.g. MR1 = screen API, MR2 = front UI). Monorepo doesn't prevent single-scope MRs. |
| **No merge blocking by owner (Free)** | - | Medium | Auto reviewer assignment is handled by a CI job (see section 6). Merge blocking remains a team convention. CODEOWNERS file is ready if migration to Premium happens. |

### 8.3. Risks of NOT Migrating (Status Quo)

| Risk | Probability | Impact | Detail |
|---|---|---|---|
| **Perpetually desynchronized submodule refs** | High | Medium | Observed today: 3/4 submodules ahead of workspace. Nobody maintains the refs. Problem worsens with each new service. |
| **Growing maintenance overhead** | High | Medium | Today 47% of commits = ref maintenance. With 6 services: ~60% noise projected. |
| **Slowed cross-stack features** | High | High | 3 MRs per feature, manual coordination, desync risk. With 16 shared scopes between front and back, this is the majority of features. |
| **No git flow possible** | High | High | Impossible to define a single flow when each repo has its own branches. Integration/preprod/prod environments require manual synchronization between repos. |
| **Painful onboarding** | Medium | Medium | Each new dev must understand submodules, refs, bumping. Frequent source of errors. |

---

## 9. Migration Plan

### Phase 1: Preparation (non-blocking)

- [x] Validate the decision with the team
- [x] Create the monorepo on GitLab
- [x] Define CODEOWNERS groups

### Phase 2: History Import

- [x] Import all repos via `git subtree add` (front, screen, global-service, infra)
- [x] Copy docs, .claude, agent-os from workspace parent

### Phase 3: CI and Protections

- [x] Write unified `.gitlab-ci.yml` (root orchestrator + 4 app CI files)
- [x] Set up CODEOWNERS + auto-assign reviewers CI job
- [x] Set up husky + lint-staged for pre-commit hooks
- [x] Set up commitlint (gitmoji + conventional commits)
- [x] Add `.dockerignore` files for all apps
- [x] Add `.editorconfig`, `.gitattributes`, `.env.example`
- [x] Add `CONTRIBUTING.md` onboarding guide
- [x] Add Dev Container configuration (`.devcontainer/`)
- [x] Add Taskfile (unified commands)
- [x] Configure ruff for all Python backends (`pyproject.toml`)
- [x] Add SAST + Secret Detection CI templates
- [ ] Configure `main` branch protection *(requires GitLab admin)*
- [ ] Add merge rules (green CI, approvals) *(requires GitLab admin)*

### Phase 4: Switch

- [ ] Freeze old repos (read-only) *(requires GitLab admin)*
- [ ] Team communication: "everything goes in the monorepo"
- [ ] Archive old repos on GitLab *(requires GitLab admin)*
- [x] Update README with new workflow (`CONTRIBUTING.md`)

---

## 10. Decision

**Recommendation: Option B -- Monorepo**

**Justification**:
1. Eliminates 47% of useless maintenance commits
2. Reduces cross-stack feature MRs from 3 to 1
3. Enables a single clear git flow for both teams
4. Naturally absorbs future service additions
5. Improves hotfix speed (1 review cycle instead of 2)
6. Makes unified review and CODEOWNERS possible
7. Weighted DX score: **67/75** vs 28/75 (submodules) vs 35/75 (multi-repo)
