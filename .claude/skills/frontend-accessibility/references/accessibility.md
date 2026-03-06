# UI Accessibility Standards (RGAA 4.1 / WCAG 2.1 AA)

## Overview

Basil follows the French RGAA (Référentiel Général d'Amélioration de l'Accessibilité) 4.1 standards, which ensure compliance with international WCAG 2.1 AA standards.

---

## Core Principles

### Semantic HTML

Use appropriate HTML elements that convey meaning to assistive technologies:

```vue
<template>
    <nav role="navigation" aria-label="Main navigation">...</nav>
    <main role="main">...</main>
    <button type="button">Action</button>
    <!-- Not <div onclick> -->
</template>
```

### Keyboard Navigation

Ensure all interactive elements are accessible via keyboard with visible focus indicators:

- All functionality available via keyboard
- Logical tab order
- Visible focus states
- Escape key closes modals/dropdowns

### Color Contrast

Maintain sufficient contrast ratios (RGAA requirements):

| Element                          | Minimum Ratio |
| -------------------------------- | ------------- |
| Normal text                      | 4.5:1         |
| Large text (18px+ or 14px+ bold) | 3:1           |
| UI components                    | 3:1           |

**Never rely solely on color to convey information.**

---

## Images

### Informative Images

```vue
<template>
    <!-- ✅ Good: Descriptive alt text -->
    <img src="/chart.png" alt="Sales increased by 25% from January to March 2024" />

    <!-- ✅ Complex images with detailed description -->
    <figure>
        <img
            src="/complex-chart.png"
            alt="Quarterly revenue breakdown by department"
            aria-describedby="chart-description"
        />
        <figcaption id="chart-description">
            Q1 2024 revenue: Engineering $2.5M, Sales $1.8M, Marketing $0.7M
        </figcaption>
    </figure>
</template>
```

### Decorative Images

```vue
<template>
    <!-- ✅ Good: Empty alt for decorative images -->
    <img src="/decoration.png" alt="" role="presentation" />
</template>
```

### Icons

```vue
<template>
    <!-- ✅ Icons with text labels -->
    <button type="button">
        <Icon name="download" aria-hidden="true" />
        Download Report
    </button>

    <!-- ✅ Icon-only buttons -->
    <button type="button" aria-label="Close dialog">
        <Icon name="close" aria-hidden="true" />
    </button>
</template>
```

---

## Links

### Descriptive Link Text

```vue
<template>
    <!-- ❌ Bad: Vague link text -->
    <a href="/report.pdf">Click here</a>

    <!-- ✅ Good: Descriptive link text -->
    <a href="/report.pdf">Download Q1 2024 Financial Report (PDF, 2.3MB)</a>

    <!-- ✅ External links with indication -->
    <a
        href="https://external-site.com"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Visit external documentation (opens in new tab)"
    >
        External Documentation
        <Icon name="external-link" aria-hidden="true" />
    </a>
</template>
```

---

## Forms

### Proper Labels and Error Handling

```vue
<template>
    <form @submit.prevent="handleSubmit" novalidate>
        <div class="form-group">
            <label for="email">
                Email Address
                <span aria-label="required">*</span>
            </label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                :aria-invalid="!!errors.email"
                :aria-describedby="errors.email ? 'email-error' : 'email-help'"
                autocomplete="email"
                required
            />
            <div id="email-help" class="form-help">
                We'll use this to contact you about your account
            </div>
            <div v-if="errors.email" id="email-error" class="form-error" role="alert">
                {{ errors.email }}
            </div>
        </div>
    </form>
</template>
```

### Form Rules

- All form controls **MUST** have associated labels
- Use `<fieldset>` and `<legend>` for grouped form controls
- Required fields **MUST** be clearly indicated
- Error messages **MUST** be associated with their controls using `aria-describedby`
- Use `aria-invalid` to indicate validation state

---

## Modals and Dynamic Content

### Accessible Modal Implementation

```vue
<template>
    <div v-if="isOpen" class="modal-overlay" @keydown.esc="closeModal">
        <div
            ref="modalRef"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            :aria-describedby="descId"
            @click.stop
        >
            <h2 :id="titleId">{{ title }}</h2>
            <p :id="descId">{{ description }}</p>

            <div class="modal-actions">
                <button @click="confirmAction">Confirm</button>
                <button ref="closeButtonRef" @click="closeModal">Cancel</button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
// Focus management
watch(isOpen, (newValue) => {
    if (newValue) {
        previousActiveElement.value = document.activeElement as HTMLElement
        nextTick(() => {
            modalRef.value?.focus()
            trapFocus(modalRef.value!)
        })
    } else {
        previousActiveElement.value?.focus()
    }
})
</script>
```

---

## Screen Reader Announcements

### Announce Dynamic Content

```typescript
// composables/useAccessibility.ts
export const useAccessibility = () => {
    const announceToScreenReader = (
        message: string,
        priority: 'polite' | 'assertive' = 'polite',
    ) => {
        const announcement = document.createElement('div')
        announcement.setAttribute('aria-live', priority)
        announcement.setAttribute('aria-atomic', 'true')
        announcement.className = 'sr-only'
        announcement.textContent = message

        document.body.appendChild(announcement)
        setTimeout(() => document.body.removeChild(announcement), 1000)
    }

    return { announceToScreenReader }
}
```

Usage:

```typescript
announceToScreenReader('Data has been updated', 'polite')
announceToScreenReader('Form has 3 errors. Please review and correct.', 'assertive')
```

---

## Page Structure

### Required Elements

```vue
<template>
    <html :lang="currentLocale">
        <head>
            <title>{{ pageTitle }} - Basil Application</title>
        </head>
        <body>
            <!-- Skip navigation links -->
            <a href="#main-content" class="skip-link">Skip to main content</a>

            <header role="banner">
                <nav role="navigation" aria-label="Main navigation">...</nav>
            </header>

            <main id="main-content" role="main">
                <h1>Page Title</h1>
                <!-- Page content with proper heading hierarchy (h1 → h2 → h3) -->
            </main>

            <footer role="contentinfo">...</footer>
        </body>
    </html>
</template>
```

### Rules

- Every page **MUST** have a unique, descriptive `<title>`
- Document language **MUST** be declared with `lang` attribute
- Each page **MUST** have exactly one `h1` element
- Use heading hierarchy without skipping levels

---

## Hidden Content

### Screen Reader Only Content

```vue
<template>
    <!-- ✅ Screen reader only content -->
    <span class="sr-only">Additional context for screen readers</span>

    <!-- ✅ Properly hidden decorative content -->
    <div aria-hidden="true">
        <Icon name="decoration" />
    </div>
</template>

<style>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
```

---

## Testing Checklist

### Keyboard Navigation

- [ ] All interactive elements are focusable with Tab
- [ ] Focus order is logical and intuitive
- [ ] All functionality is available via keyboard
- [ ] Focus indicators are visible and high contrast
- [ ] Escape key closes modals/dropdowns

### Screen Reader Testing

- [ ] Test with NVDA (Windows), VoiceOver (Mac), or Orca (Linux)
- [ ] All content is announced correctly
- [ ] Navigation landmarks work properly
- [ ] Form labels and errors are announced
- [ ] Dynamic content changes are announced

### Color and Contrast

- [ ] Use tools like WebAIM Color Contrast Checker
- [ ] Test with color blindness simulators
- [ ] Ensure information isn't conveyed by color alone
- [ ] Verify focus indicators meet contrast requirements

### Zoom Testing

- [ ] Test at 200% zoom without horizontal scrolling
- [ ] Verify functionality at different viewport sizes
