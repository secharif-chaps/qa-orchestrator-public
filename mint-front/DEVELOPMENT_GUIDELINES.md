# Mint-Front Development Guidelines

## 📋 Core Rules

### Package Management
- ✅ **ALWAYS use `yarn`** for package management (never npm)
- ✅ Run `yarn` to install dependencies
- ✅ Use `yarn add` or `yarn add -D` for new packages

### Testing Standards
- ✅ **All tests must pass** before any commit
- ✅ Write tests for new features using existing patterns:
  - Unit tests: `tests/unit/**/*.test.ts` 
  - E2E tests: `tests/e2e/**/*.test.ts`
  - UI tests: `tests/unit/**/*.ui.test.ts`
- ✅ Run test commands:
  - `yarn test:unit` - Full unit tests (41 tests)
  - `yarn test:e2e` - Integration tests (26 tests) 
  - `yarn test:ui` - Visual UI tests (23 tests)

### Code Quality
- ✅ **No comments in code** unless explicitly requested
- ✅ Follow existing code patterns and conventions
- ✅ Use TypeScript strictly
- ✅ Follow Vue 3 Composition API patterns

### Nuxt Specific Rules
- ✅ Use Nuxt auto-imports (no manual imports for composables)
- ✅ Follow Nuxt file-based routing conventions
- ✅ Use `~/` alias for project root imports
- ✅ Use `@/` alias for project root imports (alternative)

### Git Workflow
- ✅ **Never commit** unless explicitly asked
- ✅ Write descriptive commit messages following project style
- ✅ Always check git status before commits

### Security
- ✅ **Never expose secrets** or API keys
- ✅ Use environment variables for configuration
- ✅ Follow secure coding practices

## 🧪 Test Environment Setup

### Current Test Configurations:
- **Unit tests**: Nuxt environment with full auto-imports
- **E2E tests**: jsdom environment with MSW mocking
- **UI tests**: jsdom environment optimized for Vitest UI

### Mock Patterns:
- Use `vi.mock()` for module mocking in unit tests
- Use MSW for API mocking in E2E tests
- Follow existing mock patterns in test files

## 🔧 Available Commands

```bash
# Development
yarn dev              # Start dev server
yarn build           # Build for production
yarn preview         # Preview production build

# Testing
yarn test:unit       # Run unit tests (Nuxt env)
yarn test:e2e        # Run integration tests (jsdom + MSW)
yarn test:ui         # Run with visual UI interface
yarn test:watch      # Run tests in watch mode

# Quality
yarn lint            # Run linting (if available)
yarn typecheck       # Run type checking (if available)
```

## 🚫 What NOT to Do

- ❌ Don't use npm (always use yarn)
- ❌ Don't commit without being asked
- ❌ Don't add comments unless requested
- ❌ Don't create unnecessary files
- ❌ Don't break existing tests
- ❌ Don't expose sensitive information
- ❌ Don't change package.json scripts without discussion

## 📁 Project Structure

```
mint-front/
├── composables/          # Nuxt composables
├── pages/               # Nuxt pages (file-based routing)
├── components/          # Vue components
├── tests/
│   ├── unit/           # Unit tests (Nuxt environment)
│   ├── e2e/            # Integration tests (MSW mocking)
│   ├── setup.unit.ts   # Unit test setup
│   ├── setup.e2e.ts    # E2E test setup
│   └── setup.ui.ts     # UI test setup
├── vitest.config.ts     # Main Vitest config
├── vitest.config.unit.ts # Unit test config
├── vitest.config.e2e.ts  # E2E test config
└── vitest.config.ui.ts   # UI test config
```

## 💡 Best Practices

1. **Prefer editing existing files** over creating new ones
2. **Follow existing patterns** in the codebase
3. **Test thoroughly** before considering changes complete
4. **Keep it simple** - don't over-engineer solutions
5. **Ask before major changes** to architecture or dependencies

---

*Last updated: 2025-07-04*
*This document should be followed for all development work on mint-front*