# Specification: Vuellar UI Component Migration

## Goal

Migrate custom UI components from `/mint-front/src/components/ui/` to the Vuellar component library (`@owlint/feathers-vue`). This consolidates UI components, reduces maintenance burden, and leverages the shared design system across Chapsvision projects.

## User Stories

- As a developer, I want to use a single component library so that I maintain consistency across projects and reduce duplicate code.
- As a maintainer, I want fewer custom components so that bug fixes and design updates propagate automatically from the library.

## Migration Strategy

### Approach
- **Migration order**: By import count (highest impact first)
- **Granularity**: One component at a time (atomic PRs)
- **Backward compatibility**: None - direct replacement, no wrappers
- **Forward compatibility**: Set props that will be implemented in Vuellar (dismissible, clearable, helper) so code is ready when library updates

### Scope

**In Scope - Components to Migrate**:
| Component | Imports | Vuellar Equivalent | Match Level |
|-----------|---------|-------------------|-------------|
| Button | 43 | Button | Full |
| Alert | 18 | Alert | Partial |
| Input | 16 | Input | Partial |
| Indicator | 8 | Bullet | Partial |

**Out of Scope**:
- Nuxt migration (separate project)
- Reka UI components (keep as-is)
- Layout components (`AppLayout.vue`, `PageHeader.vue`)
- Feature-specific components (`CompanyCard.vue`, etc.)
- Badge component (different design approach - uses custom semantic color tokens)
- Card component (custom design patterns)
- Any component without a Vuellar equivalent

## Component Migration Details

### 1. Button (43 imports) - Full Match

**Custom Props** → **Vuellar Props**:
| Custom | Vuellar | Migration |
|--------|---------|-----------|
| `variant` | `variant` | Direct: `primary`, `secondary`, `tertiary`, `accent` |
| `size` | `size` | Direct: `sm`, `md`, `lg` (Vuellar also has `xs`) |
| `label` | `label` | Direct |
| `icon` | `icon` | Direct |
| `iconPosition="right"` | `iconRight` | Use `iconRight` prop instead |
| `iconOnly` | - | Remove, Vuellar auto-detects |
| `loading` | `loading` | Direct |
| `disabled` | `disabled` | Direct |
| `dark` | - | Remove, Vuellar auto-adapts |
| `type` | - | Use native HTML `type` attribute |
| - | `block` | New: full-width option available |

**Migration Example**:
```vue
<!-- Before -->
<Button variant="primary" icon="fa fa-save" iconPosition="left" label="Save" />
<Button variant="secondary" icon="fa fa-arrow-right" iconPosition="right" label="Next" />
<Button variant="tertiary" icon="fa fa-times" iconOnly />

<!-- After -->
<Button variant="primary" icon="fa fa-save" label="Save" />
<Button variant="secondary" iconRight="fa fa-arrow-right" label="Next" />
<Button variant="tertiary" icon="fa fa-times" />
```

---

### 2. Alert (18 imports) - Partial Match

**Custom Props** → **Vuellar Props**:
| Custom | Vuellar | Migration |
|--------|---------|-----------|
| `variant="info"` | `variant="info"` | Direct |
| `variant="success"` | `variant="success"` | Direct |
| `variant="warning"` | `variant="warning"` | Direct |
| `variant="error"` | `variant="danger"` | Rename to `danger` |
| `variant="accent"` | `intent="accent"` or `color="pink"` | Use intent, fallback to color |
| `variant="neutral"` | `color="sage"` or `variant="primary"` | Use sage color or primary |
| `title` | `title` | Direct |
| `message` | `description` | Rename to `description` |
| `icon` | `icon` | Direct |
| `show` | - | Use `v-if` externally |
| `dismissible` | `dismissible` | **Forward-compatible**: Set true, will work when Vuellar implements |
| `closable` | `dismissible` | Merge into `dismissible` |

**Variant Mapping Rules**:
```typescript
// Migration mapping
const variantMap = {
  'info': { variant: 'info' },
  'success': { variant: 'success' },
  'warning': { variant: 'warning' },
  'error': { variant: 'danger' },
  'accent': { intent: 'accent' }, // fallback: color="pink"
  'neutral': { color: 'sage' },   // fallback: variant="primary"
}
```

**Migration Example**:
```vue
<!-- Before -->
<Alert variant="error" title="Error" message="Something went wrong" dismissible />
<Alert variant="accent" title="Highlight" message="Important info" />
<Alert variant="neutral" title="Note" message="Just a note" />

<!-- After -->
<Alert variant="danger" title="Error" description="Something went wrong" dismissible />
<Alert intent="accent" title="Highlight" description="Important info" />
<Alert color="sage" title="Note" description="Just a note" />
```

**Slots Migration**:
- Custom Alert has `default`, `actions`, `action` slots
- Vuellar Alert has `action` prop (button label)
- **For usages with slots**: Refactor to use `action` prop or wrap Alert with custom content

---

### 3. Input (16 imports) - Partial Match

**Custom Props** → **Vuellar Props**:
| Custom | Vuellar | Migration |
|--------|---------|-----------|
| `id` (optional) | `id` (required) | Always provide id |
| `v-model` | `v-model` | Direct |
| `type` | `type` | Direct |
| `label` | `label` | Direct |
| `placeholder` | `placeholder` | Direct |
| `size` | `size` | Direct: `sm`, `md`, `lg` |
| `icon` | `icon` | Direct (left icon) |
| - | `iconRight` | New: right icon available |
| `error` | `error` | Direct |
| `helper` | `helper` | **Forward-compatible**: Set value, will work when Vuellar implements |
| `clearable` | `clearable` | **Forward-compatible**: Set true, will work when Vuellar implements |
| `disabled` | `disabled` | Direct |
| `required` | `required` | Direct |
| `dark` | - | Remove, Vuellar auto-adapts |
| - | `loading` | New: loading state available |

**Events Migration**:
| Custom | Vuellar | Migration |
|--------|---------|-----------|
| `@blur` | `@blur` | Direct |
| `@focus` | `@focus` | Direct |
| `@enter` | `@keyup.enter` | Use native event |

**Migration Example**:
```vue
<!-- Before -->
<Input
  v-model="search"
  label="Search"
  placeholder="Type to search..."
  icon="fa fa-search"
  helper="Press enter to search"
  clearable
  @enter="handleSearch"
/>

<!-- After -->
<Input
  id="search-input"
  v-model="search"
  label="Search"
  placeholder="Type to search..."
  icon="fa fa-search"
  helper="Press enter to search"
  clearable
  @keyup.enter="handleSearch"
/>
```

---

### 4. Indicator → Bullet (8 imports) - Partial Match

**Custom Props** → **Vuellar Props**:
| Custom Color | Vuellar | Migration |
|--------------|---------|-----------|
| `color="primary"` | `color="sage"` | Decorative brand color |
| `color="success"` | `intent="success"` | Semantic |
| `color="warning"` | `intent="warning"` | Semantic |
| `color="error"` | `intent="danger"` | Semantic (danger, not error) |
| `color="info"` | `intent="info"` | Semantic |
| `color="accent"` | `intent="accent"` | Semantic |
| `color="slate"` | `intent="neutral"` | Semantic neutral |

**Size Migration**:
| Custom | Vuellar | Notes |
|--------|---------|-------|
| `size="sm"` | TBD | Check Vuellar Bullet sizes |
| `size="md"` | TBD | Check Vuellar Bullet sizes |
| `size="lg"` | TBD | Check Vuellar Bullet sizes |

**Visual Change Note**:
- Custom Indicator: Two-layer design (light container + dark dot)
- Vuellar Bullet: Simple solid dot
- **Accept visual change** - Bullet's minimal design is preferred

**Migration Example**:
```vue
<!-- Before -->
<Indicator color="success" size="md" />
<Indicator color="error" size="sm" />
<Indicator color="primary" size="lg" />

<!-- After -->
<Bullet intent="success" />
<Bullet intent="danger" />
<Bullet color="sage" />
```

---

## Files to Modify

### Components to Delete (after migration complete)
- `/mint-front/src/components/ui/Button.vue`
- `/mint-front/src/components/ui/Alert.vue`
- `/mint-front/src/components/ui/Input.vue`
- `/mint-front/src/components/ui/Indicator.vue`

### Files Requiring Import Updates
Run analysis per component to find all import locations:
```bash
grep -r "from '@/components/ui/Button'" --include="*.vue" --include="*.ts" mint-front/src/
```

### Documentation to Update
- `/mint-front/src/components/CLAUDE.md` - Remove migrated component docs
- `/mint-front/CLAUDE.md` - Update UI component references

## Out of Scope

- Creating new Vuellar components
- Modifying Vuellar library source code
- Migrating components without Vuellar equivalents
- Badge component (keeps custom semantic color tokens)
- Card component (keeps custom design patterns)
- Layout components
- Feature-specific components

## Testing Strategy

1. **Per-component migration**: Migrate one component, test all usages
2. **Visual regression**: Compare before/after screenshots
3. **Functionality check**: Verify all props, events, slots work
4. **Dark mode**: Test in both light and dark themes
5. **Build verification**: Ensure no TypeScript errors after migration

## Success Criteria

- All 4 components migrated to Vuellar equivalents
- All custom component files deleted
- No import errors in codebase
- Visual consistency maintained
- All existing functionality preserved (or documented as intentionally changed)
- Forward-compatible props set for upcoming Vuellar features
