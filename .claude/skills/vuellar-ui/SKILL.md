---
name: vuellar-ui
description: Vuellar component library (@owlint/feathers-vue). CRITICAL - Activates when creating OR modifying any .vue file with UI elements. When modifying existing components, verify Vuellar components are used (not custom CSS for toggles, buttons, inputs, tags), correct props pattern (variant/intent/color/size), and Avatar vs Badge distinction. Fix any violations found.
allowed-tools: Read, Write, Edit, Glob, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

# Vuellar UI Component Library

**CRITICAL RULE**: Always use Vuellar components from `@owlint/feathers-vue`. Never create custom UI blocks when a Vuellar component exists.

## MANDATORY CHECKLIST (Before Writing ANY UI Code)

Before creating any UI element, ASK YOURSELF:

1. **Does Vuellar have this component?** Check the list below
2. **Can I compose Vuellar components?** Combine them before creating custom
3. **Am I about to write custom CSS for a toggle, button, input, switch, tag, badge?** STOP - Use Vuellar!

### Common Mistakes to AVOID

| DON'T                             | DO                                                              |
| --------------------------------- | --------------------------------------------------------------- |
| Custom toggle with checkbox + CSS | `<Switch v-model="value" />`                                    |
| Custom status dot with div + CSS  | `<Bullet intent="success" />`                                   |
| Custom label/tag with span + CSS  | `<Tag label="Active" intent="success" />`                       |
| Custom button with button + CSS   | `<Button variant="primary" label="Save" />`                     |
| Manual on/off toggle styling      | `<Switch :model-value="value" @update:model-value="handler" />` |

## Quick Reference

```typescript
import {
  // Core (Electrons)
  Avatar,
  Badge,
  Bullet,
  Button,
  Checkbox,
  Input,
  Label,
  Link,
  Radio,
  Select,
  Switch,
  Tab,
  Tag,
  Textarea,
  Toggle,

  // Building Blocks (Atoms)
  Breadcrumb,
  Chips,
  DateRangePicker,
  Pagination,
  Searchbar,
  Table,

  // Composites (Molecules/Organisms)
  Alert,
  Menu,
  Modal,
} from "@owlint/feathers-vue";
```

## Props Pattern

| Prop      | Purpose                 | Values                                                         |
| --------- | ----------------------- | -------------------------------------------------------------- |
| `variant` | Visual weight           | `primary`, `secondary`, `tertiary`                             |
| `intent`  | Semantic meaning        | `neutral`, `accent`, `success`, `warning`, `danger`, `info`    |
| `color`   | Decorative (no meaning) | `sage`, `almond`, `pink`, `indigo`, `yellow`, `cherry`, `cyan` |
| `size`    | Dimensions              | `xs`, `sm`, `md`, `lg`                                         |

### Intent vs Color Rule

- **Use `intent`** for semantic meaning (success states, errors, warnings)
- **Use `color`** for decoration only (categories, avatars, visual variety)

```vue
<!-- Correct: intent for status -->
<Tag label="Active" intent="success" />
<Alert variant="danger" title="Error" />

<!-- Correct: color for decoration -->
<Tag label="Design" color="pink" />
<Avatar label="JD" color="sage" />
```

## Component Selection

| Need                    | Use           | Not        |
| ----------------------- | ------------- | ---------- |
| Trigger action          | `Button`      | `Link`     |
| Navigate                | `Link`        | `Button`   |
| Multiple select         | `Checkbox`    | `Radio`    |
| Single select           | `Radio`       | `Checkbox` |
| Immediate toggle        | `Switch`      | `Checkbox` |
| Status dot only         | `Bullet`      | `Badge`    |
| Status with text        | `Badge`/`Tag` | `Bullet`   |
| 5+ options              | `Select`      | `Radio`    |
| Removable label         | `Chips`       | `Tag`      |
| **User representation** | `Avatar`      | `Badge`    |
| **Icon display**        | `Badge`       | `Avatar`   |

## Avatar vs Badge

**CRITICAL**: These components have distinct purposes:

### Avatar - User Representation Only

Use Avatar **only** for displaying user/person representation with initials or profile images.

```vue
<!-- Correct: User representation -->
<Avatar label="JD" color="sage" />
<Avatar label="Nicolas Mercier" color="pink" />
```

### Badge - Icon Display

Use Badge for displaying icons (e.g., entity types, categories, actions). **Always prefer `variant="secondary"`** for a lighter, more subtle UI.

```vue
<!-- Correct: Icon display with secondary variant -->
<Badge variant="secondary" color="sage" icon="fa fa-building" />
<Badge variant="secondary" color="pink" icon="fa fa-folder" />
<Badge variant="secondary" color="indigo" icon="fa-solid fa-magnifying-glass" />
```

**Never use Avatar for icons** - Avatar is strictly for user/person representation.

## Creating Custom Components

Only create custom components when Vuellar doesn't provide what you need.

### Checklist Before Creating

1. Is there a Vuellar component for this?
2. Can I compose multiple Vuellar components?
3. Is this truly reusable (used in 3+ places)?
4. Does it follow the same props pattern as Vuellar?

### Custom Component Guidelines

If you must create a custom component:

1. **Follow Vuellar props pattern** (variant, intent, color, size)
2. **Use Vuellar primitives inside** your custom component
3. **Document props and slots** with TypeScript
4. **Place in `src/components/`** organized by feature or `ui/` for generic

## Documentation

For detailed component props and examples, see:

- [components.md](references/components.md) - Full component reference
- [props-pattern.md](references/props-pattern.md) - Props system details
- [examples.md](references/examples.md) - Code examples for common patterns
