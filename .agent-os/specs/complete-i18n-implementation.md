# Complete i18n Implementation Specification

## 1. Overview

### Feature Description
Complete the internationalization (i18n) implementation across the entire MINT frontend application by validating and fixing all remaining Vue files that contain hardcoded text strings. This ensures full bilingual support (English/French) throughout the application.

### Goals
- Achieve 100% i18n coverage across all user-facing text
- Maintain consistency with existing translation patterns
- Ensure accessibility and proper UX in both English and French
- Enable easy addition of future languages

### Current Status
**Completed:**
- ✅ `src/pages/folders/` - All folder-related pages (list, create, edit, detail, company creation, CSV upload)
- ✅ `src/pages/companies/` - Company list and detail pages
- ✅ `src/pages/auth/` - Authentication callback page
- ✅ `src/pages/admin/` - Admin dashboard and costs pages

**Remaining:**
- ❌ `src/pages/settings/` - 3 pages (profile, security, appearance)
- ❌ `src/pages/team/` - 3 pages (users, settings, apis)
- ❌ Root-level pages - 9 files ((home).vue, help.vue, team.vue, settings.vue, login.vue, admin.workflows.vue, 403.vue, ui-demo.vue, [...path].vue)
- ❌ `src/components/` - 108 component files across 16 directories

### Success Criteria
- Zero hardcoded strings in all Vue files (template and script sections)
- All text uses `$t()` or `t()` with proper fallback values
- All translation keys exist in both `en-US.ts` and `fr-FR.ts`
- Language switcher in dev mode works correctly across all pages
- No console errors related to missing translation keys

---

## 2. Technical Architecture

### i18n Setup (Already Configured)
- **Library**: `vue-i18n` (Composition API mode, legacy: false)
- **Locale files**:
  - `src/i18n/locales/en-US.ts` (English)
  - `src/i18n/locales/fr-FR.ts` (French)
- **Translation syntax**: `$t('key', 'fallback')` in templates, `t('key', 'fallback')` in scripts
- **Current locale structure**: Hierarchical organization by domain (company, folder, csv, common, etc.)

### Translation Key Naming Convention
```typescript
// Pattern: {domain}.{subdomain}.{key}
$t('settings.profile.title', 'Profile Settings')
$t('team.users.table.name', 'Name')
$t('common.actions.save', 'Save')
```

### Implementation Patterns

#### Template Usage
```vue
<template>
  <!-- Simple text -->
  <h1>{{ $t('page.title', 'Page Title') }}</h1>

  <!-- Button labels -->
  <Button :label="$t('actions.save', 'Save')" />

  <!-- Alert messages -->
  <Alert
    :title="$t('error.title', 'Error')"
    :message="$t('error.description', 'An error occurred')"
  />

  <!-- Interpolation -->
  <p>{{ $t('greeting', 'Hello {name}', { name: user.name }) }}</p>
</template>
```

#### Script Usage
```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// In computed properties
const errorMessage = computed(() => {
  return t('validation.required', 'This field is required')
})

// In functions
const showNotification = () => {
  toast.success(t('success.saved', 'Changes saved successfully'))
}
</script>
```

---

## 3. Scope Breakdown

### Phase 1: Root-Level Pages (9 files)
**Priority**: High
**Estimated Effort**: 2-3 hours

#### Files to Process:
1. `(home).vue` - Dashboard/home page
2. `help.vue` - Help documentation page
3. `team.vue` - Team page layout wrapper
4. `settings.vue` - Settings page layout wrapper
5. `login.vue` - Login page
6. `admin.workflows.vue` - Admin workflows page
7. `403.vue` - Forbidden error page
8. `ui-demo.vue` - UI components demo (likely dev-only)
9. `[...path].vue` - Catch-all 404 page

**Expected Issues**:
- Error page messages (403, 404)
- Login form labels and validation
- Navigation breadcrumbs
- Page titles and descriptions

---

### Phase 2: Settings Pages (3 files)
**Priority**: High
**Estimated Effort**: 2-3 hours

#### Files to Process:
1. `settings/profile.vue` - User profile settings
2. `settings/security.vue` - Security and authentication settings
3. `settings/appearance.vue` - UI theme and appearance preferences

**Expected Issues**:
- Form field labels (name, email, password, etc.)
- Setting descriptions and help text
- Validation error messages
- Success/confirmation messages
- Section headers and subheaders

**Translation Key Structure**:
```typescript
settings: {
  profile: {
    title: 'Profile Settings',
    fields: {
      name: 'Full Name',
      email: 'Email Address',
      // ...
    },
    actions: {
      save: 'Save Changes',
      cancel: 'Cancel',
    },
  },
  security: {
    title: 'Security Settings',
    // ...
  },
  appearance: {
    title: 'Appearance',
    // ...
  },
}
```

---

### Phase 3: Team Pages (3 files)
**Priority**: High
**Estimated Effort**: 2-3 hours

#### Files to Process:
1. `team/users.vue` - Team members management
2. `team/settings.vue` - Team/workspace settings
3. `team/apis.vue` - API keys and integrations management

**Expected Issues**:
- User table headers (Name, Email, Role, Status, Actions)
- Role labels (Admin, Manager, Viewer, etc.)
- Permission descriptions
- API key management labels
- Invitation flow text
- Empty states

**Translation Key Structure**:
```typescript
team: {
  users: {
    title: 'Team Members',
    table: {
      name: 'Name',
      email: 'Email',
      role: 'Role',
      status: 'Status',
      actions: 'Actions',
    },
    actions: {
      invite: 'Invite Member',
      remove: 'Remove',
    },
    // ...
  },
  apis: {
    title: 'API Keys',
    // ...
  },
}
```

---

### Phase 4: Components (108 files across 16 directories)
**Priority**: Medium-High
**Estimated Effort**: 8-12 hours

#### Component Directories (Priority Order):

**High Priority (Core User-Facing Components):**
1. `global/` - Global layout components (appbar, navigation)
2. `ui/` - Base UI components (Alert, Button, Badge, Card, Input, etc.)
3. `sidebar/` - Sidebar navigation components
4. `dashboard/` - Dashboard widgets and cards
5. `home/` - Home page components

**Medium Priority (Feature-Specific Components):**
6. `settings/` - Settings page components
7. `team/` - Team management components
8. `workspace/` - Workspace-related components
9. `user/` - User profile components
10. `tokens/` - Token management components

**Lower Priority (Already Covered or Domain-Specific):**
11. `folders/` - Folder components (likely already i18n from pages work)
12. `companies/` - Company components (likely already i18n from pages work)
13. `company/` - Company detail components (likely already i18n)
14. `admin/` - Admin-specific components (likely already i18n)
15. `chapse/` - Chapse AI assistant components
16. `helpers/` - Helper utilities (likely no UI text)

**Expected Issues per Component Type:**
- **UI Components**: Button labels, placeholder text, validation messages
- **Table Components**: Column headers, empty states, pagination text
- **Modal Components**: Titles, descriptions, action buttons
- **Form Components**: Field labels, help text, error messages
- **Card Components**: Headers, descriptions, action labels

---

## 4. Implementation Strategy

### Workflow for Each File

#### Step 1: Validation with vue-i18n-validator Agent
```bash
# For each directory/file
Use vue-i18n-validator agent to audit the file
Agent will identify:
- Hardcoded strings (text without $t())
- Missing translation keys
- Inconsistent patterns
```

#### Step 2: Add Missing Translation Keys
```typescript
// Add to src/i18n/locales/en-US.ts
export default {
  // ... existing keys
  newDomain: {
    title: 'English Title',
    description: 'English description',
  },
}

// Mirror in src/i18n/locales/fr-FR.ts
export default {
  // ... existing keys
  newDomain: {
    title: 'Titre Français',
    description: 'Description française',
  },
}
```

#### Step 3: Fix Hardcoded Strings
```vue
<!-- Before -->
<h1>User Settings</h1>
<Button label="Save Changes" />

<!-- After -->
<h1>{{ $t('settings.title', 'User Settings') }}</h1>
<Button :label="$t('settings.actions.save', 'Save Changes')" />
```

#### Step 4: Test with Language Switcher
```typescript
// Use dev mode language toggle to verify:
// 1. All text appears in both languages
// 2. No missing key warnings in console
// 3. Proper formatting with interpolation
```

#### Step 5: Commit Progress
```bash
# Commit after each logical group (e.g., one directory)
git add -A
git commit -m "🌐 i18n: add translations for [component/page group]"
```

---

## 5. Task Breakdown

### Task 1: Setup and Preparation
**Estimate**: 30 minutes
- [ ] Review current translation key structure
- [ ] Document naming conventions for new domains
- [ ] Prepare template for new translation sections
- [ ] Test vue-i18n-validator agent functionality

### Task 2: Root-Level Pages i18n
**Estimate**: 2-3 hours
- [ ] Validate and fix `(home).vue`
- [ ] Validate and fix `help.vue`
- [ ] Validate and fix `login.vue`
- [ ] Validate and fix error pages (`403.vue`, `[...path].vue`)
- [ ] Validate and fix layout wrappers (`team.vue`, `settings.vue`)
- [ ] Validate and fix `admin.workflows.vue`
- [ ] Skip or minimal work on `ui-demo.vue` (dev-only)
- [ ] Add all missing translation keys to locale files
- [ ] Test language switching on all pages
- [ ] Commit: "🌐 i18n: complete root-level pages translations"

### Task 3: Settings Pages i18n
**Estimate**: 2-3 hours
- [ ] Validate `settings/profile.vue` (forms, validation)
- [ ] Validate `settings/security.vue` (security settings, 2FA)
- [ ] Validate `settings/appearance.vue` (theme, preferences)
- [ ] Add `settings.*` translation keys to both locales
- [ ] Test all settings forms and interactions
- [ ] Commit: "🌐 i18n: complete settings pages translations"

### Task 4: Team Pages i18n
**Estimate**: 2-3 hours
- [ ] Validate `team/users.vue` (table, roles, invitations)
- [ ] Validate `team/settings.vue` (workspace settings)
- [ ] Validate `team/apis.vue` (API keys management)
- [ ] Add `team.*` translation keys to both locales
- [ ] Test team management workflows
- [ ] Commit: "🌐 i18n: complete team pages translations"

### Task 5: High-Priority Components i18n
**Estimate**: 4-5 hours
- [ ] Validate `global/` components (appbar, navigation)
- [ ] Validate `ui/` components (Alert, Button, Input, etc.)
- [ ] Validate `sidebar/` components
- [ ] Validate `dashboard/` components
- [ ] Validate `home/` components
- [ ] Add component-specific translation keys
- [ ] Test common user flows
- [ ] Commit: "🌐 i18n: complete high-priority components translations"

### Task 6: Medium-Priority Components i18n
**Estimate**: 4-5 hours
- [ ] Validate `settings/` components
- [ ] Validate `team/` components
- [ ] Validate `workspace/` components
- [ ] Validate `user/` components
- [ ] Validate `tokens/` components
- [ ] Add remaining translation keys
- [ ] Test feature-specific flows
- [ ] Commit: "🌐 i18n: complete medium-priority components translations"

### Task 7: Final Validation and Cleanup
**Estimate**: 1-2 hours
- [ ] Run vue-i18n-validator on entire `src/` directory
- [ ] Fix any remaining issues identified
- [ ] Verify no hardcoded strings remain
- [ ] Test complete application in both languages
- [ ] Check for unused translation keys
- [ ] Update documentation
- [ ] Final commit: "🌐 i18n: complete frontend i18n implementation"

---

## 6. Quality Assurance

### Validation Checklist
- [ ] All hardcoded strings replaced with `$t()` or `t()`
- [ ] All translation keys exist in both `en-US.ts` and `fr-FR.ts`
- [ ] French translations are accurate and natural
- [ ] Interpolation works correctly for dynamic values
- [ ] Pluralization handled where needed
- [ ] No console warnings for missing keys
- [ ] Language switcher works on all pages
- [ ] RTL considerations (if applicable)

### Testing Strategy
1. **Manual Testing**: Use dev mode language toggle on each page
2. **Console Monitoring**: Check for missing key warnings
3. **Visual Inspection**: Verify proper text display in both languages
4. **User Flows**: Test complete workflows in both languages
5. **Edge Cases**: Test empty states, error states, long text

### Common Issues to Watch For
- **Hardcoded button labels**: `label="Save"` → `:label="$t('actions.save', 'Save')"`
- **Hardcoded alert messages**: `message="Error"` → `:message="$t('error.message', 'An error occurred')"`
- **Hardcoded placeholders**: `placeholder="Enter name"` → `:placeholder="$t('form.name.placeholder', 'Enter name')"`
- **Validation messages**: Direct strings → translation keys
- **Table headers**: Hardcoded column names → translation keys
- **Empty states**: Hardcoded descriptions → translation keys

---

## 7. Acceptance Criteria

### Must Have
✅ Zero hardcoded user-facing strings in all Vue files
✅ All translation keys present in both locale files
✅ Language switcher works correctly across entire application
✅ No console errors or warnings related to i18n
✅ French translations are accurate and natural

### Should Have
✅ Consistent translation key naming across domains
✅ Proper fallback values for all translation calls
✅ Documentation updated with i18n guidelines
✅ Common translation keys in `common.*` section for reuse

### Nice to Have
✅ Translation key usage report/audit
✅ Automated tests for i18n coverage
✅ Translation memory for future languages

---

## 8. Timeline Estimate

| Phase | Effort | Cumulative |
|-------|--------|------------|
| Setup & Preparation | 0.5h | 0.5h |
| Root-Level Pages | 2-3h | 3-3.5h |
| Settings Pages | 2-3h | 5-6.5h |
| Team Pages | 2-3h | 7-9.5h |
| High-Priority Components | 4-5h | 11-14.5h |
| Medium-Priority Components | 4-5h | 15-19.5h |
| Final Validation | 1-2h | 16-21.5h |

**Total Estimate**: 16-22 hours (2-3 full working days)

---

## 9. Dependencies & Risks

### Dependencies
- ✅ `vue-i18n` library already configured
- ✅ Locale files structure established
- ✅ `vue-i18n-validator` agent available
- ✅ Language switcher in dev mode working

### Risks
- **Translation Quality**: Ensure French translations are reviewed by native speaker
- **Key Conflicts**: Watch for duplicate or conflicting key names
- **Performance**: Large locale files may need code splitting
- **Maintenance**: Keep locale files in sync when adding new features

### Mitigation Strategies
- Use consistent naming conventions documented in this spec
- Regular commits after each logical group
- Test thoroughly with language switcher after each phase
- Document any patterns or decisions for future reference

---

## 10. Future Enhancements

### Short Term
- Add TypeScript types for translation keys
- Implement translation key linting rules
- Add automated i18n coverage checks in CI/CD

### Long Term
- Support additional languages (Spanish, German, etc.)
- Implement translation management UI for non-developers
- Add context-aware translations based on user preferences
- Implement lazy-loading for locale files

---

## 11. References

### Documentation
- Vue i18n Docs: https://vue-i18n.intlify.dev/
- Project i18n Guidelines: `src/i18n/README.md` (if exists)
- Translation Agent: `.claude/agents/vue-i18n-validator.md`

### Related Specs
- Design System Documentation: `.claude/agents/frontend-design-system-dev.md`
- Component Guidelines: `src/components/CLAUDE.md`
- Page Routing: `src/pages/CLAUDE.md`

### Completed Work Reference
- Folders pages i18n: Commits from this session (e3e5871, ff01e25, 0f7d3f5, 871e887, 86eb104, d0982b7)
- Pattern examples in: `src/pages/folders/` directory
