# Component Architecture Improvements - 2025-10-24

## Summary of Changes

Updated AI documentation across the project to enforce better component decomposition practices.

### Files Updated

1. **`src/components/CLAUDE.md`**
   - Added "Component Decomposition Philosophy" section at the top
   - Defined page vs component responsibilities
   - Added component granularity rules with examples
   - Provided real component hierarchy example

2. **`CLAUDE.md`** (main project docs)
   - Added "Component Architecture" section under "Development Standards"
   - Created quick-reference checklist for component extraction
   - Added example structure for proper component decomposition
   - Linked to detailed docs in `src/components/CLAUDE.md`

3. **`.claude/agents/frontend-design-system-dev.md`**
   - Added "Component Architecture (CRITICAL)" as section 0
   - Included workflow questions to ask before coding
   - Added extraction rules and component hierarchy example
   - Defined when to create generic UI components
   - Added documentation requirements for new components

4. **`.agent-os/component-architecture-guide.md`** (NEW)
   - Created comprehensive quick-reference guide
   - Includes decision tree for component creation
   - Real before/after examples
   - Generic UI components checklist
   - Common patterns to extract table
   - Anti-patterns to avoid

## Key Principles Now Documented

### 1. Page Size Limit
- **Pages must be < 150 lines**
- If longer, extract components immediately
- Pages orchestrate, components render

### 2. Check-First Policy
- **Always check `src/components/ui/` first** before creating new components
- Reuse existing generic components
- Don't recreate existing patterns

### 3. Generic vs Specific
- **Generic components** (`src/components/ui/`):
  - Dropdowns, modals, tabs, tables, tooltips
  - Reusable across features
  - Must be documented immediately

- **Feature components** (`src/components/[feature]/`):
  - Feature-specific logic
  - Compose generic components
  - Single responsibility

### 4. Component Hierarchy
- Maximum 2-3 levels deep
- Example structure:
  ```
  pages/admin/users.vue (data + layout)
  ├── components/admin/UserFilters.vue
  │   └── components/ui/Dropdown.vue (generic)
  ├── components/admin/UserTable.vue
  │   └── components/admin/UserTableRow.vue
  └── components/admin/UserWorkspaceModal.vue
  ```

### 5. Documentation Requirements
When creating a generic component:
1. Put in `src/components/ui/`
2. Document in `src/components/CLAUDE.md` with examples
3. Update `.claude/agents/frontend-design-system-dev.md`
4. Reference in main `CLAUDE.md` if major pattern

## Impact on Future Development

### Before (Bad Practice)
```vue
<!-- 570-line monolithic page -->
<template>
  <!-- Inline dropdown logic -->
  <!-- Inline table markup -->
  <!-- Inline modal -->
</template>
```

### After (Good Practice)
```vue
<!-- 120-line orchestrator page -->
<template>
  <UserFilters v-model:search="search" />
  <UserTable :users="users" />
  <UserWorkspaceModal v-if="showModal" />
</template>
```

## Next Steps

For future features:
1. **Planning phase**: Identify component breakdown before coding
2. **Check phase**: Look for existing components to reuse
3. **Build phase**: Create generic components when needed
4. **Document phase**: Update docs immediately after creating generic components
5. **Review phase**: Ensure pages stay under 150 lines

## Example Improvements Needed

The recent `pages/admin/users.vue` (570 lines) should be refactored to:
- Extract `UserFilters.vue` (search + dropdowns)
- Extract `UserTable.vue` + `UserTableRow.vue`
- Create generic `Dropdown.vue` component
- Reduce page to < 150 lines

This refactoring would:
- ✅ Improve readability
- ✅ Enable component reuse
- ✅ Simplify testing
- ✅ Make maintenance easier
