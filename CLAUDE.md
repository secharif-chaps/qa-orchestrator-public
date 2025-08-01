# Claude AI Assistant Instructions

## Important: Project Documentation

This project maintains detailed documentation in the `.claude/` folder that MUST be followed:

- **Git Commits**: Always follow `.claude/git-commit-guide.md` for ALL commits
- **Theme Guidelines**: Follow `.claude/theme.md` for styling
- **API Patterns**: Use `.claude/api-patterns.md` for API integration
- **Project Overview**: See `.claude/index.md` for setup and architecture

## Key Requirements

1. **ALWAYS** check and follow the guidelines in `.claude/` folder before performing any task
2. **NEVER** commit without following the git commit format with gitmojis
3. When in doubt, read the relevant `.claude/` documentation first
4. Run tests before committing when available (yarn test:all)

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

## Important Reminders

- Only commit when explicitly asked by the user
- Include Claude footer in commit messages
- Follow existing code patterns and conventions
- Check for lint/type errors before committing