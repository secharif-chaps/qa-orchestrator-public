# Claude Development Documentation

## 📁 Documentation Structure

This folder contains structured documentation for AI assistants working on the mint-front project.

### 📋 Available Guides

| File | Description | When to Use |
|------|-------------|-------------|
| **[README.md](./README.md)** | Main development guidelines | Start here - general rules & Vue 3 best practices |
| **[roles-permissions.md](./roles-permissions.md)** | Authentication & authorization | Working with user roles, permissions, workspace access |
| **[git-commit-guide.md](./git-commit-guide.md)** | Commit message standards | Before making any commits |
| **[components.md](./components.md)** | Component usage notes | When working with UI components |

### 🚀 Quick Start

1. **Read [README.md](./README.md)** for general development rules
2. **Check [roles-permissions.md](./roles-permissions.md)** if working with auth/permissions
3. **Follow [git-commit-guide.md](./git-commit-guide.md)** when committing changes
4. **Reference [components.md](./components.md)** for component-specific requirements

### 🔧 Key Technologies

- **Vue 3** with Composition API (`<script setup>`)
- **Nuxt 3** for framework and auto-imports
- **TypeScript** for type safety
- **Pinia** for state management
- **@owlint/feathers-vue** for UI components (priority)
- **Reka UI** for advanced components (secondary)
- **Keycloak** for authentication
- **i18n** for internationalization

### 🧪 Testing

- **Unit tests**: `yarn test:unit` (41 tests)
- **E2E tests**: `yarn test:e2e` (26 tests)
- **UI tests**: `yarn test:ui` (23 tests)
- **All tests**: `yarn test:all`

### 📝 Important Reminders

- ✅ **Always use `yarn`** (never npm)
- ✅ **Tests must pass** before completion
- ✅ **Use `<script setup>`** exclusively
- ✅ **Never commit** without being asked
- ✅ **Follow existing patterns**
- ✅ **Include Claude footer** in commits

---

*Structured documentation for consistent development practices*
*Last updated: 2025-07-31*