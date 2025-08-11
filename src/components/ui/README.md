# Custom UI Components

This directory contains custom UI components that should be used throughout the application instead of third-party alternatives.

## Available Components

### Alert (`Alert.vue`)
A flexible alert component for displaying messages, warnings, and notifications.

**When to use:**
- System messages and notifications
- Error messages
- Success confirmations
- Warning alerts
- Information panels

**Variants:** `info`, `success`, `warning`, `error`

### Input (`Input.vue`)
A modern input field component with icon support and validation states.

**When to use:**
- Form inputs
- Search fields
- Text entry fields
- Any place requiring user text input

**Features:**
- Icon support
- Error states
- Helper text
- Clearable option
- Size variations

### Badge (`Badge.vue`)
A versatile badge component for status indicators and labels.

**When to use:**
- Status indicators (online/offline, active/inactive)
- Token counts
- User roles and permissions
- Feature flags (beta, new, coming soon)
- Tags and categories
- Small metadata display

**Variants:** `primary`, `success`, `warning`, `error`, `info`, `slate`

## Design Principles

All custom UI components follow these principles:

1. **Theme-aware**: Components adapt to both light and dark modes automatically
2. **Subtle aesthetics**: Use of soft gradients and pastel colors for a modern, professional look
3. **Consistent spacing**: Standardized padding and margins across all components
4. **Accessibility**: Proper ARIA labels and keyboard support where applicable
5. **TypeScript first**: Full TypeScript support with proper type definitions

## Color System

Components use a consistent color system:

- **Primary**: Purple tones (brand color)
- **Success**: Green tones (positive states)
- **Warning**: Yellow/amber tones (caution states)
- **Error**: Red tones (error states)
- **Info**: Blue tones (informational)
- **Slate**: Gray tones (neutral/subtle)

In light mode, components use pastel backgrounds with darker text.
In dark mode, components use subtle transparent backgrounds with lighter text.

## Usage Guidelines

1. **Always prefer custom components** over third-party alternatives
2. **Never import from Feathers or Reka** for these component types
3. **Use semantic variants** (e.g., use `success` for positive states, not just green)
4. **Keep consistent sizing** across similar components in the same context

## Examples

See `/ui-demo` page for live examples of all components and their variations.