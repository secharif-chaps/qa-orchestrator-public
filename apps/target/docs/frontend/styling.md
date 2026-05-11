# Styling Guide

This guide covers the styling approach, Tailwind CSS configuration, and design system implementation for the Basil frontend application.

## 🎨 Design System Overview

### Design Principles

- **Consistency**: Unified visual language across all components
- **Accessibility**: WCAG 2.1 AA compliance for inclusive design
- **Scalability**: Modular system that grows with the application
- **Performance**: Optimized CSS delivery and minimal bundle size
- **Maintainability**: Clear naming conventions and documentation

### Technology Stack

- **Tailwind CSS 4.x**: Utility-first CSS framework
- **PostCSS**: CSS processing and optimization
- **CSS Custom Properties**: Dynamic theming support
- **Feather Design System**: Custom component library
- **CSS Modules**: Component-scoped styling when needed

## 🛠️ Tailwind CSS Configuration

### Configuration File

```typescript
// tailwind.config.ts
import type { Config } from 'tailwindcss'

export default {
  content: [
    './components/**/*.{js,vue,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './plugins/**/*.{js,ts}',
    './app.vue',
    './error.vue',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      // Custom color palette
      colors: {
        // Primary brand colors
        brand: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6', // Primary
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
          950: '#172554',
        },

        // Semantic colors
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e', // Success
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
        },

        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b', // Warning
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
        },

        error: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#ef4444', // Error
          600: '#dc2626',
          700: '#b91c1c',
          800: '#991b1b',
          900: '#7f1d1d',
        },

        // Neutral grays
        gray: {
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#e5e7eb',
          300: '#d1d5db',
          400: '#9ca3af',
          500: '#6b7280',
          600: '#4b5563',
          700: '#374151',
          800: '#1f2937',
          900: '#111827',
          950: '#030712',
        },
      },

      // Typography
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },

      fontSize: {
        xs: ['0.75rem', { lineHeight: '1rem' }],
        sm: ['0.875rem', { lineHeight: '1.25rem' }],
        base: ['1rem', { lineHeight: '1.5rem' }],
        lg: ['1.125rem', { lineHeight: '1.75rem' }],
        xl: ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
        '6xl': ['3.75rem', { lineHeight: '1' }],
      },

      // Spacing
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
      },

      // Border radius
      borderRadius: {
        none: '0',
        sm: '0.125rem',
        DEFAULT: '0.25rem',
        md: '0.375rem',
        lg: '0.5rem',
        xl: '0.75rem',
        '2xl': '1rem',
        '3xl': '1.5rem',
        full: '9999px',
      },

      // Shadows
      boxShadow: {
        sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        DEFAULT: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        md: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        xl: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
        '2xl': '0 25px 50px -12px rgb(0 0 0 / 0.25)',
        inner: 'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
        none: 'none',
      },

      // Animation
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'bounce-in': 'bounceIn 0.6s ease-out',
        'pulse-slow': 'pulse 3s infinite',
      },

      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(100%)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        bounceIn: {
          '0%': { transform: 'scale(0.3)', opacity: '0' },
          '50%': { transform: 'scale(1.05)', opacity: '0.8' },
          '70%': { transform: 'scale(0.9)', opacity: '0.9' },
          '100%': { transform: 'scale(1)', opacity: '1' },
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/container-queries'),
  ],
} satisfies Config
```

## 🎯 Utility Classes and Patterns

### Layout Patterns

**Container and Grid Layouts**

```vue
<template>
  <!-- Container with max-width and centering -->
  <div class=\"container mx-auto px-4 sm:px-6 lg:px-8\">

    <!-- Responsive grid -->
    <div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6\">
      <div class=\"bg-white rounded-lg shadow-md p-6\">
        <!-- Card content -->
      </div>
    </div>

    <!-- Flexbox layouts -->
    <div class=\"flex items-center justify-between\">
      <h1 class=\"text-2xl font-bold\">Title</h1>
      <button class=\"btn-primary\">Action</button>
    </div>
  </div>
</template>
```

**Responsive Design Patterns**

```vue
<template>
  <!-- Mobile-first responsive design -->
  <div class=\"
    px-4 py-2           <!-- Mobile: padding -->
    sm:px-6 sm:py-3     <!-- Small screens: increased padding -->
    md:px-8 md:py-4     <!-- Medium screens: more padding -->
    lg:px-12 lg:py-6    <!-- Large screens: maximum padding -->
  \">
    <h1 class=\"
      text-lg            <!-- Mobile: large text -->
      sm:text-xl         <!-- Small: extra large -->
      md:text-2xl        <!-- Medium: 2x large -->
      lg:text-3xl        <!-- Large: 3x large -->
      font-bold text-gray-900
    \">
      Responsive Title
    </h1>
  </div>
</template>
```

### Component Styling Patterns

**Button Variants**

```vue
<template>
  <!-- Primary button -->
  <button class=\"
    inline-flex items-center justify-center
    px-4 py-2
    text-sm font-medium
    text-white bg-brand-600
    border border-transparent
    rounded-md
    shadow-sm
    hover:bg-brand-700
    focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500
    disabled:opacity-50 disabled:cursor-not-allowed
    transition-colors duration-200
  \">
    Primary Button
  </button>

  <!-- Secondary button -->
  <button class=\"
    inline-flex items-center justify-center
    px-4 py-2
    text-sm font-medium
    text-gray-700 bg-white
    border border-gray-300
    rounded-md
    shadow-sm
    hover:bg-gray-50
    focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500
    disabled:opacity-50 disabled:cursor-not-allowed
    transition-colors duration-200
  \">
    Secondary Button
  </button>
</template>
```

**Card Components**

```vue
<template>
  <!-- Basic card -->
  <div class=\"
    bg-white
    rounded-lg
    shadow-md
    border border-gray-200
    overflow-hidden
    hover:shadow-lg
    transition-shadow duration-200
  \">
    <div class=\"p-6\">
      <h3 class=\"text-lg font-medium text-gray-900 mb-2\">Card Title</h3>
      <p class=\"text-gray-600\">Card content goes here.</p>
    </div>
  </div>

  <!-- Card with header and footer -->
  <div class=\"bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden\">
    <div class=\"px-6 py-4 border-b border-gray-200 bg-gray-50\">
      <h3 class=\"text-lg font-medium text-gray-900\">Card Header</h3>
    </div>
    <div class=\"p-6\">
      <p class=\"text-gray-600\">Card body content.</p>
    </div>
    <div class=\"px-6 py-4 border-t border-gray-200 bg-gray-50\">
      <div class=\"flex justify-end space-x-3\">
        <button class=\"btn-secondary\">Cancel</button>
        <button class=\"btn-primary\">Save</button>
      </div>
    </div>
  </div>
</template>
```

**Form Elements**

```vue
<template>
  <!-- Form field with label and validation -->
  <div class=\"form-field\">
    <label class=\"block text-sm font-medium text-gray-700 mb-1\">
      Email Address
      <span class=\"text-red-500 ml-1\">*</span>
    </label>

    <input
      type=\"email\"
      class=\"
        w-full px-3 py-2
        border border-gray-300
        rounded-md
        shadow-sm
        placeholder-gray-400
        focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500
        invalid:border-red-300 invalid:focus:border-red-500 invalid:focus:ring-red-500
        disabled:bg-gray-50 disabled:cursor-not-allowed
        transition-colors duration-200
      \"
      placeholder=\"Enter your email\"
    />

    <p class=\"mt-1 text-sm text-red-600\">
      Please enter a valid email address.
    </p>
  </div>

  <!-- Select dropdown -->
  <div class=\"form-field\">
    <label class=\"block text-sm font-medium text-gray-700 mb-1\">
      Country
    </label>

    <select class=\"
      w-full px-3 py-2
      border border-gray-300
      rounded-md
      shadow-sm
      focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500
      disabled:bg-gray-50 disabled:cursor-not-allowed
      transition-colors duration-200
    \">
      <option>Select a country</option>
      <option>United States</option>
      <option>Canada</option>
      <option>United Kingdom</option>
    </select>
  </div>
</template>
```

## 🎨 Custom CSS Classes

### Component-Specific Classes

```css
/* assets/css/components.css */

/* Button variants */
.btn-primary {
  @apply bg-brand-600 hover:bg-brand-700 focus:ring-brand-500 inline-flex items-center justify-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50;
}

.btn-secondary {
  @apply focus:ring-brand-500 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors duration-200 hover:bg-gray-50 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50;
}

.btn-outline {
  @apply text-brand-700 border-brand-300 hover:bg-brand-50 focus:ring-brand-500 inline-flex items-center justify-center rounded-md border bg-transparent px-4 py-2 text-sm font-medium transition-colors duration-200 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50;
}

.btn-ghost {
  @apply focus:ring-brand-500 inline-flex items-center justify-center rounded-md border border-transparent bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-100 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50;
}

.btn-danger {
  @apply bg-error-600 hover:bg-error-700 focus:ring-error-500 inline-flex items-center justify-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50;
}

/* Form elements */
.form-input {
  @apply focus:ring-brand-500 focus:border-brand-500 w-full rounded-md border border-gray-300 px-3 py-2 placeholder-gray-400 shadow-sm transition-colors duration-200 focus:ring-1 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50;
}

.form-input-error {
  @apply form-input border-red-300 focus:border-red-500 focus:ring-red-500;
}

.form-label {
  @apply mb-1 block text-sm font-medium text-gray-700;
}

.form-label-required::after {
  @apply ml-1 text-red-500;
  content: '*';
}

.form-error {
  @apply mt-1 text-sm text-red-600;
}

.form-hint {
  @apply mt-1 text-sm text-gray-500;
}

/* Status indicators */
.status-success {
  @apply bg-success-100 text-success-800 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium;
}

.status-warning {
  @apply bg-warning-100 text-warning-800 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium;
}

.status-error {
  @apply bg-error-100 text-error-800 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium;
}

.status-info {
  @apply bg-brand-100 text-brand-800 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium;
}

/* Loading states */
.loading-spinner {
  @apply border-t-brand-600 animate-spin rounded-full border-2 border-gray-300;
}

.loading-skeleton {
  @apply animate-pulse rounded bg-gray-300;
}

/* Focus styles */
.focus-ring {
  @apply focus:ring-brand-500 focus:ring-2 focus:ring-offset-2 focus:outline-none;
}

/* Accessibility */
.sr-only {
  @apply absolute -m-px h-px w-px overflow-hidden border-0 p-0 whitespace-nowrap;
}
```

## 🌓 Dark Mode Support

### Dark Mode Configuration

```css
/* assets/css/dark-mode.css */

/* Dark mode color overrides */
.dark {
  --color-background: theme('colors.gray.900');
  --color-surface: theme('colors.gray.800');
  --color-text-primary: theme('colors.gray.100');
  --color-text-secondary: theme('colors.gray.300');
  --color-border: theme('colors.gray.700');
}

/* Dark mode utility classes */
.dark .bg-background {
  background-color: var(--color-background);
}

.dark .bg-surface {
  background-color: var(--color-surface);
}

.dark .text-primary {
  color: var(--color-text-primary);
}

.dark .text-secondary {
  color: var(--color-text-secondary);
}

.dark .border-default {
  border-color: var(--color-border);
}

/* Dark mode component overrides */
.dark .form-input {
  @apply border-gray-600 bg-gray-800 text-gray-100 placeholder-gray-400;
}

.dark .form-input:focus {
  @apply border-brand-500 ring-brand-500;
}

.dark .btn-secondary {
  @apply border-gray-600 bg-gray-700 text-gray-100 hover:bg-gray-600;
}
```

### Dark Mode Toggle Implementation

```vue
<template>
  <button
    class=\"
      p-2 rounded-md
      text-gray-500 hover:text-gray-700 hover:bg-gray-100
      dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800
      focus:outline-none focus:ring-2 focus:ring-brand-500
      transition-colors duration-200
    \"
    @click=\"toggleDarkMode\"
  >
    <Icon
      :name=\"isDark ? 'sun' : 'moon'\"
      class=\"w-5 h-5\"
    />
  </button>
</template>

<script setup lang=\"ts\">
const { isDark, toggleDarkMode } = useDarkMode()
</script>
```

## 📱 Responsive Design Guidelines

### Breakpoint Strategy

```typescript
// Tailwind breakpoints
const breakpoints = {
  sm: '640px', // Small devices (phones)
  md: '768px', // Medium devices (tablets)
  lg: '1024px', // Large devices (desktops)
  xl: '1280px', // Extra large devices
  '2xl': '1536px', // 2X large devices
}
```

### Mobile-First Approach

```vue
<template>
  <!-- Mobile-first responsive navigation -->
  <nav class=\"
    bg-white shadow-sm border-b border-gray-200
    dark:bg-gray-800 dark:border-gray-700
  \">
    <div class=\"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8\">
      <div class=\"flex justify-between h-16\">
        <!-- Logo -->
        <div class=\"flex items-center\">
          <img class=\"h-8 w-auto\" src=\"/logo.svg\" alt=\"Basil\" />
        </div>

        <!-- Mobile menu button -->
        <div class=\"md:hidden flex items-center\">
          <button
            class=\"
              p-2 rounded-md text-gray-400
              hover:text-gray-500 hover:bg-gray-100
              focus:outline-none focus:ring-2 focus:ring-brand-500
            \"
            @click=\"toggleMobileMenu\"
          >
            <Icon name=\"menu\" class=\"w-6 h-6\" />
          </button>
        </div>

        <!-- Desktop navigation -->
        <div class=\"hidden md:flex md:items-center md:space-x-8\">
          <NuxtLink
            v-for=\"item in navigation\"
            :key=\"item.name\"
            :to=\"item.href\"
            class=\"
              text-gray-500 hover:text-gray-900
              px-3 py-2 rounded-md text-sm font-medium
              transition-colors duration-200
            \"
          >
            {{ item.name }}
          </NuxtLink>
        </div>
      </div>
    </div>

    <!-- Mobile menu -->
    <div v-show=\"isMobileMenuOpen\" class=\"md:hidden\">
      <div class=\"px-2 pt-2 pb-3 space-y-1 bg-white shadow-lg\">
        <NuxtLink
          v-for=\"item in navigation\"
          :key=\"item.name\"
          :to=\"item.href\"
          class=\"
            text-gray-500 hover:text-gray-900 hover:bg-gray-50
            block px-3 py-2 rounded-md text-base font-medium
            transition-colors duration-200
          \"
          @click=\"closeMobileMenu\"
        >
          {{ item.name }}
        </NuxtLink>
      </div>
    </div>
  </nav>
</template>
```

### Container Queries

```vue
<template>
  <!-- Container queries for component-level responsiveness -->
  <div class=\"@container\">
    <div class=\"
      grid grid-cols-1 gap-4
      @sm:grid-cols-2
      @md:grid-cols-3
      @lg:grid-cols-4
    \">
      <div class=\"bg-white p-4 rounded-lg shadow\">
        <h3 class=\"text-sm @md:text-base @lg:text-lg font-medium\">
          Responsive Card Title
        </h3>
      </div>
    </div>
  </div>
</template>
```

## 🎯 Performance Optimization

### CSS Optimization Strategies

**Purging Unused CSS**

```javascript
// nuxt.config.ts
export default defineNuxtConfig({
  css: ['~/assets/css/main.css'],
  postcss: {
    plugins: {
      tailwindcss: {},
      autoprefixer: {},
      ...(process.env.NODE_ENV === 'production' && {
        '@fullhuman/postcss-purgecss': {
          content: [
            './components/**/*.{vue,js}',
            './layouts/**/*.vue',
            './pages/**/*.vue',
            './plugins/**/*.{js,ts}',
            './nuxt.config.{js,ts}',
            './app.vue',
          ],
          defaultExtractor: (content) => content.match(/[\\w-/:]+(?<!:)/g) || [],
          safelist: [
            /^(.*?)$/, // Keep dynamic classes
          ],
        },
      }),
    },
  },
})
```

**Critical CSS Inlining**

```vue
<!-- pages/index.vue -->
<template>
  <div>
    <!-- Above-the-fold content with critical styles -->
    <hero-section class=\"hero-critical\" />

    <!-- Lazy-loaded content -->
    <lazy-content-section />
  </div>
</template>

<style>
/* Critical CSS inlined in head */
.hero-critical {
  /* Essential styles for above-the-fold content */
}
</style>
```

### Loading States and Skeletons

```vue
<template>
  <!-- Skeleton loading state -->
  <div v-if=\"loading\" class=\"animate-pulse\">
    <div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6\">
      <div
        v-for=\"i in 6\"
        :key=\"i\"
        class=\"bg-gray-200 rounded-lg h-64\"
      ></div>
    </div>
  </div>

  <!-- Actual content -->
  <div v-else class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6\">
    <card-component
      v-for=\"item in items\"
      :key=\"item.id\"
      :item=\"item\"
    />
  </div>
</template>
```

## 🧪 CSS Testing

### Visual Regression Testing

```typescript
// tests/visual/components.spec.ts
import { test, expect } from '@playwright/test'

test.describe('Component Visual Tests', () => {
  test('Button variants should match designs', async ({ page }) => {
    await page.goto('/styleguide/buttons')

    // Test different button states
    await expect(page.locator('.btn-primary')).toHaveScreenshot('button-primary.png')
    await expect(page.locator('.btn-secondary')).toHaveScreenshot('button-secondary.png')

    // Test hover states
    await page.hover('.btn-primary')
    await expect(page.locator('.btn-primary')).toHaveScreenshot('button-primary-hover.png')
  })

  test('Dark mode should render correctly', async ({ page }) => {
    await page.goto('/dashboard')

    // Enable dark mode
    await page.evaluate(() => {
      document.documentElement.classList.add('dark')
    })

    await expect(page).toHaveScreenshot('dashboard-dark-mode.png')
  })
})
```

### Accessibility Testing

```typescript
// tests/accessibility/styling.spec.ts
import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.describe('Styling Accessibility Tests', () => {
  test('Color contrast should meet WCAG standards', async ({ page }) => {
    await page.goto('/components')

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
      .analyze()

    expect(accessibilityScanResults.violations).toEqual([])
  })

  test('Focus indicators should be visible', async ({ page }) => {
    await page.goto('/form-example')

    // Tab through form elements
    await page.keyboard.press('Tab')
    const focusedElement = await page.evaluate(() => document.activeElement?.tagName)

    // Check focus ring visibility
    const focusRing = await page.locator(':focus').evaluate((el) => {
      const styles = window.getComputedStyle(el)
      return styles.outline !== 'none' || styles.boxShadow.includes('ring')
    })

    expect(focusRing).toBe(true)
  })
})
```

## 📚 Style Guide Documentation

### Component Documentation

```vue
<!-- components/StyleGuide/ButtonShowcase.vue -->
<template>
  <div class=\"space-y-8\">
    <section>
      <h3 class=\"text-lg font-medium mb-4\">Button Variants</h3>
      <div class=\"flex flex-wrap gap-4\">
        <BaseButton variant=\"primary\">Primary</BaseButton>
        <BaseButton variant=\"secondary\">Secondary</BaseButton>
        <BaseButton variant=\"outline\">Outline</BaseButton>
        <BaseButton variant=\"ghost\">Ghost</BaseButton>
        <BaseButton variant=\"danger\">Danger</BaseButton>
      </div>
    </section>

    <section>
      <h3 class=\"text-lg font-medium mb-4\">Button Sizes</h3>
      <div class=\"flex items-center gap-4\">
        <BaseButton size=\"sm\">Small</BaseButton>
        <BaseButton size=\"md\">Medium</BaseButton>
        <BaseButton size=\"lg\">Large</BaseButton>
      </div>
    </section>

    <section>
      <h3 class=\"text-lg font-medium mb-4\">Button States</h3>
      <div class=\"flex gap-4\">
        <BaseButton disabled>Disabled</BaseButton>
        <BaseButton loading>Loading</BaseButton>
        <BaseButton icon=\"download\">With Icon</BaseButton>
      </div>
    </section>
  </div>
</template>
```

### Color Palette Documentation

```vue
<!-- components/StyleGuide/ColorPalette.vue -->
<template>
  <div class=\"space-y-8\">
    <section v-for=\"(colors, name) in colorPalette\" :key=\"name\">
      <h3 class=\"text-lg font-medium mb-4 capitalize\">{{ name }} Colors</h3>
      <div class=\"grid grid-cols-5 md:grid-cols-10 gap-2\">
        <div
          v-for=\"(color, shade) in colors\"
          :key=\"shade\"
          class=\"flex flex-col items-center\"
        >
          <div
            :class=\"`bg-${name}-${shade}`\"
            class=\"w-16 h-16 rounded-lg shadow-sm border border-gray-200\"
          ></div>
          <span class=\"mt-2 text-xs text-gray-600\">{{ shade }}</span>
          <span class=\"text-xs text-gray-400 font-mono\">{{ color }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang=\"ts\">
const colorPalette = {
  brand: {
    50: '#eff6ff',
    100: '#dbeafe',
    // ... other shades
  },
  success: {
    // ... success colors
  },
  // ... other color sets
}
</script>
```

## 🎨 Best Practices

### CSS Organization

1. **Use Tailwind utilities first** before writing custom CSS
2. **Create reusable component classes** for common patterns
3. **Follow atomic design principles** for scalable architecture
4. **Document color usage** and maintain design tokens
5. **Test across devices** and screen sizes regularly

### Performance Guidelines

1. **Minimize custom CSS** by leveraging Tailwind utilities
2. **Use CSS containment** for better performance
3. **Optimize font loading** with appropriate strategies
4. **Implement progressive enhancement** for CSS features
5. **Monitor bundle sizes** and optimize accordingly

### Accessibility Standards

1. **Maintain WCAG 2.1 AA compliance** for color contrast
2. **Provide focus indicators** for all interactive elements
3. **Use semantic markup** with appropriate styling
4. **Test with screen readers** and keyboard navigation
5. **Implement proper color semantics** for status indicators
