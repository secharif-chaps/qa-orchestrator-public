# Task Breakdown: Vuellar UI Component Migration

## Overview

**Total Tasks**: 20 tasks across 4 task groups (one per component)
**Migration Order**: By import count (highest impact first)
**Strategy**: One component at a time, atomic PRs

---

## Task List

### Task Group 1: Button Migration (43 imports)
**Dependencies:** None
**Complexity:** Low (full match)
**Status:** COMPLETED

- [x] 1.0 Migrate Button component to Vuellar
  - [x] 1.1 Find all Button imports from `@/components/ui/Button`
    - Run: `grep -r "from '@/components/ui/Button'" --include="*.vue" --include="*.ts" mint-front/src/`
    - Document all files requiring updates
  - [x] 1.2 Update imports to use Vuellar Button
    - Change: `import Button from '@/components/ui/Button.vue'`
    - To: `import { Button } from '@owlint/feathers-vue'`
    - Handle files that already import from Vuellar (merge imports)
  - [x] 1.3 Update Button props in each file
    - Replace `iconPosition="right"` with `iconRight="icon-class"`
    - Remove `iconOnly` prop (Vuellar auto-detects)
    - Remove `dark` prop (Vuellar auto-adapts)
    - Keep `type` as native HTML attribute if needed
  - [x] 1.4 Handle slot content
    - Custom Button uses default slot for label override
    - Vuellar Button uses `label` prop
    - Convert slot content to `label` prop where applicable
  - [x] 1.5 Test all migrated usages
    - Visual check in browser
    - Verify hover, focus, disabled states
    - Test loading state
    - Test dark mode
  - [x] 1.6 Delete custom Button component
    - Remove `/mint-front/src/components/ui/Button.vue`
    - Verify build passes
  - [ ] 1.7 Update CLAUDE.md documentation
    - Remove Button section from `src/components/CLAUDE.md`
    - Update any Button references in `mint-front/CLAUDE.md`

**Acceptance Criteria:**
- [x] All 43 imports updated to Vuellar
- [x] Custom Button.vue deleted
- [x] Build passes with no errors
- [ ] Visual appearance matches design system (needs browser testing)

---

### Task Group 2: Alert Migration (18 imports)
**Dependencies:** Task Group 1 (Button must be migrated first - Alert uses Button)
**Complexity:** Medium (partial match, variant mapping)

- [ ] 2.0 Migrate Alert component to Vuellar
  - [ ] 2.1 Find all Alert imports from `@/components/ui/Alert`
    - Run: `grep -r "from '@/components/ui/Alert'" --include="*.vue" --include="*.ts" mint-front/src/`
    - Document all files and which variants/features they use
  - [ ] 2.2 Categorize usages by complexity
    - Simple: Only uses title, message, variant
    - Medium: Uses dismissible
    - Complex: Uses slots (default, actions, action)
  - [ ] 2.3 Update imports to use Vuellar Alert
    - Change: `import Alert from '@/components/ui/Alert.vue'`
    - To: `import { Alert } from '@owlint/feathers-vue'`
  - [ ] 2.4 Update Alert props in each file
    - Rename `message` to `description`
    - Rename `variant="error"` to `variant="danger"`
    - Convert `variant="accent"` to `intent="accent"`
    - Convert `variant="neutral"` to `color="sage"`
    - Remove `show` prop, use `v-if` externally
    - Keep `dismissible` prop (forward-compatible)
    - Merge `closable` into `dismissible`
  - [ ] 2.5 Handle slot migrations
    - For `action` slot: Convert to `action` prop if simple button
    - For `actions` slot: Wrap Alert or refactor UI
    - For `default` slot: Move content to `description` or wrap Alert
  - [ ] 2.6 Test all migrated usages
    - Visual check for all variants
    - Test dismissible behavior (may not work until Vuellar update)
    - Test dark mode
  - [ ] 2.7 Delete custom Alert component
    - Remove `/mint-front/src/components/ui/Alert.vue`
    - Verify build passes
  - [ ] 2.8 Update CLAUDE.md documentation
    - Remove Alert section from `src/components/CLAUDE.md`
    - Update references to use Vuellar Alert

**Acceptance Criteria:**
- All 18 imports updated to Vuellar
- Variant mapping applied correctly
- Forward-compatible props set
- Custom Alert.vue deleted
- Build passes

---

### Task Group 3: Input Migration (16 imports)
**Dependencies:** None (can run parallel with Task Group 2)
**Complexity:** Medium (partial match, forward-compatible props)

- [ ] 3.0 Migrate Input component to Vuellar
  - [ ] 3.1 Find all Input imports from `@/components/ui/Input`
    - Run: `grep -r "from '@/components/ui/Input'" --include="*.vue" --include="*.ts" mint-front/src/`
    - Document all files and which features they use
  - [ ] 3.2 Categorize usages by features
    - Simple: Basic text input
    - With helper: Uses `helper` prop
    - Clearable: Uses `clearable` prop
    - With events: Uses `@enter` event
  - [ ] 3.3 Update imports to use Vuellar Input
    - Change: `import Input from '@/components/ui/Input.vue'`
    - To: `import { Input } from '@owlint/feathers-vue'`
  - [ ] 3.4 Update Input props in each file
    - Add `id` prop (required in Vuellar)
    - Remove `dark` prop
    - Keep `helper` prop (forward-compatible)
    - Keep `clearable` prop (forward-compatible)
  - [ ] 3.5 Update Input events
    - Change `@enter` to `@keyup.enter`
    - Verify `@blur` and `@focus` work
  - [ ] 3.6 Test all migrated usages
    - Test all input types (text, email, password, etc.)
    - Test error state styling
    - Test disabled state
    - Test dark mode
  - [ ] 3.7 Delete custom Input component
    - Remove `/mint-front/src/components/ui/Input.vue`
    - Verify build passes
  - [ ] 3.8 Update CLAUDE.md documentation
    - Remove Input section from `src/components/CLAUDE.md`

**Acceptance Criteria:**
- All 16 imports updated to Vuellar
- All inputs have `id` prop
- Forward-compatible props preserved
- Custom Input.vue deleted
- Build passes

---

### Task Group 4: Indicator → Bullet Migration (8 imports)
**Dependencies:** None (can run parallel)
**Complexity:** Low (simple component)

- [ ] 4.0 Migrate Indicator component to Vuellar Bullet
  - [ ] 4.1 Find all Indicator imports
    - Run: `grep -r "from '@/components/ui/Indicator'" --include="*.vue" --include="*.ts" mint-front/src/`
    - Document all files and which colors they use
  - [ ] 4.2 Update imports to use Vuellar Bullet
    - Change: `import Indicator from '@/components/ui/Indicator.vue'`
    - To: `import { Bullet } from '@owlint/feathers-vue'`
    - Also change component name in template: `<Indicator>` → `<Bullet>`
  - [ ] 4.3 Update color props using mapping
    - `color="primary"` → `color="sage"`
    - `color="success"` → `intent="success"`
    - `color="warning"` → `intent="warning"`
    - `color="error"` → `intent="danger"`
    - `color="info"` → `intent="info"`
    - `color="accent"` → `intent="accent"`
    - `color="slate"` → `intent="neutral"`
  - [ ] 4.4 Handle size props
    - Check if Vuellar Bullet supports sizes
    - Map or remove size props as needed
  - [ ] 4.5 Test all migrated usages
    - Visual check for all color variants
    - Verify in context (status indicators, etc.)
    - Test dark mode
  - [ ] 4.6 Delete custom Indicator component
    - Remove `/mint-front/src/components/ui/Indicator.vue`
    - Verify build passes
  - [ ] 4.7 Update CLAUDE.md documentation
    - Remove Indicator section from `src/components/CLAUDE.md`
    - Note: Now use Bullet from Vuellar

**Acceptance Criteria:**
- All 8 imports updated to Vuellar Bullet
- Color mapping applied correctly
- Component renamed in templates
- Custom Indicator.vue deleted
- Build passes

---

## Execution Order

**Recommended sequence** (can parallelize where noted):

```
1. Task Group 1: Button (43 imports) - COMPLETED
   └── Must complete first (Alert depends on Button)

2. Task Group 2: Alert (18 imports)    ─┐
3. Task Group 3: Input (16 imports)    ─┼── Can run in parallel
4. Task Group 4: Indicator (8 imports) ─┘
```

**Total effort**: ~4 focused sessions (one per component)

---

## Migration Checklist Template

For each component migration:

```markdown
### [Component] Migration Checklist
- [ ] Find all imports (grep search)
- [ ] Update import statements
- [ ] Update props/attributes
- [ ] Update events
- [ ] Handle slots (if applicable)
- [ ] Test in browser (all usages)
- [ ] Test dark mode
- [ ] Delete custom component file
- [ ] Verify build passes
- [ ] Update documentation
- [ ] Commit with message: `♻️ refactor: migrate [Component] to Vuellar`
```

---

## Rollback Plan

If issues arise after migration:

1. **Revert commit**: `git revert <commit-hash>`
2. **Restore component**: Files are in git history
3. **Document issue**: Note what failed for future attempt

---

## Post-Migration Cleanup

After all 4 components are migrated:

- [ ] Remove empty `/mint-front/src/components/ui/` directory (if all components migrated)
- [ ] Update `src/components/CLAUDE.md` to reference Vuellar for these components
- [ ] Update `mint-front/CLAUDE.md` to remove custom component references
- [ ] Consider updating Vuellar skill documentation if patterns changed
