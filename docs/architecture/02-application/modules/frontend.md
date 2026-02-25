# Frontend Module

<!-- TODO: To be completed - detailed frontend architecture -->

The ChapsMind frontend is a Vue.js 3 single-page application (SPA) using the Composition API and TypeScript.

## Technology Stack

| Category | Technology |
|----------|------------|
| **Framework** | Vue 3 with Composition API |
| **Language** | TypeScript (strict mode) |
| **Build Tool** | Vite 7 |
| **Styling** | Tailwind CSS v4 |
| **State Management** | Pinia + Pinia Colada |
| **Routing** | Vue Router with unplugin-vue-router |
| **i18n** | vue-i18n |

## Architecture Overview

```mermaid
flowchart TB
    subgraph Routing["Routing Layer"]
        Router["Vue Router"]
        Guards["Navigation Guards"]
    end

    subgraph Pages["Page Layer"]
        PageComponents["Page Components<br/>(src/pages/)"]
    end

    subgraph Components["Component Layer"]
        Features["Feature Components"]
        UI["UI Components"]
        Layout["Layout Components"]
    end

    subgraph State["State Layer"]
        Stores["Pinia Stores"]
        Queries["Pinia Colada Queries"]
        Mutations["Pinia Colada Mutations"]
    end

    subgraph API["API Layer"]
        Functions["API Functions<br/>(src/api/)"]
        Client["ApiClient"]
    end

    Router --> Guards
    Guards --> Pages
    Pages --> Components
    Pages --> State
    Components --> State
    Queries --> Functions
    Mutations --> Functions
    Functions --> Client
    Client --> Backend["Backend API"]
```

## Directory Structure

```
src/
├── api/              # Pure HTTP functions
├── components/       # Vue components
│   ├── ui/          # Generic UI components
│   ├── layout/      # Layout structure
│   └── features/    # Feature-specific components
├── composables/     # Composition functions (Vue-specific)
├── helpers/         # Reusable helper functions
│   └── cache/       # Cache management helpers for Pinia Colada
├── stores/          # Pinia global stores
├── queries/         # Pinia Colada queries
├── mutations/       # Pinia Colada mutations
├── pages/           # File-based routing
├── types/           # TypeScript interfaces
├── utils/           # Pure utility functions
├── i18n/            # Translations
├── config/          # Configuration files
├── router/          # Vue Router configuration
├── layouts/         # Layout components
└── assets/          # Static assets (CSS, images)
```

### Helpers vs Composables vs Utils

| Folder | Purpose | Example |
|--------|---------|---------|
| `helpers/` | Reusable logic for specific domains (cache, mutations) | `rollbackCacheChanges()`, `updateFolderInCache()` |
| `composables/` | Vue-specific composition functions (use refs, computed) | `useCompanyPermissions()`, `useFolderTree()` |
| `utils/` | Pure utility functions (no Vue dependency) | `formatDate()`, `debounce()` |

## Related Documentation

- [Frontend Architecture](../../03-development/frontend-architecture.md) - Detailed implementation
- [ADR-0001: Vue.js 3](../../adr/0001-vue3-composition-api.md) - Framework decision
- [ADR-0006: Pinia Colada](../../adr/0006-pinia-colada-data-fetching.md) - Data fetching decision
