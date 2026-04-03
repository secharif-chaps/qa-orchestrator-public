# Claude AI Assistant Instructions

## Agent Behavior Rules

### 1. Plan First

- Enter plan mode for any non-trivial task (3+ steps or architecture decisions)
- Write detailed specs upfront to reduce ambiguity
- If something goes wrong, stop and re-plan immediately — don't push through
- Validate the plan with the user before starting implementation
- Before implementing any visual change, file restructuring, or Jira ticket creation: describe your planned approach in 2-3 bullet points and wait for confirmation before writing any code

### 2. Sub-Agent Strategy

- Use sub-agents extensively to keep the main context window clean
- Delegate research, exploration, and parallel analysis to sub-agents
- One task per sub-agent for focused execution
- For complex problems, use more compute via parallel sub-agents

### 3. Self-Improvement Loop

- After any user correction: save a feedback memory with the lesson learned
- Write rules for yourself that prevent the same mistake
- Review relevant memories at the start of related tasks

### 4. Verify Before Completing

- Never mark a task as done without proving it works
- Run tests, check logs, demonstrate the fix
- Ask yourself: "Is this complete, correct, and ready to ship?"
- Compare behavior between main branch and your changes when relevant

### 5. Require Elegance (Balanced)

- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer

### 6. Autonomous Bug Fixing

- When given a bug report: just fix it. Don't ask for hand-holding
- Point to logs, errors, failing tests — then resolve them
- Go fix failing CI tests without being told how

---

## Project Structure

This project is a **monorepo** containing all ChapsMind applications:

```
chapsmind/                              # THIS REPO - Monorepo
├── CLAUDE.md                           # This file - Claude configuration
├── .claude/
│   ├── agents/                         # Specialized AI agents
│   ├── commands/                       # Custom slash commands
│   └── skills/                         # Shared skills
├── agent-os/                           # Agent OS configuration
│   ├── config.yml
│   ├── product/                        # Product docs (mission, roadmap, tech-stack)
│   ├── specs/                          # Feature specifications
│   └── standards/                      # Coding standards
├── docs/                               # Documentation (ADRs, architecture, product)
│
├── apps/
│   ├── front/                          # Frontend (Vue 3)
│   │   ├── src/
│   │   │   ├── api/                    # API functions (fetch wrappers)
│   │   │   ├── components/
│   │   │   │   ├── ui/                 # Base UI components
│   │   │   │   ├── layout/             # Layout components
│   │   │   │   └── features/           # Feature-specific components
│   │   │   ├── composables/            # Composition functions
│   │   │   ├── stores/                 # Pinia stores (global state)
│   │   │   ├── queries/                # Pinia Colada queries
│   │   │   ├── pages/                  # Page components (file-based routing)
│   │   │   ├── plugins/                # Vue plugins
│   │   │   ├── utils/                  # Utility functions
│   │   │   ├── assets/                 # Static assets (CSS, images)
│   │   │   ├── main.ts                 # App entry point
│   │   │   └── App.vue                 # Root component
│   │   └── public/                     # Public static files
│   │
│   ├── screen/                         # Backend (FastAPI)
│   │   ├── app/
│   │   │   ├── api/                    # API endpoints
│   │   │   ├── models/                 # SQLAlchemy models
│   │   │   ├── schemas/                # Pydantic schemas
│   │   │   ├── services/               # Business logic
│   │   │   └── core/                   # Core configuration
│   │   └── alembic/                    # Database migrations
│   │
│   └── global-service/                 # Global service
│
├── infra/                              # Infrastructure
│   ├── compose.yaml                    # Base compose
│   ├── compose.local.yaml              # Local overrides
│   └── ...                             # Other infra configs
│
├── scripts/                            # CI scripts, subtree sync
└── Taskfile.yml                        # Task runner (all commands)
```

---

# ChapsMind Frontend Application

A modern Vue 3 application with TypeScript, Tailwind CSS v4, and comprehensive tooling for monitoring companies online.

## Project Overview

### User Application Workflow

**Organizations**

- Users create company cards to monitor companies' information online
- A user always belongs to an organization
- Company cards are shared within an organization

**Company Lifecycle & Tasks**

- Creating a company card triggers tasks that find specific information online via Dify workflows
- Frontend monitors task status and calls the backend to start tasks

**Key Components**: User, Company, Organization, Tasks

**User Experience**:

1. Dashboard: See stats and recent companies
2. Companies List View: Browse all companies
3. Company View: Detailed information about a company

---

## Tech Stack

- **Framework**: Vue 3 with Composition API + `<script setup lang="ts">`
- **Language**: TypeScript
- **Styling**: Tailwind CSS v4
- **UI Components**: Custom design system + Reka UI
- **State Management**: Pinia (global state)
- **Data Fetching**: Pinia Colada (queries & mutations)
- **Routing**: Vue Router + unplugin-vue-router (file-based)
- **Authentication**: Keycloak with role-based permissions
- **Architecture**: Organization-based multi-tenancy (Keycloak Organizations)

---

## Development Standards

### Core Principles

1. **ALWAYS** use Composition API with `<script setup lang="ts">`, **NEVER** Options API
2. **ALWAYS** use TypeScript, prefer `interface` over `type`
3. **ALWAYS** use Tailwind CSS classes, avoid manual CSS
4. **DO NOT** hard-code colors, use semantic color tokens (see Color System below)
5. **ALWAYS** use arrow functions for all functions and methods
6. **ALWAYS** prefer named exports over default exports
7. Add meaningful comments explaining **why**, not **what**

### Layout & Spacing

**CRITICAL**: Use flexbox with gap utilities for spacing, **NEVER** use margin-based spacing between sibling elements.

#### Spacing Rules

1. **ALWAYS use `flex flex-col gap-{size}` on parent containers** to manage spacing between children
2. **NEVER use margin-bottom (`mb-*`) or margin-top (`mt-*`)** between sibling elements
3. **Parent controls spacing**, not children - this creates more maintainable, harmonious layouts
4. **Use gap utilities**: `gap-4` (16px), `gap-6` (24px), `gap-8` (32px)

#### Example Pattern

```vue
<!-- CORRECT: Parent controls spacing with gap -->
<template>
  <div class="flex flex-col gap-4">
    <PageHeader />
    <Filters />
    <Alert v-if="error" />
    <DataTable />
    <Pagination />
  </div>
</template>

<!-- INCORRECT: Margin-based spacing -->
<template>
  <div>
    <PageHeader class="mb-8" />
    <Filters class="mb-6" />
    <Alert v-if="error" class="mb-6" />
    <DataTable class="mb-4" />
    <Pagination />
  </div>
</template>
```

#### Benefits

- **Consistent spacing** - one gap value controls all spacing
- **Easier maintenance** - change spacing in one place
- **Cleaner code** - no margin classes scattered throughout
- **Predictable layouts** - parent always controls child spacing

### Color System (DaisyUI-Inspired Semantic Tokens)

The application uses a semantic color token system similar to DaisyUI. **NEVER** use raw color values or numbered palette colors directly.

#### Available Semantic Colors

- `primary` - Main brand color (Sage green)
- `secondary` - Secondary brand color (Almond)
- `accent` - Accent color (Rose)
- `success` - Success states (Green)
- `warning` - Warning states (Orange)
- `error` - Error states (Red)
- `info` - Information states (Blue)

#### Token Pattern

Each semantic color has:
- **Solid variant**: `{color}` - For solid backgrounds (buttons, badges)
- **Solid content**: `{color}-content` - Text/icons on solid backgrounds (ensures accessibility)
- **Light variant**: `{color}-light` - For light backgrounds (alerts, toasts, light badges)
- **Light content**: `{color}-light-content` - Text/icons on light backgrounds
- **Stroke**: `{color}-stroke` - For borders

#### Usage Examples

```vue
<!-- Success Alert (light background) -->
<div class="bg-success-light text-success-light-content border border-success-stroke">
  Success message
</div>

<!-- Success Button (solid background) -->
<button class="bg-success text-success-content">
  Confirm
</button>

<!-- Primary Badge (light) -->
<span class="bg-primary-light text-primary-light-content border border-primary-stroke">
  Badge
</span>

<!-- Error Alert -->
<div class="bg-error-light text-error-light-content border border-error-stroke">
  Error message
</div>

<!-- Info Button (solid) -->
<button class="bg-info text-info-content">
  Learn More
</button>
```

#### Base Colors (Background Layering)

Use for application background hierarchy:
- `bg-base-100` - Main background (white/dark)
- `bg-base-200` - Elevated surfaces (cards, modals)
- `bg-base-300` - Further elevated (nested cards)

#### Border Usage

Use `-stroke` tokens for borders without repeating "border":
```vue
<!-- Correct -->
<div class="border border-primary-stroke">...</div>

<!-- Incorrect - Don't use palette colors -->
<div class="border border-primary-200">...</div>
```

#### Rules

**DO**:
- Use semantic tokens: `bg-success`, `text-success-content`
- Pair colors with their `-content` variant for accessibility
- Use `-light` variants for alerts, toasts, and subtle backgrounds
- Use `-stroke` for borders

**DON'T**:
- Use palette colors directly: ~~`bg-green-500`~~, ~~`text-red-600`~~
- Mix incompatible pairs: ~~`bg-success text-error-content`~~
- Use raw hex colors: ~~`#29ad72`~~

### File Organization

- Keep types alongside code
- Keep tests alongside files: `Button.vue` + `Button.spec.ts`
- Use consistent PascalCase for component files
- Use kebab-case for other files

### Dev Environment

- All services run behind nginx reverse proxy at `http://localhost`
- Keycloak admin console at `http://localhost:8080`
- **NEVER** launch the dev server yourself (it's already running in Docker)
- Screen backend is **never** exposed directly — global-service is the API gateway

---

## Specialized Agents

This project uses specialized agents for specific domains. Use them proactively:

### Frontend Design System Agent (`frontend-design-system-dev`)

**Use for**: All frontend UI/UX development, component creation, styling, and design system compliance

**Responsibilities**:

- Creating/modifying UI components and pages
- Implementing features requiring design system compliance
- Refactoring frontend code to match standards
- Building forms, layouts, interactive elements
- Ensuring WCAG AAA accessibility compliance
- Reviewing code for design system adherence

**Knowledge**: Complete design system (colors, typography, spacing, shadows), custom UI components (Alert, Input, Button, Badge, Card), Vue 3 best practices, routing patterns, permissions, data fetching, accessibility, responsive design

**When to use**:

- Building new pages or components
- Implementing forms or interactive features
- Fixing styling or UI issues
- Ensuring accessibility compliance
- Reviewing frontend code

**Location**: `.claude/agents/frontend-design-system-dev.md`

### Smart Commit Agent (`/commit`)

**Use for**: Automated git commit creation and management

**Responsibilities**:

- Analyzes all git changes intelligently
- Groups changes logically by scope and type
- Creates multiple focused commits (not one giant commit)
- Follows gitmoji + conventional commits format
- Includes detailed descriptions and Claude footer
- Pushes everything to the feature branch

**When to use**: When you have multiple changes to commit and want intelligent grouping

**Location**: `.claude/commands/commit.md`

---

## Permission System

### Overview

The application implements a granular, resource-based permission system integrated with Keycloak.

### Permission Model

Permissions are derived from Keycloak roles and stored in the database:

- Permissions are checked at **route level** (navigation guards)
- Permissions are checked at **component level** (conditional rendering)
- Backend API endpoints validate permissions

### Available Permissions

**Company Permissions**:

- `company.view` - View company details
- `company.create` - Create new companies
- `company.delete` - Delete companies

**Organization Permissions**:

- `organization.read` - Access team page (read-only)
- `organization.write` - Manage organization users and settings

**Admin Permissions**:

- `admin.organizations` - Admin access to organization management (user creation, org assignment)

### Implementing Permissions

#### Route-Level Protection

```vue
<template>
  <div>Your protected page</div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.organizations
    - organization.write
  requiresAuth: true
  title: 'Page Title'
</route>
```

**Permission Logic**: OR - user needs **any one** of the specified permissions

#### Component-Level Checks

```vue
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'

const authStore = useAuthStore()
const { canCreateCompany, canEditCompany, canDeleteCompany } = useCompanyPermissions()
</script>

<template>
  <div>
    <Button v-if="canCreateCompany" variant="primary" label="Create Company" />
    <Button v-if="canDeleteCompany" variant="secondary" color="danger" label="Delete" />
  </div>
</template>
```

#### Resource-Based Composables

**Company Permissions** (`useCompanyPermissions`):

- `canCreateCompany` - Permission to create companies
- `canEditCompany` - Permission to edit companies
- `canDeleteCompany` - Permission to delete companies
- `canViewCompany` - Permission to view company details
- `canManageCompanies` - Edit OR delete permission
- `hasAnyCompanyAccess` - Any company permission

**Auth Store Methods**:

- `hasPermission(permission: string)` - Check single permission
- `hasRole(role: string)` - Check single role
- `hasAnyRole(roles: string[])` - Check if user has any of the roles (OR logic)
- `hasAllRoles(roles: string[])` - Check if user has all roles (AND logic)

### Test Users

See **Test Users** section below for credentials to test different permission scenarios.

---

## Routing System

### File-Based Routing

Routes are automatically generated from `apps/front/src/pages/` directory structure using `unplugin-vue-router`.

#### Basic Patterns

```
src/pages/
├── index.vue                    → "/"
├── about.vue                    → "/about"
├── users/
│   ├── index.vue               → "/users"
│   ├── [id].vue                → "/users/:id"
│   ├── [id]/
│   │   └── edit.vue            → "/users/:id/edit"
│   └── create.vue              → "/users/create"
└── [...path].vue               → "/*" (catch-all)
```

#### Route Groups

Organize files without affecting URLs using parentheses:

```
src/pages/
├── (admin)/
│   ├── dashboard.vue          → "/dashboard"
│   └── users.vue              → "/users"
├── (public)/
│   ├── login.vue              → "/login"
│   └── register.vue           → "/register"
```

#### Breaking Out of Layouts

Use dot notation to bypass parent layouts:

```
src/pages/
├── users.vue                  → Layout for /users/*
├── users/
│   ├── index.vue             → Uses users.vue layout
│   └── [id].vue              → Uses users.vue layout
└── users.create.vue          → "/users/create" (NO layout)
```

#### Best Practices

- **AVOID** files named `index.vue`, use groups: `(home).vue` instead
- **ALWAYS** use explicit param names: `[userId].vue` not `[id].vue`
- Use `[[paramName]]` for optional parameters
- Use `+` modifier for repeatable params: `[[slug]]+.vue`
- Refer to `typed-router.d.ts` for route names and parameters
- Prefer named route locations: `router.push({ name: '/users/[userId]', params: { userId } })`

---

## API & Data Fetching

### Architecture

```
API Layer (src/api/) → Queries (src/queries/) → Components
                     ↓
                 Mutations (src/mutations/)
```

### API Functions (`src/api/`)

Pure functions that make HTTP calls using `apiClient`:

```typescript
import { apiClient } from './client'
import type { Company } from '@/types/company'

// GET single resource
export const getCompanyById = async (id: string) => {
  return apiClient.get<Company>(`/companies/${id}`)
}

// GET list with filters
export const getCompanies = async (filters: { page: number; size: number }) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })
  return apiClient.get<PaginatedResponse<Company>>(`/companies?${params}`)
}

// POST create
export const createCompany = async (data: CompanyCreate) => {
  return apiClient.post<Company>('/companies', data)
}

// PUT update (full replacement)
export const updateCompany = async (id: string, data: CompanyUpdate) => {
  return apiClient.put<Company>(`/companies/${id}`, data)
}

// PATCH update (partial)
export const patchCompany = async (id: string, data: Partial<CompanyUpdate>) => {
  return apiClient.patch<Company>(`/companies/${id}`, data)
}

// DELETE
export const deleteCompany = async (id: string) => {
  await apiClient.delete(`/companies/${id}`)
}
```

**Key Points**:

- Always return typed responses
- Use URLSearchParams for query parameters
- Keep functions pure and focused
- Use PUT for full replacement, PATCH for partial updates
- Let Pinia Colada handle errors (don't wrap in try/catch)

### Queries (`src/queries/`)

Queries fetch data using `defineQueryOptions` from Pinia Colada:

```typescript
import { defineQueryOptions } from '@pinia/colada'
import { getCompanies, getCompanyById } from '@/api/companies'

// Define query keys for cache management
export const COMPANY_QUERY_KEYS = {
  root: ['companies'] as const,
  byId: (id: string) => [...COMPANY_QUERY_KEYS.root, id] as const,
  withFilters: (filters: { page: number; size: number }) =>
    [...COMPANY_QUERY_KEYS.root, { filters }] as const,
}

// Query for single company
export const companyByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: COMPANY_QUERY_KEYS.byId(id),
  query: () => getCompanyById(id),
}))

// Query for company list with filters
export const companiesQuery = defineQueryOptions(
  ({ filters }: { filters: { page: number; size: number } }) => ({
    key: COMPANY_QUERY_KEYS.withFilters(filters),
    query: () => getCompanies(filters),
  }),
)
```

**Query Key Patterns**:

- Use consistent naming: `RESOURCE_QUERY_KEYS`
- Structure keys hierarchically
- Include parameters in keys for proper caching

### Mutations (`src/mutations/`)

Mutations create, update, or delete data using `defineMutation`:

```typescript
import { ref } from 'vue'
import { defineMutation, useMutation } from '@pinia/colada'
import { createTask } from '@/api/tasks'
import type { TaskCreate } from '@/types/task'

// Mutation with reactive refs
export const useCreateTask = defineMutation(() => {
  const companyId = ref<number | null>(null)
  const taskType = ref<string>('')

  const { mutate, ...mutation } = useMutation({
    mutation: (task: TaskCreate) => createTask(task),
  })

  return {
    ...mutation,
    createTask: () => {
      if (!companyId.value || !taskType.value) {
        throw new Error('Company ID and task type are required')
      }
      return mutate({
        company_id: companyId.value,
        type: taskType.value,
        status: 'pending',
      })
    },
    companyId,
    taskType,
    mutate,
  }
})

// Simple mutation without refs
export const useDeleteCompany = defineMutation(() => {
  const { mutate, ...mutation } = useMutation({
    mutation: (id: string) => deleteCompany(id),
  })

  return {
    ...mutation,
    deleteCompany: mutate,
  }
})
```

### Using in Components

```vue
<script setup lang="ts">
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'

const route = useRoute()

// Query with route params
const {
  data: company,
  isLoading,
  error,
} = useQuery(companyByIdQuery, () => ({ id: route.params.id as string }))

// Query with reactive parameters
const page = ref(1)
const size = ref(10)
const { data: companies } = useQuery(companiesQuery, () => ({
  filters: { page: page.value, size: size.value },
}))
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">Error: {{ error.message }}</div>
  <div v-else>
    <h1>{{ company?.name }}</h1>
    <!-- Use data -->
  </div>
</template>
```

### Best Practices

1. **Type Everything**: Always define types for API responses
2. **Consistent Query Keys**: Use hierarchical key structures
3. **Error Handling**: Let Pinia Colada handle errors
4. **Optimistic Updates**: Use mutation options for better UX
5. **Query Invalidation**: Invalidate queries after mutations
6. **Loading States**: Always handle loading, error, and empty states
7. **No Direct API Calls**: Never call API functions directly in components

---

## Test Users

All test users are defined in `infra/files/realm-chapsmind.json` and added to the **ChapsMind Dev** organization by `init-keycloak.sh`.

| Username | Password | Roles | Description |
|----------|----------|-------|-------------|
| `admin` | `admin123` | admin (composite: all roles) | Full access |
| `company_manager` | `manager123` | company.create, organization.read, organization.write | Company + team management |
| `company_viewer` | `viewer123` | organization.read | Read-only access |
| `no_access` | `noaccess123` | (none) | For testing 403 errors |

---

## Development Workflow

### Standard Workflow

1. Plan your tasks, review with user
2. Write code following [project structure](#project-structure) and [standards](#development-standards)
3. Test implementations:
   - Write tests for logic and components
   - Use Playwright MCP server to test like a real user
4. Stage changes with `git add` once feature works
5. Review changes and analyze need for refactoring
6. Use `/commit` or commit manually with proper format

### Testing with Playwright MCP

1. Navigate to the relevant page
2. Wait for content to load completely
3. Test primary user interactions
4. Test secondary functionality (error states, edge cases)
5. Check JS console for errors/warnings
6. Document and fix any bugs immediately

---

## Project Commands

### Frontend Commands (run from `apps/front/`)

```bash
# Build for production
cd apps/front && yarn build

# Run all tests
cd apps/front && yarn test

# Run specific test files
cd apps/front && yarn vitest run <test-files>

# Check test coverage
cd apps/front && yarn vitest run --coverage

# Lint and fix
cd apps/front && yarn lint

# Type check
cd apps/front && yarn run type-check
```

Or use Taskfile commands from monorepo root:

```bash
task front:build
task front:lint
task front:typecheck
task front:dev
```

### Screen Backend Commands (via Task)

```bash
# Run backend tests
task screen:test

# Lint backend code
task screen:lint

# Format backend code
task screen:format

# Open bash shell in screen container
task screen:shell
```

---

## Research & Documentation

- **NEVER** hallucinate or guess URLs
- **ALWAYS** try accessing `llms.txt` first (e.g., `https://pinia-colada.esm.dev/llms.txt`)
- **ALWAYS** follow existing links in documentation indices
- Verify examples and patterns from documentation before using

---

## Internationalization (i18n)

### Architecture

- **Library**: vue-i18n v11 with Composition API (`legacy: false`)
- **Lazy loading**: Only `en-US` is bundled statically. Other locales are loaded on demand via `loadLocaleMessages()` from `@/i18n`
- **File format**: Nested JSON, one file per language in `src/i18n/locales/`
- **Fallback chain**: default → `en-US`
- **ADR**: See `docs/architecture/adr/0012-frontend-translation-management-strategy.md`

### Key Naming Convention

Format: `module.feature.element` in camelCase

```
common.actions.save          # Shared UI actions
common.validation.required   # Shared validation messages
screen.company.title         # Screen module, company feature
dashboard.recentProjects     # Dashboard feature
settings.team.permissions    # Settings, team management
admin.users.create           # Admin section
```

**Namespaces** (top-level keys): `common`, `target`, `screen`, `dashboard`, `settings`, `admin`

### Usage Rules

```typescript
// CORRECT: key + named params
t('company.create.inFolder', { folder: folderName })

// CORRECT: key only
t('common.actions.save')

// WRONG: never use fallback as 2nd argument
t('key', 'Fallback text')                    // ❌
t('key', 'Fallback', { param: value })       // ❌
```

### Adding a New Language

1. Create `src/i18n/locales/{locale}.json` (copy structure from `en-US.json`)
2. Add datetime formats in `src/i18n/datetime-formats.ts`
3. Add the locale option in `LocaleSwitcher.vue`
4. Update fallback chain in `src/i18n/index.ts` if needed
5. No other changes needed — lazy loading handles the rest

### Adding/Modifying Translation Keys

1. Add the key in **all** locale files (`en-US.json`, `fr-FR.json`, etc.)
2. Follow the `module.feature.element` naming convention
3. Use nested JSON structure (not flat dot-notation)
4. Keep keys alphabetically sorted within each level

---

## Key Reminders

1. Use appropriate specialized agents for frontend work or commits
2. Follow git commit format with gitmojis
3. Always implement permission checks
4. Use custom UI components from `@/components/ui/`
5. Follow design system guidelines (use the frontend agent)
6. Keep code TypeScript-strict
7. Test with appropriate test users
8. Use Pinia Colada for data fetching
9. Never bypass authentication or permissions
10. Follow existing code patterns and conventions

---

## Additional Documentation

- **Frontend Design System**: `.claude/agents/frontend-design-system-dev.md`
- **Commit Agent**: `.claude/commands/commit.md`
- **Component Guidelines**: `apps/front/src/components/CLAUDE.md`
- **Page Routing**: `apps/front/src/pages/CLAUDE.md`



# Claude Code Configuration

## Kubernetes Production Environment

### Accessing Database in Kubernetes
```bash
# Find database pod name
kubectl get pods -n chapsmind | grep postgres

# Connect to database
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "YOUR_SQL_QUERY"

# List tables
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "\\dt"

# Describe table structure
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "\\d TABLE_NAME"
```

### Running Alembic Migrations in Kubernetes
```bash
# Find screen backend pod name
kubectl get pods -n chapsmind | grep screen

# Run migrations
kubectl exec -it <screen-pod-name> -n chapsmind -- alembic upgrade head

# Check current migration version
kubectl exec -it <screen-pod-name> -n chapsmind -- alembic current

# Check migration history
kubectl exec -it <screen-pod-name> -n chapsmind -- alembic history
```

**Important Notes:**
- Always use the `-n chapsmind` namespace flag
- Use double quotes for SQL queries to handle escaping properly
- Screen backend pod name typically starts with `chapsmind-screen-`
- Database pod name typically starts with `postgres-` or similar

## Docker Compose Commands

This project uses Taskfile to wrap Docker Compose commands. Run all commands from the monorepo root.

### Development Environment

```bash
# First-time setup (one-shot)
task init

# Check prerequisites
task doctor

# Start all services
task up

# Stop all services
task down

# Restart all services
task restart

# Tail all logs
task logs

# Tail logs for a specific service
task logs:service -- screen

# Full reset (removes volumes)
docker compose down -v --rmi local
task init
```

### Services Available
- **nginx**: Reverse proxy — single entry point at `http://localhost`
- **frontend**: Vue.js app (internal, behind nginx)
- **global-service**: API gateway (internal, behind nginx at `/api`)
- **screen**: FastAPI backend (internal, never exposed directly)
- **screen_celery_worker**: Background task processor
- **db**: PostgreSQL database
- **rabbitmq**: Message broker (management UI at `http://localhost:15672`)
- **keycloak**: Auth server at `http://localhost:8080` (local dev only)

### Authentication
Uses **local Keycloak** at `http://localhost:8080` in development. Staging/preprod uses a shared Keycloak instance.

## Database Migrations

### Running Migrations
```bash
task migrate
```

### Checking Migration Status
```bash
task migrate:status
```

### Creating Migrations
```bash
task screen:shell
# Then inside the container:
alembic revision -m "description"
```

**Important**: Never run alembic commands locally - the database host is configured as 'db' which only resolves inside Docker network.

## Deployment Rules

### CRITICAL: Never Copy Files Directly to Production Server
- **NEVER** use scp, ssh, or any method to directly copy files to the production server
- **NEVER** create or modify files directly on the production server
- **ALWAYS** commit and push changes, then ask user to deploy via proper deployment process
- This ensures version control integrity and proper deployment procedures

### Proper Deployment Process
1. Make changes locally in development environment
2. Test changes locally
3. Commit changes with descriptive commit message
4. Push to repository
5. Ask user to deploy using their deployment process
6. Verify deployment worked correctly

## Screen Backend Development

### Running Python Scripts
```bash
task screen:shell
# Then inside the container:
python script_name.py
```

### Testing Endpoints
The API is available at `http://localhost/api` (via nginx → global-service → other modules like screen).

### Common Commands
```bash
task logs:service -- screen    # Check logs
task restart                   # Restart all services
task screen:shell              # Enter screen backend shell
task screen:lint               # Lint backend code
task screen:format             # Format backend code
task screen:test               # Run backend tests
```

## Permission System Guidelines

### Available Permissions

#### Organization Permissions (organization-specific)
- **organization.read**: View organization content (basic access)
- **organization.write**: Modify organization content and manage team members

#### Company Permissions (organization-specific)
- **company.view**: View companies in organization
- **company.create**: Search and create companies (search form functionality)
- **company.update**: Update existing companies (future feature)
- **company.delete**: Delete companies from organization

#### Global Admin Permissions
- **admin.organizations**: Global organization administration (user creation, org assignment)

### Permission Implementation Rules

#### When Adding New Features
1. **ALWAYS ask user about permissions** before implementing
2. **Check if existing permission covers the feature**:
   - company.create = search + create companies
   - organization.write = organization modifications + user management
3. **Only create NEW permissions if existing ones don't fit**
4. **User MUST decide** on permission choice before implementation

#### Frontend Implementation
- Use `usePermissions()` composable for permission checks
- Show/hide UI elements based on permissions (v-if="canCreateCompany")
- Display helpful messages for users without permissions

#### Backend Implementation
- Always verify permissions in API endpoints using `verify_*_permission()` functions
- Return 403 Forbidden with clear error messages
- Check permissions BEFORE executing business logic

#### Permission Naming Convention
- Format: `resource.action` (e.g., company.create, organization.write)
- Organization permissions: organization-specific only (organization.read, organization.write)
- Admin permissions: global only (admin.organizations)
- Company permissions: organization-specific only

### Example Permission Checks
```python
# Backend - Always check before action (using fastapi-keycloak)
user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"]))

# Frontend - Show/hide UI elements
<PrimaryButton v-if="canCreateCompany">Create Company</PrimaryButton>
```

## Testing and Deployment Workflow

### Local Testing
- **Application**: Available at `http://localhost` (frontend + API via nginx)
- **API**: Available at `http://localhost/api`
- **API Docs**: Available at `http://localhost/docs` (Swagger UI via global-service)
- **Keycloak**: Local instance at `http://localhost:8080`
- Run all commands from monorepo root using `task`

### Authentication for Testing

Uses **local Keycloak** at `http://localhost:8080` with realm `chapsmind`.
Uses **integration Keycloak** at `https://sso.dwcode.team/auth` with realm `chapsmind`.

Log in with test users defined in `infra/files/realm-chapsmind.json` (see Test Users section above).

#### Using Token in API Calls
```bash
# Example: Get folders
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/folders/

# Example: Create company
curl -X POST http://localhost/api/companies/ \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Company"}'
```

### Testing Process
1. Make code changes locally
2. Test locally using `task up`
3. Check logs: `task logs:service -- screen`
4. Once working, commit and push changes
5. Ask user to deploy to production server

### Deployment Process
1. Make changes locally in development environment
2. Test changes locally with `task up`
3. Commit changes with descriptive commit message using gitmoji
4. Push to repository
5. Ask user to deploy using their deployment process
6. Verify deployment worked correctly

## Git Commit Guidelines

### Gitmoji Usage
**ALWAYS** use gitmoji in commit messages to provide visual context:

Common gitmojis for this project:
- ✨ `:sparkles:` - New features
- 🐛 `:bug:` - Bug fixes
- 🔧 `:wrench:` - Configuration changes
- 📝 `:memo:` - Documentation updates
- 🗃️ `:card_file_box:` - Database changes/migrations
- 🔒 `:lock:` - Security improvements
- ♻️ `:recycle:` - Refactoring code
- 🚀 `:rocket:` - Deployment/performance improvements
- 🔥 `:fire:` - Removing code/files
- 💄 `:lipstick:` - UI/styling updates
- 🧪 `:test_tube:` - Adding tests
- 📦 `:package:` - Dependencies/packages

### Commit Message Format
```
<gitmoji> <type>(<scope>): TAR-xxx <description>

[optional body]

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Examples
```bash
# Feature
✨ feat(front): TAR-42 add company search filters

# Bug fix
🐛 fix(screen): TAR-15 resolve pagination offset error

# Database change
🗃️ feat(screen): TAR-30 add workflow_configs migration

# Security fix
🔒 fix(screen): TAR-55 sanitize user input to prevent XSS

# Documentation
📝 docs: TAR-99 update API endpoint documentation
```

### Git Workflow (Feature Branch)

**Branch Naming Conventions**:
- `feat/TAR-xxx-feature-name` - New features
- `fix/TAR-xxx-bug-name` - Bug fixes
- `refactor/TAR-xxx-refactor-name` - Code refactoring
- `docs/TAR-xxx-doc-name` - Documentation updates
- `chore/TAR-xxx-task-name` - Maintenance tasks

**Workflow**:
1. Create feature branch from main: `git checkout -b feat/TAR-xxx-feature-name`
2. Make changes and commit using gitmoji format
3. Push feature branch: `git push -u origin feat/TAR-xxx-feature-name`
4. Create merge request for code review
5. After approval, merge to main
6. Deploy from main branch

**Critical Rules**:
- **NEVER** commit directly to main branch
- **ALWAYS** work in feature branches
- **ALWAYS** create merge request before merging to main
- **NEVER** use force push to main/master
- **ALWAYS** use `/commit` command for intelligent commit grouping

## Database Schema Guidelines

### User and Organization Reference Architecture
**CRITICAL**: This application does NOT use database tables for users or organization membership.

- **User References**: Users are managed entirely in Keycloak
- **No Users Table**: There is NO `users` table in the database
- **No Organization Membership Table**: Organization membership is managed in Keycloak Organizations
- **Authentication**: User authentication is handled entirely by Keycloak
- **User Data**: User information and organization membership stored in Keycloak, not in application database

### Table Schema Rules
- **folders.owner_id**: `VARCHAR/UUID` field containing Keycloak user ID
- **folders.owner_username**: `VARCHAR` field containing username (denormalized for display)
- **companies.owner_id**: `VARCHAR/UUID` field containing Keycloak user ID
- **companies.owner_username**: `VARCHAR` field containing username (denormalized for display)
- **companies.organization_id**: `VARCHAR/UUID` field containing Keycloak organization ID
- **NO workspace_members table** - organization membership is in Keycloak

### Model Relationships
- **NO foreign key relationships to users table** (because it doesn't exist)
- **NO foreign key relationships to organizations table** (managed in Keycloak)
- **NO SQLAlchemy relationships to User or Organization models** (don't exist in database)
- User IDs and organization IDs are simple string/UUID fields
- User and organization data is fetched from Keycloak when needed

### Migration Rules
- Never create `users` or `organization_members` tables
- Never create foreign keys to users or organizations
- Always use VARCHAR/String/UUID fields for user and organization references
- User and organization data comes from Keycloak, not database
- **i18n** uses ICU MessageFormat via `@messageformat/core` as custom `messageCompiler` (ADR-0012 section 4):
  - Always use: `t(translationKey, { param: value })`
  - Never use: `t(translationKey, 'fallback', { param: value })` (no fallback as 2nd arg)
  - Never use positional plural: `t(key, { count }, count)` → `t(key, { count })`
  - **Pipe syntax is disabled** (`zero | one | many` does NOT work)
  - Plurals: `"{count, plural, =0 {No items} one {# item} other {# items}}"`
  - Select (gender/type/status): `"{gender, select, male {assigné à} female {assignée à} other {assigné(e) à}} {name}"`
  - Nested select+plural: `"{type, select, company {{count, plural, one {# entreprise} other {# entreprises}}} other {{count, plural, one {# élément} other {# éléments}}}}"`
  - `#` is shorthand for the selector variable value; other variables (`{total}`, `{name}`) stay as-is
  - `other` branch is mandatory in both `plural` and `select`
  - French CLDR: 0 falls under `one`; use `=0` for distinct zero messages ("Aucun...")
- vuellar is our own private component lib dont make research on it you wont find anything

---

## Jira - Modules and Components

| Prefix | Description | Jira Component |
|--------|-------------|----------------|
| `[LEGACY]` | AMI v9 / Target v9 - Maintenance (branch ST9_3) | `Target Legacy` |
| `[GLOBAL]` | Infrastructure, auth, cross-cutting features | `Global` |
| `[TARGET]` | Competitive intelligence and strategic monitoring | `Target` |
| `[SCREEN]` | Automated company cards | `Screen` |
| `[STREAM]` | Multi-channel distribution (newsletters, API, Slack, Teams) | `Stream` |
| `[EXPLORE]` | Data exploration as a graph | `Explore` |

### Jira Rules

- **Component**: automatically inferred from the module prefix. Always verify the component name against the actual project list before using it
- **Story = 1 single module**. If multi-module detected -> create multiple linked Stories
- **Epic**: can be multi-module (combined prefixes `[TARGET][SCREEN]`)
- **Bug**: module = where the bug is DETECTED (not where it is caused)
- **PO validation required** before any Jira creation (wait for "yes"/"ok"/"go"/"validated")
- **Never estimate in story points** (that is the team's responsibility)
- **Never modify Jira statuses**

### Jira API (MCP Atlassian)

- **Descriptions**: always use **markdown** format (`contentFormat: "markdown"`), never wiki markup
- **Priority**: use ID strings (`"10007"`), never names (`"Medium"`)
- **Components**: verify exact names via `getVisibleJiraProjects` or the table above before setting them

### Skill Labels

| Label | Usage |
|-------|-------|
| `Back` | API, services, database, infrastructure |
| `Front` | UI, components, UX |
| `Prompt` | AI, LLM, Dify workflows |

Combinations: `Back + Prompt`, `Front + Prompt`, `Back + Front` (rare)

### Epic Phases

```
Phase 0: Design (Figma mockups, UI specs)
Phase 1: Foundation (architecture, base components)
Phase 2: Back (services, API, integrations)
Phase 3: Front (interface, final UX)
```

### Terminology

```
Dossier de veille -> WatchFile
Acteur -> Actor
Source -> Source
```

### MCP Tools

- **Jira**: Cloud ID `60cc5e3d-8230-41aa-9611-5c348537a1ea`, project `TAR`
