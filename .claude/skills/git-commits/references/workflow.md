# Git Workflow Standards

## Branch Strategy

### Feature Branch Workflow

All work done in feature branches, merged to main via pull/merge requests.

### Branch Naming Conventions

| Prefix              | Use                                     |
| ------------------- | --------------------------------------- |
| `feat/TAR-xxx-`     | New features                            |
| `fix/TAR-xxx-`      | Bug fixes                               |
| `refactor/TAR-xxx-` | Code refactoring                        |
| `docs/`             | Documentation updates (ticket optional) |
| `chore/`            | Maintenance tasks (ticket optional)     |

Examples:

- `feat/TAR-123-user-authentication`
- `fix/TAR-456-validation-error`
- `refactor/TAR-789-company-service`
- `docs/update-readme`
- `chore/update-dependencies`

---

## Commit Message Format

### Gitmoji + Conventional Commits

```
<gitmoji> <type>(<scope>): TAR-xxx <description>

[optional body]
```

**Note**: `chore` and `docs` commits may omit the Jira ticket number (TAR-xxx).

### Common Gitmojis

| Emoji | Code              | Usage                       |
| ----- | ----------------- | --------------------------- |
| ✨    | `:sparkles:`      | New features                |
| 🐛    | `:bug:`           | Bug fixes                   |
| 🔧    | `:wrench:`        | Configuration changes       |
| 📝    | `:memo:`          | Documentation updates       |
| 🗃️    | `:card_file_box:` | Database changes/migrations |
| 🔒    | `:lock:`          | Security improvements       |
| ♻️    | `:recycle:`       | Refactoring code            |
| 🚀    | `:rocket:`        | Deployment/performance      |
| 🔥    | `:fire:`          | Removing code/files         |
| 💄    | `:lipstick:`      | UI/styling updates          |
| 🧪    | `:test_tube:`     | Adding tests                |
| 📦    | `:package:`       | Dependencies/packages       |

### Examples

```bash
# Feature (ticket required)
✨ feat(auth): TAR-123 add user authentication system

# Bug fix (ticket required)
🐛 fix(company): TAR-456 resolve validation error for company names

# Database change (ticket required)
🗃️ feat(db): TAR-789 add organization_id to companies table

# Security fix (ticket required)
🔒 fix(api): TAR-101 sanitize user input to prevent XSS

# Chore (ticket optional)
🔧 chore(deps): update frontend dependencies

# Documentation (ticket optional)
📝 docs(readme): update installation instructions
```

---

## Workflow Steps

### 1. Start New Work

```bash
# Checkout main and pull latest
git checkout main
git pull origin main

# Create feature branch (include Jira ticket)
git checkout -b feat/TAR-123-feature-name
```

### 2. Make Changes

- Write code following project standards
- Test locally
- Run linters (Ruff for Python, ESLint for TypeScript)

### 3. Commit Changes

```bash
# Stage changes
git add .

# Commit with gitmoji format
git commit -m "$(cat <<'EOF'
✨ feat(search): TAR-123 add company search functionality

- Add search endpoint with filters
- Add frontend search component
- Add pagination support

EOF
)"
```

### 4. Push and Create PR

```bash
# Push feature branch
git push -u origin feat/TAR-123-feature-name

# Create pull request
gh pr create --title "feat(search): TAR-123 add company search" --body "..."
```

### 5. Merge After Review

After approval, merge to main via GitHub/GitLab UI.

---

## Critical Rules

### DO

- ✅ Create feature branches for all work
- ✅ Write clear commit messages
- ✅ Use gitmoji for visual context
- ✅ Include Jira ticket (TAR-xxx) in commits (except chore/docs)
- ✅ Test before committing
- ✅ Create PR/MR for code review

### DON'T

- ❌ **NEVER** commit directly to main
- ❌ **NEVER** force push to main/master
- ❌ **NEVER** skip pre-commit hooks
- ❌ **NEVER** commit sensitive data (secrets, API keys)
- ❌ **NEVER** copy files directly to production server

---

## Commit Best Practices

### Atomic Commits

One logical change per commit:

```bash
# ✅ GOOD - Separate concerns
git commit -m "✨ feat(api): TAR-123 add search endpoint"
git commit -m "💄 feat(ui): TAR-123 add search UI component"
git commit -m "🧪 test(search): TAR-123 add search tests"

# ❌ BAD - Mixed concerns
git commit -m "Add search feature, fix bug, update docs"
```

### Smart Grouping

Group related changes logically:

```bash
# All changes to search feature
git add src/api/search.py src/services/search.py
git commit -m "✨ feat(api): TAR-123 add search backend"

# All changes to UI
git add src/components/SearchForm.vue
git commit -m "💄 feat(ui): TAR-123 add search form component"
```

### Test Before Commit

```bash
# Python
ruff check . && ruff format .
pytest

# TypeScript
yarn lint
yarn test
```

---

## Deployment Process

1. Create feature branch from main
2. Make changes locally
3. Test changes locally (with Docker for backend)
4. Commit with gitmoji format
5. Push feature branch
6. Create PR/MR for review
7. After approval, merge to main
8. Deploy from main using deployment process
9. Verify deployment

---

## Pre-commit Hooks

If pre-commit hooks modify files:

1. Check what was modified
2. Review changes are safe
3. Stage the modified files
4. Amend the commit (only if it's your own commit)

```bash
# Check authorship before amending
git log -1 --format='%an %ae'

# If it's your commit, amend
git add .
git commit --amend --no-edit
```

---

## Pull Request Template

```markdown
## Summary

- Brief description of changes (1-3 bullets)

## Changes

- [ ] Feature 1
- [ ] Feature 2

## Test Plan

- [ ] Unit tests pass
- [ ] Manual testing completed
- [ ] E2E tests pass (if applicable)

## Screenshots (if UI changes)

[Add screenshots]

🤖 Generated with [Claude Code](https://claude.ai/code)
```
