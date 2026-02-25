---
name: git-commits
description: Git workflow with gitmoji commits and feature branches. Use when committing code, creating branches, or managing git workflow. Always use gitmoji format and feature branches.
allowed-tools: Bash, Read, Grep
---

# Git Workflow

## Branch Strategy

**NEVER commit directly to main**. Always use feature branches:

```bash
# Branch naming
feat/feature-name      # New features
fix/bug-name          # Bug fixes
refactor/name         # Refactoring
docs/doc-name         # Documentation
chore/task-name       # Maintenance
```

## Gitmoji Commit Format

```
<gitmoji> <type>: <description>

[optional body]

Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Common Gitmojis

| Emoji | Code | Usage |
|-------|------|-------|
| :sparkles: | New feature |
| :bug: | Bug fix |
| :wrench: | Configuration |
| :memo: | Documentation |
| :card_file_box: | Database/migrations |
| :lock: | Security |
| :recycle: | Refactoring |
| :fire: | Remove code |
| :lipstick: | UI/styling |
| :test_tube: | Tests |

## Workflow

```bash
# 1. Start from main
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feat/add-company-search

# 3. Make changes and commit
git add .
git commit -m ":sparkles: feat: add company search functionality

Implements search with filters for name, status, and date range.

Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>"

# 4. Push and create PR
git push -u origin feat/add-company-search
```

## Commit Rules

- Use present tense ("add" not "added")
- Explain what and why, not how
- Keep subject line under 72 characters
- One logical change per commit
- Never force push to main/master
