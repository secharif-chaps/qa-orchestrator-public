# Vuellar Component Library

> Vuellar is Chapsvision's Vue 3 component library built with TypeScript, Tailwind CSS, and Reka UI.

## Installation

```bash
npm install @owlint/feathers-vue
```

---

## Component Priority Rule

**CRITICAL**: Always use Vuellar components first. Only create custom components when Vuellar doesn't meet the need.

### Decision Order

1. **Use Vuellar component** if it exists
2. **Compose Vuellar components** to build complex UI
3. **Create custom component** only as last resort

---

## Available Components

```typescript
import {
  // Electrons (Core Primitives)
  Avatar,           // User/entity visual representation
  Badge,            // Count/status indicator with text/icon
  Bullet,           // Simple status dot
  Button,           // Action trigger
  Checkbox,         // Multi-selection
  Input,            // Single-line text entry
  Label,            // Form field label
  Link,             // Navigation element
  Radio,            // Single selection from options
  Select,           // Dropdown selection
  Switch,           // Immediate on/off toggle
  Tab,              // Content panel switcher
  Tag,              // Static category label
  Textarea,         // Multi-line text entry
  Toggle,           // Segmented view/mode control

  // Atoms (Building Blocks)
  Breadcrumb,       // Navigation hierarchy
  Chips,            // Removable tag element
  DateRangePicker,  // Date range selection
  Pagination,       // Page navigation
  Searchbar,        // Search input with icon
  Table,            // Data grid

  // Molecules (Functional Composites)
  Alert,            // Feedback messages
  Menu,             // Dropdown menu

  // Organisms (Complex Composites)
  Modal,            // Overlay dialog
} from '@owlint/feathers-vue'
```

---

## Component Categories

### Electrons (Core Primitives)

Smallest UI building blocks:

| Component | Purpose |
|-----------|---------|
| `Avatar` | User/entity representation |
| `Badge` | Status with text/icon |
| `Bullet` | Simple status dot |
| `Button` | Trigger actions |
| `Checkbox` | Multiple selection |
| `Input` | Text entry |
| `Label` | Form labels |
| `Link` | Navigation |
| `Radio` | Single selection |
| `Select` | Dropdown |
| `Switch` | On/off toggle |
| `Tab` | Content panels |
| `Tag` | Static labels |
| `Textarea` | Multi-line text |
| `Toggle` | Segmented control |

### Atoms (Building Blocks)

Composed from electrons:

| Component | Purpose |
|-----------|---------|
| `Breadcrumb` | Navigation path |
| `Chips` | Removable tags |
| `DateRangePicker` | Date selection |
| `Pagination` | Page controls |
| `Searchbar` | Search input |
| `Table` | Data display |

### Molecules (Functional Composites)

Complex functional components:

| Component | Purpose |
|-----------|---------|
| `Alert` | Feedback messages |
| `Menu` | Dropdown actions |

### Organisms (Complex Composites)

Full-featured UI sections:

| Component | Purpose |
|-----------|---------|
| `Modal` | Dialog overlay |

---

## Component Selection Guide

| Need | Use | Don't Use |
|------|-----|-----------|
| Trigger action | `Button` | `Link` |
| Navigate to page | `Link` | `Button` |
| Select multiple | `Checkbox` | `Radio` |
| Select one | `Radio` | `Checkbox` |
| Immediate toggle | `Switch` | `Checkbox` |
| Form boolean | `Checkbox` | `Switch` |
| Static label | `Tag` | `Chips` |
| Removable label | `Chips` | `Tag` |
| Status dot only | `Bullet` | `Badge` |
| Status with text | `Badge` | `Bullet` |
| Short text input | `Input` | `Textarea` |
| Long text input | `Textarea` | `Input` |
| 5+ options | `Select` | `Radio` |
| 2-4 options | `Radio` | `Select` |
| View mode switch | `Toggle` | `Tab` |
| Content sections | `Tab` | `Toggle` |

---

## Dark Mode

Dark mode is class-based. Add `dark` class to parent:

```vue
<div class="dark">
  <!-- All Vuellar components use dark mode -->
  <Button label="Dark mode button" />
</div>
```

All Vuellar components automatically adapt colors for dark mode.

---

## Deprecated Components

### Indicator
**Use `Bullet` instead**. Will be removed in v1.0.

### ToggleGroup
**Use `Toggle` instead**. Will be removed in v1.0.

---

## Creating Custom Components

Only when Vuellar doesn't meet the need.

### Checklist

1. ✅ Does Vuellar have this component?
2. ✅ Can I compose Vuellar components?
3. ✅ Used in 3+ places (reusable)?
4. ✅ Follows Vuellar props pattern?

### Guidelines

If creating custom:

1. **Follow Vuellar props pattern** (variant, intent, color, size)
2. **Use Vuellar components inside** your custom component
3. **Document props and slots** with TypeScript
4. **Place in `src/components/`** organized by feature

```vue
<template>
  <div class="flex items-center gap-2">
    <span>{{ title }}</span>
    <Badge v-if="count" :number="String(count)" :intent="intent" />
  </div>
</template>

<script setup lang="ts">
import { Button, Badge } from '@owlint/feathers-vue'

// Follow Vuellar props pattern (interface + destructure with defaults)
interface Props {
  title: string
  count?: number
  variant?: 'primary' | 'secondary'
  intent?: 'neutral' | 'success' | 'danger'
}

const { title, count, variant = 'primary', intent = 'neutral' } = defineProps<Props>()
</script>
```
