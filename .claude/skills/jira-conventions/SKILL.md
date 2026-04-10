---
name: jira-conventions
description: >
  Jira ticket conventions for the ChapsMind project using TAR-xxx format.
  Use when creating branches, writing commit messages, referencing tickets in code,
  or naming merge requests. Activates when running git commands, preparing commits,
  or creating branches. CRITICAL - Always include TAR-xxx ticket reference in branch
  names and commit messages (except chore and docs types).
allowed-tools: Bash, Read, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating a new git branch
- When writing commit messages
- When creating merge requests
- When referencing tickets in code comments or TODOs
- When naming feature branches with ticket references

# Jira Conventions

**CRITICAL**: Always reference the Jira ticket (TAR-xxx) in branches and commits. Only `chore` and `docs` types may omit the ticket number.

## Ticket Format

- **Project prefix**: `TAR-` (all tickets)
- **Format**: `TAR-{number}` (e.g., TAR-42, TAR-1197)
- **In commits**: After the scope, before the description
- **In branches**: After the type prefix

## Branch Naming

```bash
# Format: {type}/TAR-{number}-{short-description}
feat/TAR-123-add-company-search
fix/TAR-456-pagination-offset
refactor/TAR-789-company-service
docs/TAR-99-api-docs

# Without ticket (chore/docs only)
chore/update-dependencies
docs/update-readme
```

### Branch Types

| Type        | Usage                           |
| ----------- | ------------------------------- |
| `feat/`     | New features                    |
| `fix/`      | Bug fixes                       |
| `refactor/` | Code refactoring                |
| `docs/`     | Documentation (ticket optional) |
| `chore/`    | Maintenance (ticket optional)   |

## Commit Message Format

```
<gitmoji> <type>(<scope>): TAR-xxx <description>

[optional body]
```

### Examples

```bash
# Feature
✨ feat(front): TAR-42 add company search filters

# Bug fix
🐛 fix(screen): TAR-15 resolve pagination offset error

# Database migration
🗃️ feat(screen): TAR-30 add workflow_configs migration

# Security
🔒 fix(screen): TAR-55 sanitize user input to prevent XSS

# Documentation (ticket optional)
📝 docs: update API endpoint documentation

# Chore (ticket optional)
🔧 chore(infra): update docker-compose ports
```

### Scopes

| Scope            | When                             |
| ---------------- | -------------------------------- |
| `front`          | Frontend (Vue/Nuxt) changes      |
| `screen`         | Screen backend (FastAPI) changes |
| `infra`          | Docker, CI/CD, infrastructure    |
| `global-service` | Global service changes           |
| (none)           | Cross-cutting changes            |

## Merge Request Title

```
<gitmoji> <type>(<scope>): TAR-xxx <description>
```

## Code References

```python
# TODO: TAR-456 - Implement retry logic for failed workflows
# FIXME: TAR-789 - Race condition in concurrent task updates
```

## Rules

1. **Always use TAR-xxx** in feature and fix branches/commits
2. **One ticket per branch** - Don't mix multiple tickets
3. **Keep descriptions short** - Max 72 characters in subject line
4. **Use present tense** - "add" not "added", "fix" not "fixed"
5. **Scope is optional** but recommended for clarity

## Labels Jira

6. **Always add the label `Claude`** to every Jira ticket created by the assistant (Epic, Story, Bug, Design) — this is mandatory and non-negotiable, it allows tracking AI-created tickets in Jira
