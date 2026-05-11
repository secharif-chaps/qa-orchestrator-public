# Creating and maintaining an Agent Skill

> **Project**: Target / Chapsmind
> **Reference spec**: <https://agentskills.io/specification>
> **Best practices**: <https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices>

---

## 1. What is an Agent Skill?

An Agent Skill is a **folder containing a SKILL.md file** that teaches an AI agent (Claude Code, Copilot, Cursor, etc.) how to perform a specific task. It is an open standard adopted by 30+ platforms.

**Why we use them:**

- Ensure consistency of generated code (same patterns, same conventions)
- Capitalize on architectural decisions (DDD, API Platform, Vuellar, etc.)
- Share standards across teams and projects (basil, chapsmind)
- Avoid repeating the same instructions in every prompt

**What it is NOT:**

- A documentation file for humans (it's for the agent)
- An exhaustive course on a technology (Claude already knows Symfony, Vue, etc.)
- A replacement for CLAUDE.md (which contains global project instructions)

**Why follow the agentskills.io spec:**

- **Cross-platform**: A compliant skill works with Claude Code, Copilot, Cursor, Windsurf, and 30+ tools. If we switch agents, the skills follow.
- **Progressive disclosure**: The spec enforces 3 tiers (description -> body -> references) to optimize context tokens instead of loading everything at once.
- **Reliable activation**: The constraints on `description` ("Use when...", 1024c max, third person) allow the agent to trigger the right skill at the right time.
- **Marketplace**: skills.sh indexes 73K+ compliant skills. We can install third-party skills and publish ours.
- **Automatic validation**: `skills-ref validate` checks compliance - impossible without a standardized format.

For the full details, see section 0 of the strategy document.

---

## 2. Prerequisites

Before creating or modifying a skill:

1. Read the agentskills.io spec (10 min): <https://agentskills.io/specification>
2. Read the Anthropic best practices (15 min): <https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices>
3. Browse 2-3 existing skills in `.claude/skills/` to understand the format
4. Have access to the basil or chapsmind-workspace repo

---

## 3. Our conventions

### Location

```text
.claude/skills/{skill-name}/
    SKILL.md                    # Required - main instructions
    references/                 # Optional - detailed documentation
        examples.md
        patterns.md
```

Skills live in `.claude/skills/` (native Claude Code location, supported by the agentskills.io spec). Do NOT use `.github/skills/` (we're on GitLab).

### Naming

| Rule                        | OK example                                         | Bad example                     |
| --------------------------- | -------------------------------------------------- | ------------------------------- |
| kebab-case only             | `api-platform`                                     | `apiPlatform`, `API_Platform`   |
| Folder = `name` field       | `pinia-colada/` + `name: pinia-colada`             | `pinia/` + `name: pinia-colada` |
| Max 64 characters           | `clean-architecture`                               | (rarely an issue)               |
| No leading/trailing hyphens | `git-commits`                                      | `-git-commits-`                 |
| No consecutive hyphens      | `vue-components`                                   | `vue--components`               |
| Domain prefix               | `backend-api`, `frontend-css`, `global-validation` | `api`, `css`, `validation`      |
| Descriptive names           | `testing-test-writing`                             | `utils`, `helper`, `tools`      |

### Two types of skills

| Type                | When to use                                               | Typical size                          |
| ------------------- | --------------------------------------------------------- | ------------------------------------- |
| **Self-contained**  | The topic fits in < 300 lines                             | SKILL.md only                         |
| **With references** | The topic requires detailed examples or advanced patterns | SKILL.md (< 300L) + `references/*.md` |

---

## 4. Creating a new skill - 5 steps

### Step 1 - Identify the need

Before creating a skill, answer these 3 questions:

- [ ] **No existing skill covers the topic?** Browse `.claude/skills/`
- [ ] **No marketplace skill does it better?** Search on <https://skills.sh>
- [ ] **The topic justifies a dedicated skill?** If it's 3 lines of config, add them to an existing skill

### Step 2 - Create the structure

```bash
mkdir -p .claude/skills/my-skill/references
touch .claude/skills/my-skill/SKILL.md
```

### Step 3 - Write the SKILL.md

Use the template below (section 5). Key points:

- **Description**: Hook sentence + "Use when..." + "Activates when..." + "CRITICAL -" (if applicable)
- **Body**: Concrete instructions, no theory. Claude is already smart - only explain what it can't guess
- **Examples**: Minimum 2, copied from real project code (not generic examples)
- **References**: Move details to `references/` if the body exceeds 300 lines

### Step 4 - Validate

```bash
# Automatic validation
npx skills-ref validate .claude/skills/my-skill/

# Manual checklist (see section 8)
```

### Step 5 - Test

1. Open Claude Code in the project
2. Give a task covered by the skill
3. Verify the skill is activated automatically (visible in context)
4. Verify the instructions are followed correctly
5. Test with an edge case
6. Bonus: test with another model (Haiku, Sonnet) if the skill is critical

---

## 5. SKILL.md template

````yaml
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

**CRITICAL**: [Most important rule, repeated for emphasis]

## Key Rules

1. Rule 1
2. Rule 2

## Patterns

### Pattern Name

```language
// Code example from real project
```

## Examples

### Example 1 - [Scenario]

```language
// Real code from basil
```

### Example 2 - [Scenario]

```language
// Real code from basil
```

## References

For detailed implementation patterns, see:
- Topic A → references/topic-a.md
- Topic B → references/topic-b.md

````

### YAML frontmatter fields

| Field           | Required             | Constraints                        | Notes                                         |
| --------------- | -------------------- | ---------------------------------- | --------------------------------------------- |
| `name`          | **Yes**              | Max 64c, kebab-case, = folder name | No reserved words ("anthropic", "claude")     |
| `description`   | **Yes**              | Max 1024c, third person, no XML    | Include "Use when..." and "Activates when..." |
| `allowed-tools` | **Yes** (convention) | Comma-separated list               | Restrict to what's needed                     |
| `license`       | No                   | License name                       | `MIT` by default for us                       |
| `metadata`      | No                   | String key/values                  | `author: owlint`, `version: "1.0"`            |
| `compatibility` | No                   | Max 500c                           | Required environment                          |

---

## 6. Best practices

### Progressive disclosure (3 tiers)

| Tier                           | Loaded when                 | Budget                | Content                                         |
| ------------------------------ | --------------------------- | --------------------- | ----------------------------------------------- |
| **Description** (frontmatter)  | At startup, for ALL skills  | < 200 tokens (~800c)  | What + when + activation criteria               |
| **Body** (SKILL.md)            | When the skill is activated | < 5000 tokens (~400L) | Essential instructions, rules, key examples     |
| **References** (`references/`) | On demand                   | Unlimited             | Details, exhaustive examples, advanced patterns |

### Writing a good description

The description is the primary activation criterion. The agent reads ALL descriptions at startup to decide which skill to activate.

**Recommended structure:**

```text

[What the skill does]. Use when [trigger conditions].
Activates when [file patterns or contexts].
CRITICAL - [most important rule].

```

**Real example (pinia-colada):**

```text

Data fetching with Pinia Colada queries and mutations for Vue/Nuxt
applications. Use when fetching data from the API, creating query
definitions with defineQueryOptions, implementing mutations with
useMutation, handling loading/error/empty states, managing server-side
cache invalidation, or implementing optimistic updates. Activates when
working on files in pwa/api/queries/, pwa/api/mutations/, or any Vue
component that needs to fetch or mutate API data. CRITICAL - Never call
API functions directly in components; always use queries and mutations.

```

### Body writing rules

1. **Conciseness**: Every token competes with conversation history. Get straight to the point.
2. **One level of depth**: SKILL.md -> references/file.md. Never references/a.md -> references/b.md.
3. **Project examples**: Use real code from basil, not generic examples.
4. **No dates**: "Current pattern" instead of "Since v2.0 (Jan 2026)".
5. **Fixed terminology**: Choose a term and stick with it (e.g., always "Gateway", never "Repository").
6. **Feedback loops**: For critical tasks, include a validation step (lint, test, type-check).
7. **Restricted allowed-tools**: Only provide the necessary tools (Read, Grep for review; + Write, Edit for generation).

---

## 7. Maintaining an existing skill

| When to modify                             | Action                                                  |
| ------------------------------------------ | ------------------------------------------------------- |
| Version upgrade (e.g., Symfony 7.3 -> 7.4) | Update versions + obsolete patterns                     |
| New pattern discovered                     | Add to `references/` (not to SKILL.md unless essential) |
| Obsolete pattern                           | Delete (no "deprecated since..." or "formerly...")      |
| Skill too large (> 500L)                   | Extract to `references/`                                |
| Recurring negative feedback                | Rephrase instructions, add examples                     |
| Better third-party skill available         | Evaluate: replace or complement                         |

### Never modify

- The `name` field (would break existing references)
- Descriptions to add temporal content ("since February 2026...")

---

## 8. Quality checklist (before merge)

### Frontmatter

- [ ] `name` matches folder, kebab-case, < 64 characters
- [ ] `description` < 1024 characters, third person
- [ ] `description` includes "Use when..." AND "Activates when..."
- [ ] `allowed-tools` present and restricted to what's needed
- [ ] `metadata.author` = `owlint`, `metadata.version` filled in

### Body

- [ ] < 500 lines
- [ ] Section "## When to use this skill" present
- [ ] Minimum 2 practical examples (real project code)
- [ ] No theory that Claude already knows
- [ ] Consistent terminology throughout the skill

### Structure

- [ ] Additional files in `references/` (not at the skill root)
- [ ] No relative paths outside the skill folder (`../../../` forbidden)
- [ ] Internal links to `references/` are correct
- [ ] No temporal information

### Test

- [ ] Tested with a real task in Claude Code
- [ ] The skill activates at the right time (no false positive or negative)
- [ ] Instructions are followed correctly

---

## 9. Common mistakes to avoid

These anti-patterns were identified during our audit (33 skills):

### 1. The empty "thin wrapper"

```markdown
# Bad - a SKILL.md that only points elsewhere

## Instructions

For details, refer to the information provided in this file:
[backend models](../../../agent-os/standards/backend/models.md)
```

The external file contains 10 lines of bullet points. Result: the skill provides almost nothing to the agent.

**Fix**: Integrate the content directly into the SKILL.md. If the external content is > 100 lines, copy it into `references/`.

### 2. Missing `allowed-tools`

```yaml
---
name: symfony-cache
description: Implement caching with Symfony Cache...
---
```

Without `allowed-tools`, the agent doesn't know which tools it can use with this skill.

**Fix**: Always include `allowed-tools` in the frontmatter.

### 3. Description too short

```yaml
description: Git workflow with gitmoji commits and feature branches.
```

175 characters. The agent doesn't have enough context to know WHEN to activate this skill.

**Fix**: Include triggers ("Use when..."), file patterns ("Activates when..."), and the critical rule ("CRITICAL -").

### 4. Missing "When to use this skill"

A SKILL.md that starts directly with instructions without an activation section. The agent understands less well when to use it.

**Fix**: Always start with `## When to use this skill` with 5-10 bullet points.

### 5. Support files at the root

```text
my-skill/
    SKILL.md
    examples.md        # At the root, not in references/
    patterns.md
```

Doesn't follow the spec's `references/` convention, makes the structure less readable.

**Fix**:

```text
my-skill/
    SKILL.md
    references/
        examples.md
        patterns.md
```

### 6. Generic examples

```php
// Bad - generic example
class MyEntity {
    private string $name;
}
```

**Fix**: Use real code from the project.

```php
// Good - real example from basil
#[ORM\Entity]
#[ApiResource(
    operations: [new Get(), new GetCollection()],
    security: "is_granted('VIEW', object)"
)]
class WatchFile {
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
}
```

---

## 10. Complete example: the `pinia-colada` skill

### Structure

```text
.claude/skills/pinia-colada/
    SKILL.md                     # 264 lines
    references/
        data-fetching.md         # 369 lines - architecture and patterns
        examples.md              # 633 lines - detailed examples
```

### SKILL.md (excerpt)

```yaml
---
name: pinia-colada
description: Data fetching with Pinia Colada queries and mutations for Vue/Nuxt
  applications. Use when fetching data from the API, creating query definitions with
  defineQueryOptions, implementing mutations with useMutation, handling
  loading/error/empty states, managing server-side cache invalidation, or implementing
  optimistic updates. Activates when working on files in pwa/api/queries/,
  pwa/api/mutations/, or any Vue component that needs to fetch or mutate API data.
  CRITICAL - Never call API functions directly in components; always use queries and
  mutations.
allowed-tools: Read, Write, Edit, Glob, Grep
---

## When to use this skill

- When fetching data from the API in Vue components
- When creating query definitions in `pwa/api/queries/`
- When creating mutation definitions in `pwa/api/mutations/`
- When implementing `useQuery` or `useMutation` hooks
- When handling loading, error, and empty states in templates
- When invalidating cache after successful mutations
- When implementing optimistic updates for better UX

# Data Fetching with Pinia Colada

**CRITICAL**: Never call API functions directly in components. Always use
queries and mutations.

## Architecture (3 layers)

[... essential instructions ...]

## References

- [data-fetching.md](references/data-fetching.md) - Architecture and patterns
- [examples.md](references/examples.md) - Pagination, optimistic UI, cache
```

### Why this skill works well

1. **Rich description** (552c) with clear triggers and CRITICAL rule
2. **"When to use"** with 7 concrete scenarios + file paths
3. **Body < 300 lines** with essential rules
4. **references/** for the 1000+ lines of detail
5. **Real examples** from the project (no generic code)
6. **Consistent terminology**: always "query", "mutation", "cache invalidation"
