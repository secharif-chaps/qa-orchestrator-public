# Claude AI Assistant Instructions

## 📁 Project Structure

```
mint-front/
├── .claude/
│   ├── agents/                    # Specialized AI agents
│   │   └── frontend-design-system-dev.md
│   └── commands/                  # Custom slash commands
│       └── commit.md
├── src/
│   ├── api/                       # API functions (fetch wrappers)
│   ├── components/
│   │   ├── ui/                   # Base UI components (Alert, Input, Button, Badge, Card)
│   │   ├── layout/               # Layout components
│   │   └── features/             # Feature-specific components
│   ├── composables/              # Composition functions
│   ├── stores/                   # Pinia stores (global state)
│   ├── queries/                  # Pinia Colada queries (data fetching)
│   ├── pages/                    # Page components (file-based routing)
│   ├── plugins/                  # Vue plugins
│   ├── utils/                    # Utility functions
│   ├── assets/                   # Static assets (CSS, images)
│   ├── main.ts                   # App entry point
│   └── App.vue                   # Root component
├── public/                        # Public static files
└── CLAUDE.md                      # This file
```

---

# MINT Frontend Application

A modern Vue 3 application with TypeScript, Tailwind CSS v4, and comprehensive tooling for monitoring companies online.

## Project Overview

### User Application Workflow

🗂️ **Workspaces**

- Users create company cards to monitor companies' information online
- A user always belongs to a workspace
- Company cards are shared within a workspace

🔄 **Company Lifecycle & Tasks**

- Creating a company card triggers tasks that find specific information online via n8n workflows
- Frontend monitors task status and calls the backend to start tasks

**Key Components**: User, Company, Workspace, Tasks

**User Experience**:

1. Dashboard: See stats and recent companies
2. Companies List View: Browse all companies
3. Company View: Detailed information about a company

---

## 🛠️ Tech Stack

- **Framework**: Vue 3 with Composition API + `<script setup lang="ts">`
- **Language**: TypeScript
- **Styling**: Tailwind CSS v4
- **UI Components**: Custom design system + Reka UI
- **State Management**: Pinia (global state)
- **Data Fetching**: Pinia Colada (queries & mutations)
- **Routing**: Vue Router + unplugin-vue-router (file-based)
- **Authentication**: Keycloak with role-based permissions
- **Architecture**: Workspace-based multi-tenancy

---

## 📋 Development Standards

### Core Principles

1. **ALWAYS** use Composition API with `<script setup lang="ts">`, **NEVER** Options API
2. **ALWAYS** use TypeScript, prefer `interface` over `type`
3. **ALWAYS** use Tailwind CSS classes, avoid manual CSS
4. **DO NOT** hard-code colors, use semantic color tokens (see Color System below)
5. **ALWAYS** use named functions for methods, arrow functions only for callbacks
6. **ALWAYS** prefer named exports over default exports
7. Add meaningful comments explaining **why**, not **what**

### Component Architecture

**CRITICAL**: Break down complex pages into focused components. Pages orchestrate, components render.

**Component Decomposition Rules**:

1. **Pages should stay focused** (aim for < 200 lines, but complex logic may require more)
2. **ALWAYS check for existing components** in `src/components/ui/` before creating new ones
3. **Extract into components**:
   - Custom dropdowns → Styled `Dropdown.vue` with slots (NOT headless)
   - Data tables → `Table.vue` + `TableHeader.vue` + `TableRow.vue` + `TableEmpty.vue`
   - Forms with >3 fields
   - Any repeated UI patterns
4. **Create generic UI components** for reusable patterns:
   - Put in `src/components/ui/`
   - Document in `src/components/CLAUDE.md`
   - Examples: `Dropdown.vue`, `Table.vue`, `Modal.vue`, `Tabs.vue`
5. **Component hierarchy** should be 2-3 levels deep max

**Example structure** (User Management):
```
pages/admin/users.vue (data + layout)
├── components/admin/UserFilters.vue (search + dropdowns)
│   └── ui/Dropdown.vue (generic styled dropdown)
├── components/admin/UserTable.vue (table wrapper)
│   ├── components/admin/UserTableHeader.vue
│   ├── components/admin/UserTableRow.vue
│   └── components/admin/UserTableEmpty.vue
└── components/admin/UserWorkspaceModal.vue
```

See `src/components/CLAUDE.md` for detailed component best practices.

### 🎨 Semantic Color System

The MINT design system uses DaisyUI-inspired semantic color tokens for consistent, accessible theming.

#### Philosophy

**Never use palette colors directly** (~~`bg-green-500`~~, ~~`text-red-600`~~). Always use semantic tokens that automatically adapt to light/dark themes and ensure WCAG accessibility.

#### Semantic Color Tokens

| Token       | Purpose                  | Example Use Case                |
| ----------- | ------------------------ | ------------------------------- |
| `primary`   | Main brand actions       | Primary buttons, active states  |
| `secondary` | Secondary brand elements | Secondary buttons, badges       |
| `accent`    | Emphasis and highlights  | Special badges, callouts        |
| `success`   | Positive feedback        | Success messages, confirmations |
| `warning`   | Caution states           | Warnings, pending states        |
| `error`     | Negative feedback        | Error messages, validation      |
| `info`      | Informational            | Info banners, help text         |

#### Token Variants

Each semantic color has **4 variants**:

1. **Solid** (`{color}`): For solid backgrounds
2. **Solid Content** (`{color}-content`): Text/icons on solid backgrounds
3. **Light** (`{color}-light`): For light/subtle backgrounds
4. **Light Content** (`{color}-light-content`): Text/icons on light backgrounds
5. **Stroke** (`{color}-stroke`): For borders

#### Usage Patterns

**Solid Button**

```vue
<button class="bg-primary text-sage-content-content">
  Primary Action
</button>
```

**Light Alert**

```vue
<div
  class="bg-success-light text-success-light-content border border-success-stroke rounded-lg p-4"
>
  <Icon class="text-success-light-content" />
  Operation successful!
</div>
```

**Badge (Light)**

```vue
<span class="bg-info-light text-info-light-content border border-info-stroke px-2 py-1 rounded">
  New
</span>
```

**Card with Border**

```vue
<div class="bg-base-200 border border-primary-stroke rounded-card p-6">
  Card content
</div>
```

#### Background Layering

Use `base` colors for application hierarchy:

```vue
<body class="bg-base-100">              <!-- Page background -->
  <div class="bg-base-200">             <!-- Card/modal background -->
    <div class="bg-base-300">           <!-- Nested card -->
    </div>
  </div>
</body>
```

#### Complete Examples

**Success Toast**

```vue
<div
  class="bg-success-light text-success-light-content border border-success-stroke
            rounded-lg p-4 shadow-green flex items-center gap-3"
>
  <IconCircleCheck class="text-success-light-content" />
  <span>Changes saved successfully</span>
</div>
```

**Error Alert**

```vue
<div
  class="bg-error-light text-error-light-content border border-error-stroke
            rounded-lg p-4 shadow-red"
>
  <IconAlertCircle class="text-error-light-content" />
  <p class="font-semibold">Error</p>
  <p>Something went wrong</p>
</div>
```

**Warning Banner**

```vue
<div class="bg-warning-light text-warning-light-content border-l-4 border-warning-stroke p-4">
  <IconAlertTriangle class="text-warning-light-content" />
  <p>This action cannot be undone</p>
</div>
```

**Button Group**

```vue
<div class="flex gap-2">
  <button class="bg-primary text-sage-content-content px-4 py-2 rounded-lg">
    Save
  </button>
  <button class="bg-error text-error-content px-4 py-2 rounded-lg">
    Delete
  </button>
</div>
```

#### Accessibility Rules

✅ **DO**:

- Always pair backgrounds with their matching `-content` color
- Use `-light` variants for non-critical/informational UI
- Use solid variants for primary actions and critical states
- Use `-stroke` for borders to maintain visual hierarchy

❌ **DON'T**:

- Mix mismatched pairs: ~~`bg-success text-error-content`~~
- Use palette colors: ~~`bg-green-100`~~, ~~`text-red-700`~~
- Use raw colors: ~~`bg-[#29ad72]`~~
- Ignore content pairing: ~~`bg-primary text-black`~~ (use `text-sage-content-content`)

### File Organization

- Keep types alongside code
- Keep tests alongside files: `Button.vue` + `Button.spec.ts`
- Use consistent PascalCase for component files
- Use kebab-case for other files

### Dev Environment

- Dev server runs on `http://localhost:3000` with HMR
- **NEVER** launch the dev server yourself (it's already running)

---

## 🎯 Specialized Agents

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
- Pushes everything to `origin/main`

**When to use**: When you have multiple changes to commit and want intelligent grouping

**Location**: `.claude/commands/commit.md`

---

## 🔐 Permission System

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

**Workspace Permissions**:

- `workspace.read` - Access team page (read-only)
- `workspace.write` - Manage workspace users and settings

**Admin Permissions**:

- `admin.workspaces` - Admin access to workspace management

### Implementing Permissions

#### Route-Level Protection

```vue
<template>
  <div>Your protected page</div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
    - workspace.write
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

## 🗺️ Routing System

### File-Based Routing

Routes are automatically generated from `src/pages/` directory structure using `unplugin-vue-router`.

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

## 🔌 API & Data Fetching

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

## 👥 Test Users

All test users are linked to **Workspace ID 1** (ChapsVision workspace).

### 1. Admin User (Full Access)

```
Username: admin
Password: admin123
Email: admin@test.com
```

**Permissions**: All permissions

**Expected Behavior**:

- ✅ Sees all sidebar links (home, search, companies, team, workspaces)
- ✅ Can create, edit, and delete companies
- ✅ Can manage team members
- ✅ Has access to all routes

### 2. Company Manager

```
Username: company_manager
Password: manager123
Email: manager@test.com
```

**Permissions**: `company.view`, `company.create`, `company.delete`

**Expected Behavior**:

- ✅ Can manage companies (create, edit, delete)
- ❌ Cannot see team link
- ❌ Cannot access workspace admin

### 3. Company Viewer

```
Username: company_viewer
Password: viewer123
Email: viewer@test.com
```

**Permissions**: `company.view`

**Expected Behavior**:

- ✅ Can view companies (read-only)
- ❌ Cannot create/edit/delete companies
- ❌ Cannot access team page
- ✅ Sees "Read-only access" messages

### 4. Team Viewer

```
Username: team_viewer
Password: teamviewer123
Email: teamviewer@test.com
```

**Permissions**: `workspace.read`, `company.view`

**Expected Behavior**:

- ✅ Can access team page (read-only)
- ✅ Can view companies
- ❌ Cannot add/edit/disable users
- ❌ Cannot create/edit/delete companies

### 5. Team Manager

```
Username: team_manager
Password: teammanager123
Email: teammanager@test.com
```

**Permissions**: `workspace.read`, `workspace.write`, `company.view`

**Expected Behavior**:

- ✅ Can manage team members (add, edit, disable)
- ✅ Can view companies
- ❌ Cannot create/edit/delete companies

### 6. No Access User

```
Username: no_access
Password: noaccess123
Email: noaccess@test.com
```

**Permissions**: None

**Expected Behavior**:

- ✅ Can only see home link
- ❌ Gets 403 on most routes
- ❌ Sees permission denied messages

### Creating Test Users

Run from the mint-new repository root:

```bash
./create_test_users.sh
```

---

## 🔍 Development Workflow

### Standard Workflow

1. Create feature branch from main (e.g., `feat/feature-name`)
2. Plan your tasks, review with user
3. Write code following [project structure](#project-structure) and [standards](#development-standards)
4. Test implementations:
   - Write tests for logic and components
   - Use Playwright MCP server to test like a real user
5. Stage changes with `git add` once feature works
6. Review changes and analyze need for refactoring
7. Use `/commit` or commit manually with proper format
8. Push feature branch and create merge/pull request

### Git Workflow (Feature Branch)

**Branch Naming Conventions**:
- `feat/feature-name` - New features
- `fix/bug-name` - Bug fixes
- `refactor/refactor-name` - Code refactoring
- `docs/doc-name` - Documentation updates
- `chore/task-name` - Maintenance tasks

**Critical Rules**:
- **NEVER** commit directly to main branch
- **ALWAYS** work in feature branches
- **ALWAYS** create merge/pull request before merging to main
- **ALWAYS** use gitmoji + conventional commits format
- Use `/commit` command for intelligent commit grouping

### Testing with Playwright MCP

1. Navigate to the relevant page
2. Wait for content to load completely
3. Test primary user interactions
4. Test secondary functionality (error states, edge cases)
5. Check JS console for errors/warnings
6. Document and fix any bugs immediately

---

## 📚 Project Commands

### Frequently Used

```bash
# Build for production
pnpm run build

# Run all tests
pnpm run test

# Run specific test files
pnpm exec vitest run <test-files>

# Check test coverage
pnpm exec vitest run --coverage
```

---

## 🔗 Research & Documentation

- **NEVER** hallucinate or guess URLs
- **ALWAYS** try accessing `llms.txt` first (e.g., `https://pinia-colada.esm.dev/llms.txt`)
- **ALWAYS** follow existing links in documentation indices
- Verify examples and patterns from documentation before using

---

## 🎯 Key Reminders

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

## 📖 Additional Documentation

- **Frontend Design System**: `.claude/agents/frontend-design-system-dev.md`
- **Commit Agent**: `.claude/commands/commit.md`
- **Component Guidelines**: `src/components/CLAUDE.md`
- **Page Routing**: `src/pages/CLAUDE.md`
