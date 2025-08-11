# Claude AI Assistant Instructions

## Important: Project Documentation

This project maintains detailed documentation in the `.claude/` folder that MUST be followed:

- **Project Overview**: See `.claude/index.md` for setup and architecture
- **Git Commits**: Always follow `.claude/git-commit-guide.md` for ALL commits
- **Theme Guidelines**: Follow `.claude/theme.md` for styling and color system
- **API Patterns**: Use `.claude/api-patterns.md` for API integration patterns
- **Permission System**: Follow `.claude/permissions.md` for implementing access control
- **Routing**: See `.claude/routing.md` for Vue Router patterns and file-based routing
- **Test Users**: Use `.claude/test-users.md` for testing different permission levels

## Key Requirements

1. **ALWAYS** check and follow the guidelines in `.claude/` folder before performing any task
2. **NEVER** commit without following the git commit format with gitmojis
3. When in doubt, read the relevant `.claude/` documentation first
4. Run tests before committing when available (yarn test:all)
5. **ALWAYS** implement proper permission checks using resource-based composables
6. Use test users from `.claude/test-users.md` to verify permission functionality

## Git Commit Format (Quick Reference)

Format: `<gitmoji> <type>(<scope>): <description>`

Examples:
- `✨ feat(companies): add new company listing feature`
- `🐛 fix(auth): resolve login redirect issue`
- `💄 style(ui): improve button hover states`
- `♻️ refactor(api): restructure API client`
- `🔧 chore(deps): update dependencies`

## Project Stack

- Vue 3 + TypeScript
- Pinia for state management
- Vue Router for navigation
- Tailwind CSS for styling
- Workspace-based multi-tenancy
- Keycloak for authentication & authorization
- Resource-based permission system

## Permission System

This project implements a granular permission system:

- **Resource-based composables**: Use `useCompanyPermissions()` for company-related features
- **Route guards**: All pages have permission requirements defined in `<route>` blocks
- **UI conditional rendering**: Hide/show elements based on user permissions
- **Backend integration**: Permissions are synced between Keycloak and database

### Available Permissions
- `company.create`, `company.update`, `company.delete`, `company.view`
- `workspace.read`, `workspace.write`
- `admin.workspaces`

### Testing Permissions
Use test users defined in `.claude/test-users.md` to test different permission scenarios.

## UI Components

- **ALWAYS use custom UI components** from `@/components/ui/` instead of third-party libraries when available:
  - **Alert**: Use `Alert` component instead of `OAlert` (Feathers) or `RAlert` (Reka)
    - For warnings, errors, info messages, and important notifications
  - **Input**: Use `Input` component instead of `OInput` (Feathers)
    - For all form inputs, search fields, and text entry
  - **Badge**: Use `Badge` component instead of any third-party badge/chip/tag components
    - For status indicators, counts, labels, tags, and small metadata
- These custom components provide:
  - Theme-aware styling that works in both light and dark modes
  - Consistent design language across the application
  - Subtle gradients and modern aesthetics
  - Better TypeScript support
- See `src/components/CLAUDE.md` for detailed component usage and examples

## Important Reminders

- Only commit when explicitly asked by the user
- Include Claude footer in commit messages
- Follow existing code patterns and conventions
- Check for lint/type errors before committing