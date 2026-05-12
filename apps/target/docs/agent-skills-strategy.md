# Agent Skills Strategy - Audit & Action Plan

> **Date**: 2026-02-24
> **Author**: Lucas GAULT & Claude Code (automated audit)
> **Scope**: basil (27 skills) + chapsmind-workspace (6 skills) = **33 skills audited**

---

## 0. Why follow the agentskills.io spec?

We could write our skills in any free format. Following the agentskills.io standard brings concrete benefits:

**Cross-platform compatibility**: A compliant skill works with Claude Code, Copilot, Cursor, Windsurf, Cline, and 30+ other tools. If we switch agents tomorrow, the skills follow without rewriting.

**Context optimization (progressive disclosure)**: The spec enforces 3 loading tiers - description (read at startup for all skills), body (loaded only when the skill is activated), references (loaded on demand). Without this structure, the agent loads everything at once and wastes context tokens.

**Reliable automatic activation**: The `description` and `name` fields with their constraints (1024c max, third person, "Use when...") allow the agent to decide which skill to activate. A poorly formatted description = a skill that doesn't trigger at the right time.

**Marketplace access**: skills.sh indexes 73K+ spec-compliant skills. Following the standard lets us install third-party skills (security, PostgreSQL, Reka UI) and publish ours if needed.

**Validation tooling**: Tools like `skills-ref validate` automatically check compliance. Impossible without a standardized format.

**Future-proofing**: The standard is maintained by a consortium (Anthropic, Microsoft, community) and evolves. Our skills remain compatible with future tool versions.

---

## 1. Compliance table - agentskills.io spec

### Legend

| Symbol | Meaning                        |
| ------ | ------------------------------ |
| OK     | Spec compliant                 |
| WARN   | Functional but minor deviation |
| KO     | Non-compliant, action required |

### 1.1 Audit of 27 basil skills

| #   | Skill                  | Lines | Frontmatter | `name` | `description` | `allowed-tools`  | Refs structure                             | Verdict |
| --- | ---------------------- | ----- | ----------- | ------ | ------------- | ---------------- | ------------------------------------------ | ------- |
| 1   | api-platform           | 351   | OK          | OK     | OK (578c)     | OK               | 4 flat files (no `references/`)            | WARN    |
| 2   | backend-api            | 28    | OK          | OK     | OK (505c)     | OK               | External link `agent-os/standards/` (167L) | WARN    |
| 3   | backend-migrations     | 27    | OK          | OK     | OK (457c)     | OK               | External link `agent-os/standards/` (9L)   | KO      |
| 4   | backend-models         | 27    | OK          | OK     | OK (498c)     | OK               | External link `agent-os/standards/` (10L)  | KO      |
| 5   | backend-queries        | 27    | OK          | OK     | OK (483c)     | OK               | External link `agent-os/standards/` (9L)   | KO      |
| 6   | clean-architecture     | 438   | OK          | OK     | OK (550c)     | OK               | 4 flat files                               | WARN    |
| 7   | frontend-accessibility | 29    | OK          | OK     | OK (496c)     | OK               | External link `agent-os/standards/` (343L) | WARN    |
| 8   | frontend-components    | 29    | OK          | OK     | OK (510c)     | OK               | External link `agent-os/standards/` (423L) | WARN    |
| 9   | frontend-css           | 28    | OK          | OK     | OK (492c)     | OK               | External link `agent-os/standards/` (292L) | WARN    |
| 10  | frontend-responsive    | 29    | OK          | OK     | OK (472c)     | OK               | External link `agent-os/standards/` (228L) | WARN    |
| 11  | git-commits            | 97    | OK          | OK     | OK (504c)     | OK               | 1 flat file (`workflow.md`)                | WARN    |
| 12  | global-coding-style    | 27    | OK          | OK     | OK (488c)     | OK               | External link (10L)                        | KO      |
| 13  | global-commenting      | 26    | OK          | OK     | OK (441c)     | OK               | External link (5L)                         | KO      |
| 14  | global-conventions     | 27    | OK          | OK     | OK (440c)     | OK               | External link (11L)                        | KO      |
| 15  | global-error-handling  | 27    | OK          | OK     | OK (487c)     | OK               | External link (9L)                         | KO      |
| 16  | global-tech-stack      | 27    | OK          | OK     | OK (477c)     | OK               | External link (357L)                       | WARN    |
| 17  | global-validation      | 28    | OK          | OK     | OK (492c)     | OK               | External link (11L)                        | KO      |
| 18  | n8n                    | 172   | OK          | OK     | OK (546c)     | OK               | 4 flat files                               | WARN    |
| 19  | php-symfony            | 345   | OK          | OK     | OK (532c)     | OK               | 3 flat files                               | WARN    |
| 20  | phpstan                | 344   | OK          | OK     | OK (540c)     | OK               | 1 flat file                                | WARN    |
| 21  | pinia-colada           | 264   | OK          | OK     | OK (552c)     | OK               | 2 flat files                               | WARN    |
| 22  | symfony-cache          | 354   | OK          | OK     | OK (456c)     | **KO** (missing) | None                                       | KO      |
| 23  | tailwind-styling       | 250   | OK          | OK     | OK (574c)     | OK               | 1 flat file                                | WARN    |
| 24  | taskfile-linters       | 197   | OK          | OK     | OK (426c)     | OK               | 1 flat file                                | WARN    |
| 25  | testing-test-writing   | 29    | OK          | OK     | OK (537c)     | OK               | External link (308L)                       | WARN    |
| 26  | vue-components         | 362   | OK          | OK     | OK (524c)     | OK               | 3 flat files                               | WARN    |
| 27  | vuellar-ui             | 395   | OK          | OK     | OK (633c)     | OK               | 3 flat files                               | WARN    |

### 1.2 Compliance summary

| Spec criterion                         | Compliant | To fix | Notes                                      |
| -------------------------------------- | --------- | ------ | ------------------------------------------ |
| `SKILL.md` file present                | 27/27     | 0      | OK                                         |
| Valid YAML frontmatter                 | 27/27     | 0      | OK                                         |
| `name` field (req, <= 64c, match dir)  | 27/27     | 0      | OK                                         |
| `description` field (req, <= 1024c)    | 27/27     | 0      | Max 633c (vuellar-ui)                      |
| `allowed-tools` field                  | 26/27     | **1**  | `symfony-cache` missing                    |
| Body < 500 lines                       | 27/27     | 0      | Max 438L (clean-architecture)              |
| Body < 5000 tokens (estimated)         | 27/27     | 0      | OK                                         |
| `references/` folder (spec convention) | **0/27**  | **27** | Flat files or external links               |
| Location `.claude/skills/`             | 27/27     | 0      | OK - native Claude Code, supported by spec |
| `license` field                        | 0/27      | Opt.   | Not used (optional)                        |
| `metadata` field (author, version)     | 0/27      | Opt.   | Not used (optional)                        |
| Third-person description               | ~20/27    | ~7     | Some start with "Use when..."              |

### 1.3 Critical issues identified

#### P1 - Empty external standards (7 files = 0 bytes)

These "thin wrapper" skills point to `agent-os/standards/` files that are **empty**:

| External file              | Size | Impacted skills           |
| -------------------------- | ---- | ------------------------- |
| `backend/application.md`   | 0L   | (not directly referenced) |
| `backend/domain.md`        | 0L   | (not directly referenced) |
| `backend/testing.md`       | 0L   | (not directly referenced) |
| `frontend/testing.md`      | 0L   | (not directly referenced) |
| `frontend/ux-writing.md`   | 0L   | (not directly referenced) |
| `global/best-practices.md` | 0L   | (not directly referenced) |
| `n8n/index.md`             | 0L   | (not directly referenced) |
| `n8n/integration.md`       | 0L   | (not directly referenced) |
| `n8n/naming.md`            | 0L   | (not directly referenced) |
| `n8n/testing.md`           | 0L   | (not directly referenced) |
| `n8n/workflows.md`         | 0L   | (not directly referenced) |

#### P2 - Near-empty external standards (< 15 lines, insufficient content)

| External file              | Size | Impacted skills       |
| -------------------------- | ---- | --------------------- |
| `global/commenting.md`     | 5L   | global-commenting     |
| `backend/migrations.md`    | 9L   | backend-migrations    |
| `backend/queries.md`       | 9L   | backend-queries       |
| `global/error-handling.md` | 9L   | global-error-handling |
| `backend/models.md`        | 10L  | backend-models        |
| `global/coding-style.md`   | 10L  | global-coding-style   |
| `global/conventions.md`    | 11L  | global-conventions    |
| `global/validation.md`     | 11L  | global-validation     |

**Impact**: 8 out of 27 skills (30%) are effectively empty shells - a ~27-line SKILL.md pointing to a ~10-line standard.

#### P3 - Dual pattern (structural inconsistency)

Two patterns coexist without a clear convention:

| Pattern                   | Skills    | Where instructions live                                |
| ------------------------- | --------- | ------------------------------------------------------ |
| **Rich** (self-contained) | 12 skills | In SKILL.md + co-located files                         |
| **Thin wrapper**          | 15 skills | In `agent-os/standards/` via relative path `../../../` |

The "thin wrapper" pattern is fragile: relative paths `../../../agent-os/standards/` break if the structure changes and don't follow the spec's `references/` convention.

### 1.4 Audit of 6 chapsmind-workspace skills

**Location**: `/chapsmind-workspace/.claude/skills/`

| #   | Skill            | Lines | Frontmatter | `name` | `description` | `allowed-tools` | Support files                                                                | Verdict |
| --- | ---------------- | ----- | ----------- | ------ | ------------- | --------------- | ---------------------------------------------------------------------------- | ------- |
| 28  | git-commits      | 79    | OK          | OK     | OK (175c)     | OK              | `workflow.md` (102L)                                                         | OK      |
| 29  | pinia-colada     | 90    | OK          | OK     | OK (218c)     | OK              | `data-fetching.md` (369L), `examples.md` (633L)                              | WARN    |
| 30  | python-fastapi   | 106   | OK          | OK     | OK (222c)     | OK              | `coding-style.md` (496L)                                                     | WARN    |
| 31  | tailwind-styling | 103   | OK          | OK     | OK (186c)     | OK              | `css-guidelines.md` (260L)                                                   | WARN    |
| 32  | vue-components   | 107   | OK          | OK     | OK (197c)     | OK              | `component-structure.md` (469L), `reactivity.md` (283L), `routing.md` (229L) | WARN    |
| 33  | vuellar-ui       | 114   | OK          | OK     | OK (176c)     | OK              | `components.md` (220L), `examples.md` (489L), `props-pattern.md` (253L)      | WARN    |

**WARN verdict**: flat support files instead of `references/` (same deviation as basil).

#### Chapsmind-workspace observations

- **No KO**: all 6 skills are more compliant than basil (no `../../../` links, no missing `allowed-tools`)
- **Single "Rich" pattern**: all skills are self-contained with co-located support
- **Standards in `agent-os/standards/`** (25 files) exist but are **not referenced by skills** - they are consumed via the `standards_as_claude_code_skills: true` flag in `agent-os/config.yml`
- **Additional agents**: 10 agents in `.claude/agents/agent-os/` + 8 commands in `.claude/commands/agent-os/` (not covered by this audit, out of "skills" scope)

### 1.5 Cross-repo synchronization analysis (5 shared skills)

| Skill                | basil (lines) | chapsmind (lines) | Divergences                                                                                                                                                                             |
| -------------------- | ------------- | ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **git-commits**      | 97            | 79                | **Different commit format**: basil = conventional commits (`feat(scope): msg`) + Jira refs `TAR-XXX` + `mcp__gitlab__*`; chapsmind = gitmoji (`:sparkles: feat: msg`) without Jira refs |
| **pinia-colada**     | 264           | 90                | **Description**: basil more detailed (552c vs 218c), additional "When to use" section, references to `pwa/api/queries/` (basil-specific paths)                                          |
| **tailwind-styling** | 250           | 103               | **Description**: basil mentions Tailwind CSS 4.1 explicitly, `sage` tokens; chapsmind more generic. Same critical rules (gap, semantic tokens)                                          |
| **vue-components**   | 362           | 107               | **Description**: basil mentions Nuxt, i18n, `watchEffect`, `pwa/` paths; chapsmind more generic. Same support files                                                                     |
| **vuellar-ui**       | 395           | 114               | **Description**: basil lists all components in the description (633c vs 176c), additional "When to use" section                                                                         |

#### Diagnosis

The **technical instructions are identical** (same rules, same examples, same support files). The divergences are:

1. **Descriptions**: basil has 2-3x longer descriptions with project-specific activation patterns (`pwa/api/queries/`, `TAR-XXX`) - **basil is better** on this point (follows spec best practices)
2. **Git workflow**: fundamentally different commit convention (gitmoji vs conventional commits) - **intentional divergence** tied to the projects
3. **"When to use" sections**: present in basil, absent in chapsmind - **basil is better** (helps the model activate the right skill)
4. **`allowed-tools`**: basil sometimes includes specific tools (`mcp__gitlab__*`) absent from chapsmind

### 1.6 Skill unique to chapsmind-workspace

| Skill              | Description                                                  | Basil equivalent                               |
| ------------------ | ------------------------------------------------------------ | ---------------------------------------------- |
| **python-fastapi** | FastAPI + SQLAlchemy + Pydantic + Ruff (106L + 496L support) | None - **chapsmind-specific** (Python backend) |

This skill must **not** be synchronized to basil (PHP/Symfony stack).

### 1.7 Global summary - 33 skills

| Metric                  | basil | chapsmind          | Total          |
| ----------------------- | ----- | ------------------ | -------------- |
| Total skills            | 27    | 6                  | **33**         |
| Unique skills           | 22    | 1 (python-fastapi) | 23             |
| Shared skills           | 5     | 5                  | 5 (duplicated) |
| KO (non-compliant)      | 8     | 0                  | **8**          |
| WARN (minor deviations) | 18    | 5                  | 23             |
| OK                      | 1     | 1                  | 2              |
| "Thin wrapper" pattern  | 15    | 0                  | 15             |
| "Rich" pattern          | 12    | 6                  | 18             |
| Support files           | 27    | 11                 | 38             |

---

## 2. Marketplace exploration - Recommended third-party skills

### 2.1 Detailed evaluation (Top 8)

| #   | Skill                           | Source                      | Installs/wk | Stack covered                                   | Existing overlap                             | Recommendation                               |
| --- | ------------------------------- | --------------------------- | ----------- | ----------------------------------------------- | -------------------------------------------- | -------------------------------------------- |
| 1   | **Superpowers Symfony**         | MakFly/superpowers-symfony  | -           | API Platform, Doctrine, Messenger, PHPUnit, DDD | High (api-platform, clean-arch, php-symfony) | **EVALUATE** - Compare quality vs our skills |
| 2   | **nuxt-skills (Reka UI)**       | onmax/nuxt-skills           | 1.8K        | Vue 3, Nuxt 4, Reka UI, VueUse, Vitest          | Medium (vue-components)                      | **INSTALL** - Reka UI not covered by us      |
| 3   | **Trail of Bits Security**      | trailofbits/skills          | -           | Security code review, variant analysis, Semgrep | None                                         | **INSTALL** - Critical gap (security)        |
| 4   | **Tailwind Design System v4**   | wshobson/agents             | 10.7K       | Tailwind CSS 4.x, OKLCH tokens, CVA             | Partial (tailwind-styling)                   | **EVALUATE** - v4 specific, very popular     |
| 5   | **PostgreSQL Table Design**     | wshobson/agents             | 4.7K        | PostgreSQL schema, indexing, perf               | None                                         | **INSTALL** - Gap (no DB skill)              |
| 6   | **API Security Best Practices** | sickn33/antigravity-awesome | 1.4K        | OAuth, JWT, OWASP API Top 10                    | None                                         | **INSTALL** - API security complement        |
| 7   | **Sentry Skills**               | getsentry/skills            | -           | Code review, bug finding, skill scanner         | None                                         | **EVALUATE** - `skill-scanner` useful        |
| 8   | **Docker Expert**               | sickn33/antigravity-awesome | 3.5K        | Docker Compose, multi-stage, security           | None                                         | **NICE-TO-HAVE**                             |

### 2.2 Final recommendations

#### To install (3 skills - gap filling)

1. **onmax/nuxt-skills** (skill `reka-ui`) - Our stack uses Reka UI 2.0 and no skill covers it
2. **trailofbits/skills** (skills `differential-review` + `variant-analysis`) - Zero security coverage currently
3. **wshobson/agents** (skill `postgresql-table-design`) - Zero database design coverage

#### To evaluate in detail (2 skills - overlap to arbitrate)

4. **wshobson/agents** (skill `tailwind-design-system`) - Compare with our `tailwind-styling` and decide whether to replace or complement
5. **MakFly/superpowers-symfony** - 49 Symfony skills; compare quality vs our 7 backend skills before adoption

#### Gaps not covered by the marketplace

No relevant third-party skill found for:

- **Elasticsearch / OpenSearch**
- **Keycloak / OIDC**
- **N8N workflows** (our custom skill remains unique)
- **Mercure / SSE**
- **RabbitMQ** (outside Symfony Messenger)
- **Pinia Colada** (our custom skill remains unique)

---

## 3. Monorepo conventions

### 3.1 Recommended folder structure

We keep `.claude/skills/` (native Claude Code location, supported by the agentskills.io spec).
`.github/skills/` is the spec's recommended cross-platform location, but we're on **GitLab** and primarily use **Claude Code**: staying on `.claude/skills/` avoids an unnecessary migration.

**Chapsmind-workspace** is the source of truth for all skills (see section 4). All modifications are made on chapsmind, then manually copied to basil if needed.

```
chapsmind-workspace/.claude/skills/    # SOURCE OF TRUTH
    ├── pinia-colada/                  # Shared → copy to basil if needed
    │   ├── SKILL.md
    │   └── references/
    ├── tailwind-styling/              # Shared
    ├── vue-components/                # Shared
    ├── vuellar-ui/                    # Shared
    ├── git-commits/                   # Local chapsmind (gitmoji)
    └── python-fastapi/                # Local chapsmind (Python stack)

basil/.claude/skills/
    ├── pinia-colada/                  # Copy from chapsmind
    ├── tailwind-styling/              # Copy from chapsmind
    ├── vue-components/                # Copy from chapsmind
    ├── vuellar-ui/                    # Copy from chapsmind
    ├── api-platform/                  # Local basil
    │   ├── SKILL.md
    │   └── references/
    ├── clean-architecture/            # Local basil
    ├── git-commits/                   # Local basil (conventional commits)
    └── ... (22 local skills)
```

### 3.2 Naming convention

| Element           | Convention                            | Examples                             |
| ----------------- | ------------------------------------- | ------------------------------------ |
| Skill folder      | `kebab-case`, max 64c, match `name`   | `api-platform`, `clean-architecture` |
| SKILL.md          | Always `SKILL.md` (uppercase)         | -                                    |
| Docs subfolder    | `references/` (spec standard)         | `references/examples.md`             |
| Scripts subfolder | `scripts/` (if executable)            | `scripts/validate.sh`                |
| Reference files   | `kebab-case.md`, descriptive names    | `dto-validation.md`, not `doc2.md`   |
| Domain prefix     | `backend-*`, `frontend-*`, `global-*` | `backend-api`, `frontend-css`        |

### 3.3 SKILL.md template

```yaml
---
name: skill-name
description: >
  Action-oriented description in third person. Covers X, Y, Z.
  Use when [trigger conditions]. Activates when [file patterns or contexts].
  CRITICAL - [most important rule if any].
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: php|vue|infra|global
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

## When to use this skill

- When [specific trigger 1]
- When [specific trigger 2]
- When working on files in `path/to/relevant/files`

# Skill Title

[Core instructions - keep under 300 lines]

## Key Rules

1. Rule 1
2. Rule 2

## Examples

[2-3 practical examples]

## References

For detailed implementation patterns, see:
- [Topic A](references/topic-a.md)
- [Topic B](references/topic-b.md)
```

### 3.4 Size rules (progressive disclosure)

| Tier                      | Budget                         | Content                                         |
| ------------------------- | ------------------------------ | ----------------------------------------------- |
| Description (frontmatter) | < 200 tokens (~800c)           | What + when + activation criteria               |
| SKILL.md body             | < 5000 tokens (~300-400 lines) | Essential instructions, rules, key examples     |
| `references/*.md`         | Unlimited (loaded on-demand)   | Details, exhaustive examples, advanced patterns |

### 3.5 Quality checklist (before merge)

- [ ] `name` matches folder, kebab-case, < 64 characters
- [ ] `description` < 1024 characters, third person, includes "Use when..."
- [ ] `allowed-tools` present and relevant
- [ ] `metadata.author` = `owlint`, `metadata.version` filled in
- [ ] Body < 500 lines
- [ ] Additional files in `references/` (not at the skill root)
- [ ] No relative paths outside the skill folder (`../../../` forbidden)
- [ ] No temporal information ("since v2.0" -> "Current pattern")
- [ ] Consistent terminology throughout the skill
- [ ] Minimum 2 practical examples

---

## 4. Synchronization strategy: chapsmind → basil

### 4.1 Decision: chapsmind-workspace = source of truth

**Chapsmind-workspace** is the monorepo where all skills live. All modifications are made on chapsmind. If basil needs a skill, it is manually copied from chapsmind.

**Why chapsmind:**

- Agent-os is already installed there (agents, commands, standards, config.yml)
- The 6 existing skills are already mature (rich pattern, no KO)
- Basil is a product project - cross-cutting skills shouldn't live there

### 4.2 Sync process

1. **All modifications** to skills are made on chapsmind-workspace
2. If the skill is also used in basil, **manually copy** the folder to basil
3. Adapt if necessary after copying (paths `pwa/`, Jira refs `TAR-XXX`, `mcp__gitlab__*` in `allowed-tools`)

No submodule, no CI job, no automation - skills rarely change and a manual copy is sufficient.

### 4.3 Skill classification

| Category            | Skills                                                                                  | Source             | Copy to basil                    |
| ------------------- | --------------------------------------------------------------------------------------- | ------------------ | -------------------------------- |
| **Shared frontend** | pinia-colada, tailwind-styling, vue-components, vuellar-ui                              | chapsmind          | Yes (manual copy)                |
| **Basil local**     | api-platform, clean-architecture, php-symfony, phpstan, symfony-cache, n8n, + 16 others | basil              | -                                |
| **Chapsmind local** | git-commits (gitmoji), python-fastapi                                                   | chapsmind          | No                               |
| **Git workflow**    | git-commits                                                                             | local in each repo | No (different commit convention) |

### 4.4 CI validation job (both repos)

> **Note**: This CI job is proposed as a recommendation and is not implemented in this MR. It should be added in a follow-up task.

```yaml
# In each project (.gitlab-ci.yml)
validate-skills:
  stage: test
  rules:
    - changes:
        - .claude/skills/**/*
  script:
    - npx skills-ref validate .claude/skills/
```

---

## 5. Onboarding guide

The complete guide for creating and maintaining an Agent Skill is in a dedicated document:

**[Creating and maintaining an Agent Skill](create-agent-skills.md)**

This guide covers:

- Prerequisites and conventions (location, naming, skill types)
- 5-step creation process (identify, structure, write, validate, test)
- Complete SKILL.md template with frontmatter and body
- Best practices (progressive disclosure, descriptions, real examples)
- Maintaining existing skills
- Quality checklist before merge (frontmatter, body, structure, test)
- Common mistakes to avoid (6 anti-patterns identified during the audit)
- Complete commented example (skill `pinia-colada`)

---

## 6. Appendices

### A. Detailed support file inventory

| Skill              | Co-located files                                                    | Total lines |
| ------------------ | ------------------------------------------------------------------- | ----------- |
| api-platform       | `dto-validation.md`, `examples.md`, `processors.md`, `providers.md` | ~1780       |
| clean-architecture | `application.md`, `domain.md`, `examples.md`, `infrastructure.md`   | ~2115       |
| n8n                | `examples.md`, `rabbitmq.md`, `testing.md`, `workflows.md`          | ~1749       |
| php-symfony        | `doctrine-entities.md`, `examples.md`, `symfony-services.md`        | ~1295       |
| vue-components     | `component-structure.md`, `reactivity.md`, `routing.md`             | ~1621       |
| vuellar-ui         | `components.md`, `examples.md`, `props-pattern.md`                  | ~1620       |
| pinia-colada       | `data-fetching.md`, `examples.md`                                   | ~1349       |
| phpstan            | `examples.md`                                                       | ~783        |
| tailwind-styling   | `css-guidelines.md`                                                 | ~542        |
| taskfile-linters   | `examples.md`                                                       | ~595        |
| git-commits        | `workflow.md`                                                       | ~335        |
| symfony-cache      | (none)                                                              | 354         |

### B. `agent-os/standards/` files and their size

| Path                        | Lines | Status              |
| --------------------------- | ----- | ------------------- |
| `backend/api.md`            | 167   | Substantial content |
| `backend/application.md`    | 0     | EMPTY               |
| `backend/domain.md`         | 0     | EMPTY               |
| `backend/migrations.md`     | 9     | Near-empty          |
| `backend/models.md`         | 10    | Near-empty          |
| `backend/queries.md`        | 9     | Near-empty          |
| `backend/testing.md`        | 0     | EMPTY               |
| `frontend/accessibility.md` | 343   | Substantial content |
| `frontend/components.md`    | 423   | Substantial content |
| `frontend/css.md`           | 292   | Substantial content |
| `frontend/responsive.md`    | 228   | Substantial content |
| `frontend/testing.md`       | 0     | EMPTY               |
| `frontend/ux-writing.md`    | 0     | EMPTY               |
| `global/best-practices.md`  | 0     | EMPTY               |
| `global/coding-style.md`    | 10    | Near-empty          |
| `global/commenting.md`      | 5     | Near-empty          |
| `global/conventions.md`     | 11    | Near-empty          |
| `global/error-handling.md`  | 9     | Near-empty          |
| `global/tech-stack.md`      | 357   | Substantial content |
| `global/validation.md`      | 11    | Near-empty          |
| `testing/test-writing.md`   | 308   | Substantial content |
| `n8n/index.md`              | 0     | EMPTY               |
| `n8n/integration.md`        | 0     | EMPTY               |
| `n8n/naming.md`             | 0     | EMPTY               |
| `n8n/testing.md`            | 0     | EMPTY               |
| `n8n/workflows.md`          | 0     | EMPTY               |

### C. Chapsmind-workspace inventory

| Skill            | Co-located files                                                             | Total lines | Shared with basil                             |
| ---------------- | ---------------------------------------------------------------------------- | ----------- | --------------------------------------------- |
| git-commits      | `workflow.md` (102L)                                                         | 181         | Yes - **divergent** (gitmoji vs conventional) |
| pinia-colada     | `data-fetching.md` (369L), `examples.md` (633L)                              | 1092        | Yes - identical instructions                  |
| python-fastapi   | `coding-style.md` (496L)                                                     | 602         | **No** - unique to chapsmind                  |
| tailwind-styling | `css-guidelines.md` (260L)                                                   | 363         | Yes - identical instructions                  |
| vue-components   | `component-structure.md` (469L), `reactivity.md` (283L), `routing.md` (229L) | 1088        | Yes - identical instructions                  |
| vuellar-ui       | `components.md` (220L), `examples.md` (489L), `props-pattern.md` (253L)      | 1076        | Yes - identical instructions                  |

**Additional chapsmind ecosystem** (out of skills scope, for reference):

- 10 agents in `.claude/agents/agent-os/` (git-workflow, i18n-translator, implementation-verifier, implementer, product-planner, spec-initializer, spec-shaper, spec-verifier, spec-writer, tasks-list-creator)
- 8 commands in `.claude/commands/agent-os/` (commit, create-tasks, implement-tasks, orchestrate-tasks, plan-product, shape-spec, sync, write-spec)
- 25 standard files in `agent-os/standards/` (consumed via `standards_as_claude_code_skills: true`)
- Agent-os config v2.1.1 (`agent-os/config.yml`)

### D. Marketplace sources

- **agentskills.io**: https://agentskills.io/specification
- **skills.sh**: https://skills.sh (73K+ skills)
- **VoltAgent/awesome-agent-skills**: https://github.com/VoltAgent/awesome-agent-skills (380+ curated skills)
- **microsoft/skills**: https://github.com/microsoft/skills (131 skills)
- **anthropics/skills**: https://github.com/anthropics/skills (reference implementation)
- **MakFly/superpowers-symfony**: https://github.com/MakFly/superpowers-symfony (49 Symfony skills)
- **onmax/nuxt-skills**: https://github.com/onmax/nuxt-skills (18 Vue/Nuxt skills)
- **trailofbits/skills**: https://github.com/trailofbits/skills (18 security skills)
- **getsentry/skills**: https://github.com/getsentry/skills (14 skills)
