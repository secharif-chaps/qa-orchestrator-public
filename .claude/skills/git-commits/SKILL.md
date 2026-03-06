---
name: git-commits
description: Git workflow with gitmoji commits and feature branches. Use when committing code, creating branches, or managing git workflow. Always use gitmoji format and feature branches.
allowed-tools: Bash, Read, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

# Git Workflow

## Branch Strategy

**NEVER commit directly to main**. Always use feature branches:

```bash
# Branch naming (include Jira ticket when available)
feat/TAR-123-feature-name      # New features
fix/TAR-456-bug-name           # Bug fixes
refactor/TAR-789-name          # Refactoring
docs/doc-name                  # Documentation (ticket optional)
chore/task-name                # Maintenance (ticket optional)
```

## Gitmoji Commit Format

```
<gitmoji> <type>(<scope>): TAR-xxx <description>

[optional body]
```

**Note**: `chore` and `docs` commits may omit the Jira ticket number.

### Common Gitmojis

| Emoji           | Code                | Usage |
| --------------- | ------------------- | ----- |
| :sparkles:      | New feature         |
| :bug:           | Bug fix             |
| :wrench:        | Configuration       |
| :memo:          | Documentation       |
| :card_file_box: | Database/migrations |
| :lock:          | Security            |
| :recycle:       | Refactoring         |
| :fire:          | Remove code         |
| :lipstick:      | UI/styling          |
| :test_tube:     | Tests               |

## Workflow

```bash
# 1. Start from main
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feat/TAR-123-add-company-search

# 3. Make changes and commit
git add .
git commit -m ":sparkles: feat(search): TAR-123 add company search functionality

Implements search with filters for name, status, and date range."

# 4. Push and create PR
git push -u origin feat/TAR-123-add-company-search
```

## Commit Rules

- Use present tense ("add" not "added")
- Explain what and why, not how
- Keep subject line under 120 characters
- One logical change per commit
- Never force push to main/master
- Include Jira ticket (TAR-xxx) for all commits except `chore` and `docs`
