# ChapsMind Front

Vue 3 frontend application for ChapsMind, part of the [monorepo](../../README.md).

## Tech Stack

- **Vue 3** — Composition API with `<script setup lang="ts">`
- **TypeScript 5.8**
- **Tailwind CSS v4**
- **Vite 7**
- **Vuellar** (`@owlint/feathers-vue`) — ChapsVision Design System
- **Reka UI** — Headless UI primitives
- **Pinia 3** — State management
- **Pinia Colada** — Data fetching with caching
- **Vue Router** — File-based routing (`unplugin-vue-router`)
- **Vue I18n** — Internationalization (English/French)
- **Keycloak** — OIDC authentication with organization-based multi-tenancy

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

The frontend runs inside Docker (via `docker compose`). No local Node.js required for day-to-day development.

### Task Commands

From the **monorepo root**:

```bash
task front:lint         # ESLint + Prettier + Stylelint
task front:typecheck    # TypeScript type checking
task front:test         # Run Vitest tests
```

From this directory (`apps/front/`):

| Command               | Description                                   |
| --------------------- | --------------------------------------------- |
| `task lint`           | Run all linters (ESLint, Prettier, Stylelint) |
| `task lint:eslint`    | ESLint only                                   |
| `task lint:prettier`  | Prettier only (check mode)                    |
| `task lint:stylelint` | Stylelint only                                |
| `task lint:fix`       | Auto-fix all linting issues                   |
| `task typecheck`      | TypeScript type checking                      |
| `task test`           | Run Vitest tests                              |

### CI Pipeline

The GitLab CI runs the same linters on every push and every MR. A failing linter blocks the pipeline.
