# Git Commit Guidelines

## 🎯 Commit Message Format

We use **Conventional Commits** with **Gitmoji** for consistent and meaningful commit messages.

### Basic Structure
```
<gitmoji> <type>(<scope>): <description>

[optional body]

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## 🎨 Gitmoji Reference

### Most Common Gitmojis
| Gitmoji | Code | Description | When to Use |
|---------|------|-------------|-------------|
| ✨ | `:sparkles:` | New feature | Adding new functionality |
| 🐛 | `:bug:` | Bug fix | Fixing bugs or issues |
| 🎨 | `:art:` | Code structure/format | Improving code quality, formatting |
| ⚡ | `:zap:` | Performance | Performance improvements |
| 🔧 | `:wrench:` | Configuration | Config files, settings |
| 📝 | `:memo:` | Documentation | Adding/updating docs |
| 🚀 | `:rocket:` | Deployment | Deploy related changes |
| 🔒 | `:lock:` | Security | Security improvements |
| ♻️ | `:recycle:` | Refactoring | Code refactoring |
| 🧪 | `:test_tube:` | Tests | Adding/updating tests |

### Component-Specific Gitmojis
| Gitmoji | Code | Description | When to Use |
|---------|------|-------------|-------------|
| 💄 | `:lipstick:` | UI/UX | UI improvements, styling |
| 🔥 | `:fire:` | Remove code/files | Removing features, cleaning up |
| 📱 | `:iphone:` | Responsive design | Mobile/responsive updates |
| 🌐 | `:globe_with_meridians:` | Internationalization | i18n updates |
| 🗃️ | `:card_file_box:` | Database | Database changes |
| 🔐 | `:closed_lock_with_key:` | Auth/permissions | Authentication, roles |

## 📋 Commit Types

### Primary Types
- **feat**: New feature for the user
- **fix**: Bug fix for the user  
- **docs**: Documentation changes
- **style**: Code formatting (no logic changes)
- **refactor**: Code refactoring (no features/fixes)
- **perf**: Performance improvements
- **test**: Adding/updating tests
- **chore**: Build process, tools, dependencies

### Scope Examples
- **components**: Vue components
- **pages**: Page components
- **composables**: Composable functions
- **stores**: Pinia stores
- **api**: API endpoints
- **auth**: Authentication system
- **workspace**: Workspace functionality
- **company**: Company management
- **tasks**: Task system
- **ui**: UI/UX improvements
- **i18n**: Internationalization
- **config**: Configuration files

## 💡 Commit Message Examples

### Feature Additions
```bash
✨ feat(companies): add created_at and owner_username fields to company cards
✨ feat(auth): implement admin.workspaces role for workspace management
✨ feat(workspace): add workspace management page with CRUD operations
```

### Bug Fixes
```bash
🐛 fix(auth): resolve invalidRedirectUriMessage error on logout
🐛 fix(companies): fix empty owner_username display in company cards  
🐛 fix(migration): remove old migration files causing KeyError
```

### UI/UX Improvements
```bash
💄 style(sidebar): add workspace management link for admin users
💄 style(companies): improve company card layout with owner information
💄 style(modal): enhance workspace creation modal styling
```

### Refactoring
```bash
♻️ refactor(companies): convert from user-scoped to workspace-scoped access
♻️ refactor(auth): streamline permission checking with dependency injection
♻️ refactor(security): implement workspace-based access control
```

### Configuration & Setup
```bash
🔧 chore(keycloak): add admin.workspaces role to realm configuration
🔧 chore(docker): update docker-compose for development environment
🔧 chore(deps): update yarn dependencies to latest versions
```

### Database Changes
```bash
🗃️ feat(migration): add workspace_id to companies table for multi-tenancy
🗃️ fix(migration): resolve workspaces table missing error
🗃️ chore(db): reset database with clean migration state
```

### Documentation
```bash
📝 docs(claude): restructure documentation into .claude folder
📝 docs(auth): document roles and permissions system
📝 docs(git): add commit message guidelines with gitmoji
```

### Testing
```bash
🧪 test(companies): add tests for workspace-scoped company access
🧪 test(auth): add role-based permission testing
🧪 fix(tests): resolve failing tests after workspace refactor
```

### Performance
```bash
⚡ perf(companies): optimize paginated company queries
⚡ perf(auth): cache user roles for better performance
⚡ perf(api): implement database indexing for workspace queries
```

### Security
```bash
🔒 security(auth): implement workspace isolation for data access
🔐 security(api): add permission checks for company operations
🔒 security(db): add row-level security for workspace data
```

## 🔄 Multi-line Commit Format

For complex changes, use the extended format:

```bash
✨ feat(workspace): implement complete workspace management system

- Add workspace management page with grid layout
- Implement CRUD operations for workspaces
- Add admin.workspaces role checking in sidebar
- Create workspace creation/edit modal
- Add French translations for workspace UI
- Integrate with backend workspace API

Resolves: #123
Breaking Change: Companies are now workspace-scoped instead of user-scoped

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## 🚨 Important Rules

### MANDATORY Footer
**ALWAYS** include this footer in commits made by Claude:
```
🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Commit When Asked
- **NEVER commit automatically** - only when user explicitly requests
- **Run tests first** - `yarn test:all` must pass
- **Check git status** - ensure you're committing the right files
- **Verify changes** - review what's being committed

### Pre-commit Checklist
1. ✅ **Tests pass** (`yarn test:all`)
2. ✅ **Linting passes** (if applicable)
3. ✅ **Types check** (if applicable)  
4. ✅ **Files staged** correctly
5. ✅ **Commit message** follows format
6. ✅ **Claude footer** included

## 🎯 Quick Reference

### Common Patterns
```bash
# New feature
✨ feat(scope): add feature description

# Bug fix  
🐛 fix(scope): resolve issue description

# UI update
💄 style(scope): improve visual description

# Refactor
♻️ refactor(scope): restructure code description

# Config change
🔧 chore(scope): update configuration description

# Database
🗃️ feat(scope): add database changes description

# Documentation
📝 docs(scope): update documentation description
```

### Scope Selection Guide
- **Global changes**: No scope or `(app)`
- **Component changes**: `(components)` or specific component name
- **Page changes**: `(pages)` or specific page name
- **Feature area**: `(auth)`, `(workspace)`, `(company)`, `(tasks)`
- **Technical area**: `(api)`, `(db)`, `(config)`, `(tests)`

## 📖 References

- [Conventional Commits](https://www.conventionalcommits.org/)
- [Gitmoji Guide](https://gitmoji.dev/)
- [Semantic Versioning](https://semver.org/)

---

*Follow these guidelines for consistent and meaningful commit history*
*Last updated: 2025-07-31*