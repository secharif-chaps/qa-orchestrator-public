# Vue Component Architecture Guide

## TL;DR

- **Pages focused & readable**: Aim for < 200 lines (guideline, not strict rule)
- **Check existing components first**: Look in `src/components/ui/` before creating
- **Create styled generic components**: Dropdowns (with slots), tables (header/row/empty), modals
- **Document selectively**: Update `src/components/CLAUDE.md` for design system components

---

## The Problem We're Solving

**Bad**: 570-line monolithic page files with inline dropdowns, tables, and complex UI

**Good**: Small orchestrator pages (< 150 lines) that delegate to focused components

---

## Component Responsibilities

### Pages (`src/pages/*.vue`)

**What pages should do**:
- Data fetching (queries, mutations)
- Page-level state management
- Layout structure
- Component orchestration

**What pages should NOT do**:
- Render complex UI inline
- Implement dropdown/table/modal logic directly
- Become unreadably long (aim for < 200 lines, but complex logic may require more)

### Components

**Generic components** (`src/components/ui/`):
- Reusable across features
- Well-documented with examples
- Examples: `Button.vue`, `Input.vue`, `Dropdown.vue`, `Modal.vue`, `Table.vue`

**Feature components** (`src/components/[feature]/`):
- Feature-specific logic
- Compose generic components
- Examples: `UserTable.vue`, `UserWorkspaceModal.vue`

---

## Decision Tree

```
Building a new feature?
│
├─ Check: Does this UI pattern already exist?
│  ├─ Check src/components/ui/ → YES → Reuse it
│  └─ NO → Continue
│
├─ Check: Is the page getting too long (> 200 lines)?
│  ├─ YES → Plan component extraction now
│  └─ NO but complex UI → Still extract for clarity
│
├─ Check: Is this UI pattern reusable across features?
│  ├─ YES → Create styled generic component with slots
│  │   ├─ Put in src/components/ui/
│  │   ├─ Provide default styling + customization
│  │   ├─ Document in src/components/CLAUDE.md
│  │   └─ Example: Dropdown.vue, Table.vue
│  │
│  └─ NO → Create feature-specific component
│      ├─ Put in src/components/[feature]/
│      └─ Break into sub-components (Table → Header + Row + Empty)
│
└─ Build the feature with well-structured components
```

---

## Real Example: User Management Page

### ❌ Bad (Monolithic - 570 lines)

```vue
<!-- pages/admin/users.vue -->
<template>
  <div>
    <!-- 50 lines of search/filter UI -->
    <!-- 30 lines of dropdown logic -->
    <!-- 200 lines of table markup -->
    <!-- 100 lines of modal -->
    <!-- 190 lines of script logic -->
  </div>
</template>
```

### ✅ Good (Component-based - 120 lines)

```vue
<!-- pages/admin/users.vue -->
<template>
  <div>
    <UserFilters
      v-model:search="search"
      v-model:workspace-filter="workspaceFilter"
      :workspaces="workspaces"
    />

    <UserTable
      :users="users"
      @assign-workspace="handleAssign"
    />

    <UserWorkspaceModal
      v-if="showModal"
      :user="selectedUser"
      :workspaces="workspaces"
      @confirm="handleConfirm"
      @cancel="closeModal"
    />
  </div>
</template>

<script setup lang="ts">
// Only queries, state, event handlers (< 100 lines)
const { data: users } = useQuery(adminUsersQuery, ...)
const { data: workspaces } = useQuery(workspacesQuery, ...)
// ...
</script>
```

**Component breakdown**:
- `pages/admin/users.vue` (120 lines) - Data + orchestration
- `components/admin/UserFilters.vue` (80 lines) - Search + dropdowns
- `components/admin/UserTable.vue` (80 lines) - Table wrapper
- `components/admin/UserTableHeader.vue` (40 lines) - Table header
- `components/admin/UserTableRow.vue` (60 lines) - Single row
- `components/admin/UserTableEmpty.vue` (40 lines) - Empty state
- `components/admin/UserWorkspaceModal.vue` (150 lines) - Modal content
- `components/ui/Dropdown.vue` (140 lines) - Generic styled dropdown with slots

**Total**: Same functionality, but:
- ✅ Each file is focused and readable
- ✅ Components are testable independently
- ✅ Dropdown is reusable across the app
- ✅ Easier to maintain and refactor

---

## Generic UI Components Checklist

When creating a new generic component:

**Implementation**:
- [ ] Put it in `src/components/ui/`
- [ ] Create styled component with slots (NOT headless)
- [ ] Use semantic color tokens (not raw colors)
- [ ] Support light/dark themes
- [ ] Add TypeScript types for props/emits
- [ ] Include prop documentation (JSDoc comments)
- [ ] Test accessibility (keyboard navigation, ARIA)
- [ ] Handle common logic (open/close, positioning, etc.)

**Documentation** (selective):
- [ ] Add to `src/components/CLAUDE.md` with:
  - [ ] Usage examples
  - [ ] Props documentation
  - [ ] Common patterns
- [ ] Reference in `.claude/agents/frontend-design-system-dev.md` if it's a major design system component

---

## Common Patterns to Extract

| Pattern | Component Name | Location | Notes |
|---------|---------------|----------|-------|
| Custom select/filter dropdown | `Dropdown.vue` + `DropdownItem.vue` | `ui/` | Styled with slots, NOT headless |
| Data table structure | `Table.vue` | `ui/` or Feature | Generic wrapper |
| Data table header | `TableHeader.vue` | Feature-specific | Column definitions |
| Data table row | `TableRow.vue` | Feature-specific | Row rendering |
| Data table empty state | `TableEmpty.vue` | Feature-specific | No data message |
| Search + filters bar | `Filters.vue` | Feature-specific | Composes `Input` + `Dropdown` |
| Empty state | `EmptyState.vue` | `ui/` | Generic with slots |
| Loading skeleton | `Skeleton.vue` | `ui/` | Generic variants |
| Confirmation modal | `ConfirmModal.vue` | `ui/` | Generic for confirms |
| Form with validation | `FormName.vue` | Feature-specific | Composes `Input` |

---

## Anti-Patterns to Avoid

❌ **Don't**:
- Create unreadably long page files (aim for < 200 lines)
- Implement dropdown/table logic inline in pages
- Copy-paste similar UI patterns (extract instead)
- Create overly granular components (`TableCellText.vue` is too much)
- Create headless components when styled ones would work better
- Forget to document new design system components

✅ **Do**:
- Plan component extraction before coding
- Check for existing components first (`src/components/ui/`)
- Create styled generic components with slots
- Break tables into header/row/empty components
- Keep pages focused and readable
- Document design system components in `src/components/CLAUDE.md`

---

## Quick Commands

### Check for existing components
```bash
# List all UI components
ls src/components/ui/

# Search for component usage
rg "import.*from.*components/ui" src/
```

### Create new component
```bash
# Generic UI component
touch src/components/ui/Dropdown.vue

# Feature component
touch src/components/admin/UserTable.vue
```

---

## Questions?

Refer to:
- `src/components/CLAUDE.md` - Detailed component best practices
- `CLAUDE.md` - Project-wide standards
- `.claude/agents/frontend-design-system-dev.md` - Design system agent guide
