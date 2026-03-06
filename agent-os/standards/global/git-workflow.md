# Git Workflow Standards

## Branch Strategy

### Feature Branch Workflow

All work done in feature branches, merged to main via pull/merge requests.

### Branch Naming Conventions

| Prefix      | Use                   |
| ----------- | --------------------- |
| `feat/`     | New features          |
| `fix/`      | Bug fixes             |
| `refactor/` | Code refactoring      |
| `docs/`     | Documentation updates |
| `chore/`    | Maintenance tasks     |

Examples:

- `feat/user-authentication`
- `fix/validation-error`
- `refactor/company-service`

---

## Commit Message Format

### Gitmoji + Conventional Commits

```
<gitmoji> <type>: <description>

[optional body]
```

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
# Feature
✨ feat: add user authentication system

# Bug fix
🐛 fix: resolve validation error for company names

# Database change
🗃️ feat: add organization_id to companies table

# Security fix
🔒 fix: sanitize user input to prevent XSS
```

---

## Workflow Steps

### 1. Start New Work

```bash
# Checkout main and pull latest
git checkout main
git pull origin main

# Create feature branch
git checkout -b feat/feature-name
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
✨ feat: add company search functionality

- Add search endpoint with filters
- Add frontend search component
- Add pagination support

EOF
)"
```

### 4. Push and Create PR

```bash
# Push feature branch
git push -u origin feat/feature-name

# Create pull request
gh pr create --title "feat: add company search" --body "..."
```

### 5. Merge After Review

After approval, merge to main via GitHub/GitLab UI.

---

## Critical Rules

### DO

- ✅ Create feature branches for all work
- ✅ Write clear commit messages
- ✅ Use gitmoji for visual context
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
git commit -m "✨ feat: add search endpoint"
git commit -m "💄 feat: add search UI component"
git commit -m "🧪 test: add search tests"

# ❌ BAD - Mixed concerns
git commit -m "Add search feature, fix bug, update docs"
```

### Smart Grouping

Group related changes logically:

```bash
# All changes to search feature
git add src/api/search.py src/services/search.py
git commit -m "✨ feat: add search backend"

# All changes to UI
git add src/components/SearchForm.vue
git commit -m "💄 feat: add search form component"
```

### Test Before Commit

```bash
# Python
ruff check . && ruff format .
pytest

# TypeScript
pnpm lint
pnpm test
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
```
