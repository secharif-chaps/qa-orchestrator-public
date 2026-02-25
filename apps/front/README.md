# ChapsMind Frontend

Vue 3 application with TypeScript, Tailwind CSS v4, and Pinia Colada.

## Stack

- **Framework**: Vue 3 + Composition API (`<script setup lang="ts">`)
- **Build**: Vite 7
- **Styling**: Tailwind CSS v4 + custom semantic color tokens
- **State**: Pinia + Pinia Colada (queries/mutations)
- **Routing**: unplugin-vue-router (file-based)
- **Auth**: Keycloak (OIDC)

## Commands

```bash
task front:dev        # Dev server with HMR (port 3000)
task front:lint       # Lint + fix
task front:typecheck  # TypeScript check
task front:build      # Production build
```

## Structure

```
src/
├── api/              # HTTP client functions
├── components/
│   ├── ui/           # Design system components
│   ├── layout/       # Layout components
│   └── features/     # Feature-specific components
├── composables/      # Composition functions
├── stores/           # Pinia stores
├── queries/          # Pinia Colada query definitions
├── mutations/        # Pinia Colada mutation definitions
├── pages/            # File-based routes
├── plugins/          # Vue plugins
├── utils/            # Utility functions
└── assets/           # Static assets
```