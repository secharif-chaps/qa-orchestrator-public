# ADR-2026-007: Migration from Nuxt 4 to Vue 3 with unplugin-vue-router

| Status   | Date       | Author      |
| -------- | ---------- | ----------- |
| Accepted | 2026-01-15 | Lucas GAULT |

## Context

Target's frontend (`pwa/`) currently uses **Nuxt 4** as a meta-framework built on Vue 3. The project needs to migrate to
a **pure Vue 3** application to integrate with an existing Vue 3 codebase (`chapsmind-workspace/front`) that already
uses `unplugin-vue-router` for file-based routing.

### Current State (Nuxt 4)

The Target frontend (`target/pwa/`) is built with:

- **Nuxt 4.0** with Vue 3 and TypeScript
- **File-based routing** via Nuxt's automatic route generation
- **@nuxtjs/i18n** for internationalization
- **@pinia/nuxt** for Pinia integration
- **useRuntimeConfig()** for environment variables
- **Nuxt-specific features**: `definePageMeta()`, `useHead()`, `defineNuxtPlugin()`, auto-imports
- **Keycloak JS** for authentication
- **SSR disabled** (SPA mode: `ssr: false`)

### Target State (Vue 3)

The target codebase (`chapsmind-workspace/front`) already has:

- **Vue 3.5** with TypeScript
- **unplugin-vue-router** for file-based routing (similar to Nuxt)
- **vue-i18n** (standard, not @nuxtjs/i18n)
- **Pinia** with standard integration (not @pinia/nuxt)
- **Vite** as build tool
- **oidc-client-ts** for authentication (not Keycloak JS) - Note: requires migration from deprecated `oidc-client`
- **Navigation guards** already implemented
- **Layout system** with `DefaultLayout` and `UnauthenticatedLayout`

### Requirements

1. **Preserve functionality**: All features from Target must work in the target codebase
2. **Independent configurations**: ChapsMind has its own configuration that won't interfere with Target's
3. **Maintainability**: Code should be maintainable and follow Vue 3 best practices
4. **Type safety**: Preserve TypeScript types and type safety
5. **Performance**: No degradation in application performance
6. **Testing**: Maintain test coverage during migration

### Constraints

| Constraint              | Description                                                            |
| ----------------------- | ---------------------------------------------------------------------- |
| **No SSR**              | Application runs in SPA mode only (no server-side rendering)           |
| **Existing codebase**   | Must integrate with existing Vue 3 project structure                   |
| **Authentication**      | Must migrate from Keycloak JS to oidc-client-ts                        |
| **Independent configs** | ChapsMind configuration is independent and won't interfere with Target |
| **Timeline**            | Migration should be incremental to minimize disruption                 |

## Decision Drivers

- **Framework alignment**: Target codebase uses Vue 3, not Nuxt
- **Code reuse**: Leverage existing infrastructure in target codebase
- **Maintainability**: Reduce framework-specific abstractions
- **Performance**: Vite provides faster builds than Nuxt's build system
- **Flexibility**: Pure Vue 3 offers more control over build configuration
- **Team expertise**: Team is familiar with Vue 3 patterns

## Considered Options

### Option A: Keep Nuxt 4 (Status Quo)

Continue using Nuxt 4 in a separate codebase.

**Pros:**

- No migration effort required
- All existing features work as-is
- Nuxt provides many conveniences (auto-imports, modules, etc.)

**Cons:**

- Cannot integrate with existing Vue 3 codebase
- Maintains two separate frontend codebases
- Framework lock-in with Nuxt-specific features
- Larger bundle size due to Nuxt runtime
- Less flexibility in build configuration

### Option B: Migrate to Pure Vue 3 with unplugin-vue-router (Selected)

Migrate all functionality to the existing Vue 3 codebase using `unplugin-vue-router` for file-based routing.

**Pros:**

- Integrates with existing Vue 3 codebase
- Single codebase to maintain
- More control over build and configuration
- Smaller bundle size (no Nuxt runtime)
- Uses standard Vue 3 patterns
- `unplugin-vue-router` provides similar file-based routing to Nuxt
- Better performance with Vite

**Cons:**

- Significant migration effort required
- Need to replace Nuxt-specific features:
    - `useRuntimeConfig()` → `import.meta.env` + `useConfig()` composable
    - `definePageMeta()` → `definePage()` from unplugin-vue-router
    - `useHead()` → `@vueuse/head` or `@unhead/vue`
    - `defineNuxtPlugin()` → standard Vue plugins
    - Auto-imports → explicit imports
- Authentication migration from Keycloak JS to oidc-client-ts
- Need to adapt layout system (Target uses route-based layouts like `watch-file`, ChapsMind uses auth-based layouts
  only)
- Testing infrastructure needs adaptation

### Option C: Hybrid Approach (Nuxt + Vue 3)

Keep both codebases and share components via npm packages or monorepo.

**Pros:**

- Gradual migration possible
- Can reuse components between projects

**Cons:**

- Complex build and dependency management
- Two codebases to maintain
- Version synchronization challenges
- Increased complexity

## Decision

We will **migrate to pure Vue 3 with unplugin-vue-router** (Option B).

### Rationale

1. **Integration**: The target codebase already has the necessary infrastructure (routing, i18n, state management)
2. **Maintainability**: Single codebase reduces maintenance overhead
3. **Performance**: Vite provides faster builds and better development experience
4. **Flexibility**: Pure Vue 3 offers more control without framework abstractions
5. **Future-proof**: Aligns with team's Vue 3 expertise and industry standards

## Implementation Strategy

### Phase 0: Pre-migration - Code Style Alignment

**Goal**: Align Target's ESLint/Prettier rules with ChapsMind **before** migration to avoid massive relinting during
integration.

1. **ESLint Migration**
    - Replace `@nuxt/eslint` with `@vue/eslint-config-typescript` + `@vue/eslint-config-prettier`
    - Align rules with ChapsMind configuration (`eslint.config.ts`)
    - Run linting on all Target code and fix errors

2. **Prettier Alignment**
    - Verify Prettier configurations are identical
    - Reformat Target code if necessary

3. **Stylelint Setup** (in ChapsMind)
    - **Context**: Target uses Stylelint for CSS/SCSS/Vue style linting, but ChapsMind workspace does not currently have
      Stylelint configured
    - **Action**: Add Stylelint configuration to ChapsMind workspace to maintain code quality standards
    - Install required dependencies:
        - `stylelint: ^16.19.1`
        - `stylelint-config-recommended`
        - `stylelint-config-tailwindcss`
        - `stylelint-config-recommended-vue` (for Vue SFC `<style>` blocks)
        - `stylelint-prettier` (to integrate with Prettier)
    - Create `.stylelintrc` configuration file aligned with Target's configuration:
        ```json
        {
            "extends": [
                "stylelint-config-recommended",
                "stylelint-config-tailwindcss",
                "stylelint-config-recommended-vue"
            ],
            "plugins": ["stylelint-prettier"],
            "rules": {
                "prettier/prettier": true
            }
        }
        ```
    - Add Stylelint scripts to `package.json`:
        - `stylelint:fix`: Check and fix CSS/SCSS/Vue style issues
        - `stylelint:check`: Check only (for CI)
    - Integrate Stylelint into lint-staged configuration for `.vue`, `.css`, and `.scss` files
    - **Note**: This ensures consistent CSS/SCSS code quality across both Target and ChapsMind modules

4. **Migration oidc-client → oidc-client-ts** (in ChapsMind)
    - Update dependency from `oidc-client` to `oidc-client-ts`
    - Adapt authentication code following
      the [migration guide](https://github.com/authts/oidc-client-ts/blob/main/docs/migration.md)
    - Key changes:
        - `loadUserInfo` defaults to `false` instead of `true`
        - Changes in `revokeTokens()` and `signoutPopupCallback()`
    - Validate that authentication works correctly

5. **Validation**
    - Ensure Target code passes ChapsMind linters (ESLint, Prettier, Stylelint) without errors
    - Ensure ChapsMind authentication works with oidc-client-ts
    - Verify Stylelint runs correctly on ChapsMind codebase

### Phase 1: Configuration and Dependencies

1. **Environment Variables Migration**
    - Replace `useRuntimeConfig()` with `import.meta.env` (Vite standard)
    - Create `useConfig()` composable to maintain similar API
    - Prefix all environment variables with `VITE_`
    - Configure Target API endpoint: `VITE_TARGET_API_BASE_URL` (ChapsMind has its own independent configuration)

2. **Dependencies**
    - Add missing dependencies: `@internationalized/date`, `dompurify`
    - Remove dependencies not needed in target: `zod` (Target-specific, not used in ChapsMind)
    - Update version mismatches: `@pinia/colada`, `@vueuse/core`, `marked`, etc.
    - Update `reka-ui` from 2.0.2 to 2.4.1 (minor breaking change: `VisuallyHidden` prop naming only)
    - Remove Nuxt-specific packages: `nuxt`, `@nuxtjs/i18n`, `@pinia/nuxt`
    - Add `@vueuse/head` or `@unhead/vue` for meta tag management

3. **Folder Structure - Step 1: Isolation**
    - Create a `/target` folder in `chapsmind-workspace/front/src/`
    - Copy all content from `target/pwa/` (adapted) into `src/target/`
    - Add Vite alias for `@target/*` → `src/target/*`
    - Initial structure:
        ```
        chapsmind-workspace/front/src/
        ├── target/           # Migrated Target code
        │   ├── components/
        │   ├── composables/
        │   ├── pages/
        │   ├── stores/
        │   └── ...
        ├── components/       # Existing ChapsMind code
        ├── composables/
        └── ...
        ```

### Phase 2: Core Functionality Migration

1. **Routing**
    - Migrate pages from `definePageMeta()` to `<route lang="yaml">` blocks (unplugin-vue-router format)
    - Convert route metadata including permissions to YAML format
    - Adapt middleware to Vue Router navigation guards (ChapsMind already has permission checking in `router/index.ts`)
    - **Layout handling**: Target uses route-based layouts (`watch-file` layout for WatchFile pages). ChapsMind only has
      auth-based layout selection. Options:
        - Extend ChapsMind's `App.vue` to support route-based layout selection via `to.meta.layout`
        - Integrate layout components directly into pages that need them

2. **Authentication**
    - Adapt Target's `useAuth()` composable to use OIDC UserManager
    - Map Keycloak configuration to OIDC settings
    - Handle token refresh and events via OIDC patterns

3. **API Layer**
    - ChapsMind has its own configuration and won't interfere with Target's
    - Migrate existing API queries and mutations for Target
    - Adapt `useAppFetch()` for Target API endpoint
    - No need to create separate composables as ChapsMind configuration is independent

### Phase 3: Components and Features

1. **Components Migration**
    - Copy components to `src/target/components/` (see Phase 1.3 for structure)
    - Replace `~/` imports with `@target/` alias
    - Remove `#imports` occurrences (Nuxt auto-imports)

2. **Plugins and Directives**
    - Convert `defineNuxtPlugin()` to standard Vue plugin functions
    - Create `v-sanitize-html` directive (replacing Nuxt plugin)
    - Migrate auth and clarity plugins

3. **Meta Tags**
    - Install and configure `@vueuse/head` or `@unhead/vue`
    - Replace `useHead()` calls with library's `useHead()`
    - Migrate `useLocaleHead()` functionality

### Phase 4: Internationalization

1. **i18n Configuration**
    - Migrate `datetimeFormats` from Nuxt i18n config
    - Adapt locale formats (`en`/`fr` vs `en-US`/`fr-FR`)
    - Copy and merge translation files
    - **Note**: ChapsMind workspace uses `.ts` files for translations, Target uses `.json` files. Both formats can
      coexist initially during migration

2. **Future Improvements** (out of scope for initial migration)
    - Align translation keys between modules (standardization)
    - Implement lazy loading of translation files per module/route to optimize initial bundle:
        ```typescript
        // Example: load Target translations only on Target pages
        async function loadTargetTranslations(locale: string) {
            const messages = await import(`@target/i18n/${locale}.json`)
            i18n.global.mergeLocaleMessage(locale, {
                target: messages.default,
            })
        }
        ```

### Phase 5: Testing and Validation

1. **Test Infrastructure**
    - Adapt test mocks (remove Nuxt-specific mocks)
    - Update test utilities
    - Verify all tests pass

2. **Manual Testing**
    - Test critical user flows
    - Verify authentication works
    - Check API integrations
    - Validate routing and navigation

### Note on Code Style and Linters

See **Phase 0** for linter alignment. The goal is to align Target with ChapsMind configuration **before** migration to
avoid massive relinting.

Target configuration (ChapsMind):

- **ESLint 9** with `@vue/eslint-config-typescript` and `@vue/eslint-config-prettier`
- **Prettier** for formatting
- **Stylelint** for CSS/SCSS/Vue style linting (to be added in Phase 0)
- **TypeScript** strict mode via `@vue/tsconfig`

## Key Migration Patterns

### Pattern 1: Runtime Config → Vite Environment Variables

**Before (Nuxt):**

```typescript
const config = useRuntimeConfig()
const apiUrl = config.public.apiBaseUrl
```

**After (Vite):**

```typescript
// composables/useConfig.ts
export function useConfig() {
    return {
        public: {
            apiBaseUrl: import.meta.env.VITE_API_BASE_URL || '/api',
            // ...
        },
    }
}

// Usage
const config = useConfig()
const apiUrl = config.public.apiBaseUrl
```

### Pattern 2: Page Meta Definition

**Before (Nuxt):**

```typescript
definePageMeta({
    layout: 'watch-file',
    name: RouteNames.WATCH_FILES,
})
```

**After (Vue 3 + unplugin-vue-router):**

```vue
<route lang="yaml">
meta:
# Note: layout is handled differently in ChapsMind (see Pattern 6)
# Custom route name if needed
</route>
```

Or using `definePage()`:

```typescript
import { definePage } from 'vue-router/auto'

definePage({
    meta: {
        // custom metadata
    },
})
```

**Note**: Route names are auto-generated from file paths in unplugin-vue-router (e.g., `/watch_files/[id]`).

### Pattern 3: Meta Tags Management

**Before (Nuxt):**

```typescript
useHead({
    title: t('watch_files.title'),
})
```

**After (Vue 3 + @vueuse/head):**

```typescript
import { useHead } from '@vueuse/head'

useHead({
    title: t('watch_files.title'),
})
```

### Pattern 4: HTML Sanitization Directive

**Before (Nuxt Plugin):**

```typescript
export default defineNuxtPlugin((nuxtApp) => {
    nuxtApp.vueApp.directive('sanitize-html', {
        /* ... */
    })
})
```

**After (Vue 3 Directive):**

```typescript
// directives/sanitizeHtml.ts
export function setupSanitizeHtmlDirective(app: App) {
    app.directive('sanitize-html', {
        beforeMount(el, binding) {
            el.innerHTML = DOMPurify.sanitize(binding.value, sanitizeConfig)
        },
        updated(el, binding) {
            el.innerHTML = DOMPurify.sanitize(binding.value, sanitizeConfig)
        },
    })
}
```

### Pattern 5: Authentication Migration

**Before (Keycloak JS):**

```typescript
import Keycloak from 'keycloak-js'
const keycloak = new Keycloak({ url, realm, clientId })
await keycloak.init({ onLoad: 'login-required' })
```

**After (oidc-client-ts):**

```typescript
import { UserManager } from 'oidc-client-ts'
const userManager = new UserManager({
    authority: oidcBaseUrl,
    client_id: clientId,
    redirect_uri: window.location.origin + '/auth/callback',
    // Note: loadUserInfo defaults to false in oidc-client-ts (was true in oidc-client)
    loadUserInfo: true,
    // ...
})
await userManager.signinRedirect()
```

### Pattern 6: Route Permissions (Nuxt → unplugin-vue-router)

**Before (Nuxt):**

```typescript
definePageMeta({
    middleware: ['auth', 'admin'],
})
```

**After (Vue 3 + unplugin-vue-router):**

```vue
<route lang="yaml">
meta:
permissions:
    - admin.workflows
</route>
```

The router in ChapsMind already handles permission checking via navigation guards (`router/index.ts:64-83`).

**Note on Layouts**: ChapsMind does not use route-based layout selection. Instead, layouts are determined by
authentication state in `App.vue`:

- `DefaultLayout` for authenticated users
- `UnauthenticatedLayout` for unauthenticated users

If Target needs custom layouts per route, this system would need to be extended.

## Consequences

### Positive

1. **Single codebase**: Reduced maintenance overhead
2. **Better performance**: Vite provides faster builds and HMR
3. **More flexibility**: Full control over build configuration
4. **Standard patterns**: Uses Vue 3 standard practices
5. **Smaller bundle**: No Nuxt runtime overhead
6. **Team alignment**: Aligns with existing Vue 3 expertise

### Negative

1. **Migration effort**: Significant time investment required
2. **Breaking changes**: Some Nuxt-specific features need reimplementation
3. **Authentication complexity**: Keycloak → OIDC migration requires careful mapping
4. **Testing updates**: Test infrastructure needs adaptation
5. **Learning curve**: Team needs to understand new patterns (though minimal)

### Risks and Mitigations

| Risk                                 | Mitigation                                                     |
| ------------------------------------ | -------------------------------------------------------------- |
| **Feature loss during migration**    | Comprehensive testing and feature parity checklist             |
| **Authentication issues**            | Thorough testing of OIDC integration, fallback plans           |
| **oidc-client-ts migration**         | Done in Phase 0 before Target migration, follow official guide |
| **Performance regression**           | Performance benchmarks before/after migration                  |
| **Breaking changes in dependencies** | Version pinning and gradual updates                            |
| **Type safety issues**               | TypeScript strict mode and comprehensive type checking         |

### Open Questions

1. **Permissions structure**: Clarify with ChapsMind the permissions system (`meta.permissions` in routes). How to align
   for Target?
2. **Auto-imports**: Whether to implement auto-imports via `unplugin-auto-import` or keep explicit imports

### Deferred Decisions

The following items will be addressed after the initial migration:

1. **Module reorganization**: Restructure shared folders into subdirectories by module:
    ```
    src/
    ├── components/
    │   ├── common/       # Shared components
    │   ├── explore/      # ChapsMind (Explore) components
    │   └── target/       # Target components
    ├── composables/
    │   ├── common/
    │   ├── explore/
    │   └── target/
    ├── stores/
    │   ├── common/
    │   ├── explore/
    │   └── target/
    └── ...
    ```
2. **i18n key alignment**: Standardize translation keys between modules (explore/target/common)
3. **i18n lazy loading**: Load translation files per module based on navigation
4. **i18n file format**: Long-term decision on `.ts` vs `.json` (both coexist during migration)

## References

- [unplugin-vue-router Documentation](https://github.com/posva/unplugin-vue-router)
- [Vite Environment Variables](https://vite.dev/guide/env-and-mode.html)
- [Vue 3 Composition API](https://vuejs.org/guide/extras/composition-api-faq.html)
- [@vueuse/head Documentation](https://github.com/vueuse/head)
- [oidc-client-ts Documentation](https://github.com/authts/oidc-client-ts) (successor to oidc-client-js)
- [oidc-client-ts Migration Guide](https://github.com/authts/oidc-client-ts/blob/main/docs/migration.md)
