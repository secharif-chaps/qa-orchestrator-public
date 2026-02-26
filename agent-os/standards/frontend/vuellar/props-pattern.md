# Vuellar Props Pattern

## Standardized Props

Most Vuellar components follow a consistent props pattern:

| Prop | Purpose | Values |
|------|---------|--------|
| `variant` | Visual style/weight | `primary`, `secondary`, `tertiary` |
| `intent` | Semantic meaning | `neutral`, `accent`, `success`, `warning`, `danger`, `info` |
| `color` | Decorative color | `sage`, `almond`, `pink`, `indigo`, `yellow`, `cherry`, `cyan` |
| `size` | Component dimensions | `xs`, `sm`, `md`, `lg` |

---

## variant

Controls the visual style/weight of the component.

| Value | Description | Use Case |
|-------|-------------|----------|
| `primary` | Filled/solid background | Main actions, emphasis |
| `secondary` | Lighter/outlined | Secondary actions, less emphasis |
| `tertiary` | Minimal/text-only | Subtle actions (Button only) |

```vue
<template>
  <!-- Primary: solid, emphasized -->
  <Button label="Save" variant="primary" />

  <!-- Secondary: lighter, less emphasis -->
  <Button label="Cancel" variant="secondary" />

  <!-- Tertiary: minimal, subtle -->
  <Button label="Skip" variant="tertiary" />
</template>
```

---

## intent

Semantic colors that convey meaning. Use for status, feedback, or actions with significance.

| Value | Color | Use Case |
|-------|-------|----------|
| `neutral` | Gray | Default state, neutral information |
| `accent` | Pink | Branded elements, highlights |
| `success` | Green | Positive outcomes, confirmations |
| `warning` | Orange | Caution, attention needed |
| `danger` | Red | Errors, destructive actions |
| `info` | Blue | Informational messages |

```vue
<template>
  <!-- Success: positive feedback -->
  <Alert variant="success" title="Saved!" description="Your changes are saved." />
  <Tag label="Active" intent="success" />

  <!-- Danger: errors, destructive -->
  <Alert variant="danger" title="Error" description="Something went wrong." />
  <Button label="Delete" variant="secondary" intent="danger" />

  <!-- Warning: caution -->
  <Alert variant="warning" title="Warning" description="This cannot be undone." />
  <Badge number="!" intent="warning" />

  <!-- Info: informational -->
  <Alert variant="info" title="Note" description="Remember to save." />
  <Tag label="New" intent="info" />
</template>
```

---

## color

Decorative colors for visual variety **without semantic meaning**.

| Value | Description |
|-------|-------------|
| `sage` | Muted green-gray (primary brand) |
| `almond` | Warm beige |
| `pink` | Soft pink |
| `indigo` | Deep purple-blue |
| `yellow` | Warm yellow |
| `cherry` | Deep red |
| `cyan` | Bright teal |

```vue
<template>
  <!-- Decorative badges for categories -->
  <Tag label="Design" color="pink" />
  <Tag label="Engineering" color="indigo" />
  <Tag label="Marketing" color="yellow" />

  <!-- Decorative avatars -->
  <Avatar label="JD" color="sage" />
  <Avatar label="AB" color="cherry" />
</template>
```

---

## Intent vs Color

**Key Rule**: Use `intent` for meaning, `color` for decoration.

| Scenario | Use |
|----------|-----|
| Success message | `intent="success"` |
| Error state | `intent="danger"` |
| Category badge | `color="pink"` |
| User avatar | `color="sage"` |
| Warning alert | `intent="warning"` |
| Brand highlight | `intent="accent"` |

When both are provided, **intent takes precedence**.

```vue
<template>
  <!-- CORRECT: intent for semantic meaning -->
  <Badge number="3" intent="danger" />  <!-- Error count -->
  <Tag label="Completed" intent="success" />  <!-- Status -->

  <!-- CORRECT: color for decoration -->
  <Tag label="Frontend" color="cyan" />  <!-- Category -->
  <Avatar label="JD" color="pink" />  <!-- User avatar -->

  <!-- AVOID: color for semantic meaning -->
  <Tag label="Error" color="cherry" />  <!-- Should use intent="danger" -->
</template>
```

---

## size

Component dimensions. Availability varies by component.

| Value | Use Case |
|-------|----------|
| `xs` | Extra small, compact UIs |
| `sm` | Small, dense layouts |
| `md` | Medium, default size |
| `lg` | Large, prominent elements |

```vue
<template>
  <!-- Size variations -->
  <Button label="Small" size="sm" />
  <Button label="Medium" size="md" />
  <Button label="Large" size="lg" />

  <Input id="search" size="sm" placeholder="Search..." />
  <Input id="name" size="lg" placeholder="Full name" />

  <Avatar label="JD" size="xs" />
  <Avatar label="JD" size="lg" />
</template>
```

---

## Props by Component

### Button
```typescript
{
  label?: string
  icon?: string           // Left icon (FontAwesome)
  iconRight?: string      // Right icon
  size?: 'lg' | 'md' | 'sm' | 'xs'
  variant?: 'primary' | 'secondary' | 'tertiary' | 'accent'
  loading?: boolean
  disabled?: boolean
  block?: boolean         // Full width
}
```

### Input
```typescript
{
  id: string              // Required
  type?: InputTypeHTMLAttribute
  label?: string
  placeholder?: string
  size?: 'sm' | 'md' | 'lg'
  icon?: string
  iconRight?: string
  error?: string
  loading?: boolean
  disabled?: boolean
  required?: boolean
}
```

### Alert
```typescript
{
  title?: string
  description?: string
  icon?: string
  action?: string         // Action button label
  loading?: boolean
  showBackground?: boolean
  variant?: 'primary' | 'info' | 'success' | 'warning' | 'danger'
}
```

### Tag
```typescript
{
  label?: string
  icon?: string
  size?: 'md' | 'sm' | 'xs'
  variant?: 'primary' | 'secondary'
  intent?: 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'
  color?: 'sage' | 'almond' | 'pink' | 'indigo' | 'yellow' | 'cherry' | 'cyan'
}
```

### Badge
```typescript
{
  icon?: string
  number?: string
  size?: 'lg' | 'sm' | 'xs'
  fill?: boolean
  variant?: 'primary' | 'secondary'
  intent?: 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info'
  color?: 'sage' | 'almond' | 'pink' | 'indigo' | 'yellow' | 'cherry' | 'cyan'
}
```

### Modal
```typescript
{
  displayModal: boolean   // v-model
  title?: string
  description?: string
  icon?: string
  iconClose?: string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  content?: boolean       // Content-only mode
}
```

---

## Common Patterns

### Action Buttons
```vue
<template>
  <div class="flex gap-2">
    <!-- Primary action -->
    <Button label="Save" variant="primary" />

    <!-- Secondary action -->
    <Button label="Cancel" variant="secondary" />

    <!-- Destructive action -->
    <Button label="Delete" variant="secondary" intent="danger" />
  </div>
</template>
```

### Status Indicators
```vue
<template>
  <!-- With text -->
  <Tag label="Active" intent="success" />
  <Tag label="Pending" intent="warning" />
  <Tag label="Inactive" intent="neutral" />

  <!-- Dot only -->
  <Bullet intent="success" /> Online
  <Bullet intent="danger" /> Offline
</template>
```

### Form Fields
```vue
<template>
  <div class="flex flex-col gap-4">
    <Input
      id="email"
      v-model="email"
      type="email"
      label="Email"
      placeholder="you@example.com"
      :error="emailError"
      required
    />

    <Textarea
      id="message"
      v-model="message"
      label="Message"
      placeholder="Enter your message..."
      rows="4"
    />

    <Button
      label="Submit"
      variant="primary"
      :loading="isSubmitting"
      :disabled="!isValid"
    />
  </div>
</template>
```
