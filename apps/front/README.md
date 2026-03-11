# ChapsMind Front

Vue 3 frontend application for ChapsMind, part of the [monorepo](../../README.md).

## Tech Stack

### Frontend Framework

- **Vue 3** - Composition API with `<script setup lang="ts">`
- **TypeScript 5.8** - Type-safe development
- **Tailwind CSS v4** - Utility-first CSS framework
- **Vite 7** - Build tool

### UI Components

- **Vuellar** (`@owlint/feathers-vue`) - ChapsVision Design System
- **Reka UI** - Headless UI primitives for accessibility
- Custom components in `src/components/ui/`

### State Management & Data Fetching

- **Pinia 3** - Global state management
- **Pinia Colada** - Async state management and data fetching with caching

### Routing & Internationalization

- **Vue Router** - Client-side routing with file-based routing (`unplugin-vue-router`)
- **Vue I18n** - Internationalization (English/French)

### Authentication & Authorization

- **Keycloak** - Identity and access management
- **Keycloak Organizations** - Multi-tenant organization support
- **OIDC Client** - OpenID Connect authentication
- Role-based permissions extracted from JWT tokens

## Project Structure

```
src/
├── api/              # HTTP client and API functions
├── components/       # Vue components
├── composables/      # Composition functions
├── config/           # App configuration
├── helpers/          # Helper utilities
├── i18n/             # Internationalization (en/fr)
├── layouts/          # Layout components
├── mutations/        # Pinia Colada mutations (cache invalidation)
├── pages/            # Page components (file-based routing)
├── queries/          # Pinia Colada queries (data fetching + caching)
├── router/           # Vue Router configuration
├── stores/           # Pinia stores (global state)
├── target/           # Target module
├── types/            # TypeScript type definitions
├── utils/            # Utility functions
└── tests/            # Vitest tests
```

## Development

### Task Commands

From the **monorepo root**:

```bash
task front:dev          # Start dev server with HMR
task front:lint         # Lint and fix
task front:typecheck    # TypeScript type checking
task front:build        # Production build
```

From this directory:

| Command               | Description                                   |
| --------------------- | --------------------------------------------- |
| `task lint`           | Run all linters (ESLint, Prettier, Stylelint) |
| `task lint:eslint`    | ESLint only                                   |
| `task lint:prettier`  | Prettier only (check mode)                    |
| `task lint:stylelint` | Stylelint only                                |
| `task lint:fix`       | Auto-fix all linting issues                   |
| `task lint:staged`    | Lint only staged files (via lint-staged)      |
| `task hook:install`   | Install git hooks (pre-commit)                |

### CI Pipeline

The GitLab CI runs the same 3 linters on every push and every MR. A failing linter blocks the pipeline.
