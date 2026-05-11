# Web Accessibility Development Guide (RGAA 4.1 Compliance)

This guide provides comprehensive development practices for creating accessible web applications based on the French RGAA (Référentiel Général d'Amélioration de l'Accessibilité) 4.1 standards. The RGAA ensures compliance with international WCAG 2.1 AA standards and French legal requirements.

## 🎯 Overview

### What is RGAA?

The RGAA (General Framework for Accessibility Improvement) is the French standard for digital accessibility, comprising **106 criteria** across **13 thematic areas**. It ensures that digital services are accessible to people with disabilities and complies with both European and international accessibility standards.

### Why Accessibility Matters

- **Legal Compliance**: Required for public services and private companies with >250M€ annual revenue
- **Inclusive Design**: Ensures usability for 12M+ people with disabilities in France
- **Better UX**: Accessible design improves usability for all users
- **SEO Benefits**: Better structured content improves search engine rankings
- **Technical Quality**: Enforces clean, semantic HTML and proper architecture

### Who Should Use This Guide

- Frontend developers (Vue.js/Nuxt.js)
- Backend developers (Symfony/API Platform)
- UI/UX designers
- QA engineers
- Product managers

## 🏗️ Accessibility Architecture

### Frontend Integration

```typescript
// composables/useAccessibility.ts
export const useAccessibility = () => {
  const announceToScreenReader = (message: string, priority: 'polite' | 'assertive' = 'polite') => {
    const announcement = document.createElement('div')
    announcement.setAttribute('aria-live', priority)
    announcement.setAttribute('aria-atomic', 'true')
    announcement.className = 'sr-only'
    announcement.textContent = message

    document.body.appendChild(announcement)
    setTimeout(() => document.body.removeChild(announcement), 1000)
  }

  const trapFocus = (element: HTMLElement) => {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    )
    // Focus trapping implementation
  }

  return {
    announceToScreenReader,
    trapFocus,
  }
}
```

### Backend Accessibility Headers

```php
// src/Infrastructure/Http/AccessibilityHeaderSubscriber.php
final readonly class AccessibilityHeaderSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Add accessibility-friendly headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Ensure proper content type for accessibility tools
        if ($response->headers->get('Content-Type') === 'text/html') {
            $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        }
    }
}
```

## 📋 RGAA 4.1 Thematic Guidelines

### 1. Images (8 criteria)

Images must provide appropriate alternatives for screen readers and be properly implemented.

#### Best Practices

**Informative Images**

```vue
<template>
  <!-- ✅ Good: Descriptive alt text -->
  <img
    src="/chart.png"
    alt="Sales increased by 25% from January to March 2024"
    class="chart-image"
  />

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

**Decorative Images**

```vue
<template>
  <!-- ✅ Good: Empty alt for decorative images -->
  <img src="/decoration.png" alt="" role="presentation" />

  <!-- ✅ Better: Use CSS for decorative images -->
  <div class="hero-section" style="background-image: url('/hero-bg.jpg')" />
</template>
```

**Icon Implementation**

```vue
<template>
  <!-- ✅ Icons with text labels -->
  <button type="button" class="btn-primary">
    <Icon name="download" aria-hidden="true" />
    Download Report
  </button>

  <!-- ✅ Icon-only buttons -->
  <button type="button" aria-label="Close dialog" class="btn-icon">
    <Icon name="close" aria-hidden="true" />
  </button>
</template>
```

#### Development Rules

- All informative images **MUST** have meaningful `alt` attributes
- Decorative images **MUST** have empty `alt=""` attributes
- Complex images **SHOULD** include `aria-describedby` with detailed descriptions
- Icons **MUST** be marked with `aria-hidden="true"` when used with text
- Image captions **SHOULD** use `<figcaption>` within `<figure>` elements

### 2. Frames (2 criteria)

Frames and iframes must be properly titled and accessible.

#### Best Practices

```vue
<template>
  <!-- ✅ Good: Descriptive frame title -->
  <iframe
    src="https://www.youtube.com/embed/videoID"
    title="Product demonstration: Creating a new user account"
    width="560"
    height="315"
    frameborder="0"
  />

  <!-- ✅ Interactive frame with focus management -->
  <iframe
    src="/embedded-form.html"
    title="Contact form for support requests"
    :tabindex="isFormVisible ? 0 : -1"
    @load="onFrameLoad"
  />
</template>
```

#### Development Rules

- All frames **MUST** have descriptive `title` attributes
- Frame titles **MUST** describe the content or purpose
- Avoid generic titles like "Frame" or "Content"

### 3. Colors (4 criteria)

Color usage must ensure sufficient contrast and not convey information through color alone.

#### Best Practices

**Contrast Requirements**

```scss
// RGAA requires minimum contrast ratios
.text-normal {
  // Minimum 4.5:1 for normal text
  color: #2d3748; // Dark gray
  background-color: #ffffff; // White - 12.6:1 ratio ✅
}

.text-large {
  // Minimum 3:1 for large text (18px+ or 14px+ bold)
  color: #4a5568; // Medium gray
  background-color: #f7fafc; // Light gray - 7.8:1 ratio ✅
}

.interactive-elements {
  // Minimum 3:1 for UI components
  border: 2px solid #3182ce; // Blue border
  background-color: #ffffff; // 4.5:1 ratio ✅
}
```

**Color-Independent Information**

```vue
<template>
  <!-- ❌ Bad: Information conveyed only by color -->
  <span class="text-red">Error</span>
  <span class="text-green">Success</span>

  <!-- ✅ Good: Color + icons + text -->
  <span class="status status--error">
    <Icon name="alert-circle" aria-hidden="true" />
    Error: Invalid email format
  </span>
  <span class="status status--success">
    <Icon name="check-circle" aria-hidden="true" />
    Success: Account created
  </span>
</template>

<style scoped>
.status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 0.25rem;
}

.status--error {
  color: #742a2a;
  background-color: #fed7d7;
  border: 1px solid #fc8181;
}

.status--success {
  color: #22543d;
  background-color: #c6f6d5;
  border: 1px solid #68d391;
}
</style>
```

#### Development Rules

- Text contrast **MUST** meet minimum ratios (4.5:1 normal, 3:1 large text)
- UI component contrast **MUST** be at least 3:1
- Information **MUST NOT** be conveyed by color alone
- Focus indicators **MUST** be visible and high contrast
- Use tools like WebAIM Color Contrast Checker for validation

### 4. Multimedia (13 criteria)

Audio and video content must be accessible with proper alternatives.

#### Best Practices

**Video Implementation**

```vue
<template>
  <!-- ✅ Accessible video player -->
  <video controls preload="metadata" :aria-label="videoTitle" class="responsive-video">
    <source src="/video.mp4" type="video/mp4" />
    <source src="/video.webm" type="video/webm" />

    <!-- Captions for accessibility -->
    <track kind="captions" src="/captions-en.vtt" srclang="en" label="English captions" default />
    <track kind="captions" src="/captions-fr.vtt" srclang="fr" label="French captions" />

    <!-- Fallback for unsupported browsers -->
    <p>
      Your browser doesn't support video.
      <a href="/video.mp4" download>Download the video</a>
    </p>
  </video>

  <!-- Transcript link -->
  <div class="video-transcript">
    <button @click="toggleTranscript" :aria-expanded="showTranscript">
      {{ showTranscript ? 'Hide' : 'Show' }} Transcript
    </button>
    <div v-if="showTranscript" class="transcript-content">
      <!-- Transcript content -->
    </div>
  </div>
</template>
```

**Audio Implementation**

```vue
<template>
  <!-- ✅ Accessible audio player -->
  <audio controls preload="metadata" :aria-label="audioTitle">
    <source src="/audio.mp3" type="audio/mpeg" />
    <source src="/audio.ogg" type="audio/ogg" />

    <p>
      Your browser doesn't support audio.
      <a href="/audio.mp3" download>Download the audio</a>
    </p>
  </audio>

  <!-- Transcript for audio-only content -->
  <details class="audio-transcript">
    <summary>Audio Transcript</summary>
    <div class="transcript-content">
      <!-- Full transcript text -->
    </div>
  </details>
</template>
```

#### Development Rules

- Videos **MUST** have captions for speech content
- Audio content **MUST** have transcripts
- Media players **MUST** be keyboard accessible
- Auto-playing media **MUST** be avoidable or pausable
- Provide multiple formats for compatibility

### 5. Tables (6 criteria)

Data tables must have proper structure and headers for screen reader navigation.

#### Best Practices

**Simple Data Table**

```vue
<template>
  <!-- ✅ Properly structured data table -->
  <table class="data-table">
    <caption>
      Quarterly Sales Report 2024
    </caption>
    <thead>
      <tr>
        <th scope="col">Quarter</th>
        <th scope="col">Revenue</th>
        <th scope="col">Growth</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th scope="row">Q1 2024</th>
        <td>$2.5M</td>
        <td>+15%</td>
      </tr>
      <tr>
        <th scope="row">Q2 2024</th>
        <td>$3.1M</td>
        <td>+24%</td>
      </tr>
    </tbody>
  </table>
</template>
```

**Complex Data Table**

```vue
<template>
  <!-- ✅ Complex table with multiple header levels -->
  <table class="complex-table">
    <caption>
      Employee Performance by Department and Quarter
    </caption>
    <thead>
      <tr>
        <th scope="col" rowspan="2">Employee</th>
        <th scope="colgroup" colspan="2">Q1 2024</th>
        <th scope="colgroup" colspan="2">Q2 2024</th>
      </tr>
      <tr>
        <th scope="col">Sales</th>
        <th scope="col">Rating</th>
        <th scope="col">Sales</th>
        <th scope="col">Rating</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th scope="row">John Smith</th>
        <td headers="q1-sales">$125K</td>
        <td headers="q1-rating">4.2</td>
        <td headers="q2-sales">$142K</td>
        <td headers="q2-rating">4.5</td>
      </tr>
    </tbody>
  </table>
</template>
```

#### Development Rules

- Use `<table>` only for tabular data, never for layout
- Always include `<caption>` to describe table content
- Use `<th>` for header cells with appropriate `scope` attributes
- Complex tables **MAY** need `headers` attribute on `<td>` elements
- Avoid nested tables when possible

### 6. Links (5 criteria)

Links must be clearly identified and provide meaningful context.

#### Best Practices

**Descriptive Link Text**

```vue
<template>
  <!-- ❌ Bad: Vague link text -->
  <a href="/report.pdf">Click here</a>
  <a href="/products">Read more</a>

  <!-- ✅ Good: Descriptive link text -->
  <a href="/report.pdf">Download Q1 2024 Financial Report (PDF, 2.3MB)</a>
  <a href="/products">View our product catalog</a>

  <!-- ✅ Context for screen readers -->
  <a href="/user/123/edit" aria-label="Edit profile for John Smith"> Edit Profile </a>

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

**Link States and Focus**

```scss
.link {
  color: #3182ce;
  text-decoration: underline;

  &:hover {
    color: #2c5282;
    text-decoration: none;
  }

  &:focus {
    outline: 2px solid #3182ce;
    outline-offset: 2px;
    border-radius: 2px;
  }

  &:visited {
    color: #553c9a;
  }
}

// Skip links for keyboard navigation
.skip-link {
  position: absolute;
  top: -40px;
  left: 6px;
  background: #000;
  color: #fff;
  padding: 8px;
  z-index: 9999;
  text-decoration: none;

  &:focus {
    top: 6px;
  }
}
```

#### Development Rules

- Link text **MUST** be descriptive and meaningful
- Avoid generic text like "click here" or "read more"
- External links **SHOULD** be clearly indicated
- Links opening in new windows **MUST** be announced
- Ensure sufficient color contrast for all link states
- Skip links **SHOULD** be provided for keyboard navigation

### 7. Scripts (12 criteria)

JavaScript interactions must be accessible and keyboard-navigable.

#### Best Practices

**Accessible Modal/Dialog**

```vue
<template>
  <!-- ✅ Accessible modal implementation -->
  <div v-if="isOpen" class="modal-overlay" @click="closeModal" @keydown.esc="closeModal">
    <div
      ref="modalRef"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="descId"
      class="modal-content"
      @click.stop
    >
      <h2 :id="titleId">{{ title }}</h2>
      <p :id="descId">{{ description }}</p>

      <div class="modal-actions">
        <button @click="confirmAction" class="btn-primary">Confirm</button>
        <button ref="closeButtonRef" @click="closeModal" class="btn-secondary">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const modalRef = ref<HTMLElement>()
const closeButtonRef = ref<HTMLElement>()
const previousActiveElement = ref<HTMLElement>()

const { trapFocus } = useAccessibility()

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

**Accessible Dropdown Menu**

```vue
<template>
  <!-- ✅ Accessible dropdown menu -->
  <div class="dropdown" ref="dropdownRef">
    <button
      :aria-expanded="isOpen"
      aria-haspopup="true"
      :aria-controls="menuId"
      @click="toggleMenu"
      @keydown.down.prevent="openMenu"
      @keydown.up.prevent="openMenu"
      class="dropdown-trigger"
    >
      {{ selectedOption }}
      <Icon name="chevron-down" aria-hidden="true" />
    </button>

    <ul
      v-if="isOpen"
      :id="menuId"
      role="menu"
      class="dropdown-menu"
      @keydown.esc="closeMenu"
      @keydown.down.prevent="focusNext"
      @keydown.up.prevent="focusPrevious"
    >
      <li
        v-for="(option, index) in options"
        :key="option.value"
        role="menuitem"
        :tabindex="focusedIndex === index ? 0 : -1"
        @click="selectOption(option)"
        @keydown.enter.prevent="selectOption(option)"
        @keydown.space.prevent="selectOption(option)"
      >
        {{ option.label }}
      </li>
    </ul>
  </div>
</template>
```

#### Development Rules

- All interactive elements **MUST** be keyboard accessible
- Focus management **MUST** be implemented for dynamic content
- ARIA attributes **MUST** be used correctly for custom components
- Screen reader announcements **SHOULD** be provided for dynamic changes
- Avoid auto-focusing without user intention
- Ensure all JavaScript functionality has fallbacks

### 8. Mandatory Elements (3 criteria)

Essential page elements must be present and properly structured.

#### Best Practices

**Page Structure**

```vue
<template>
  <!-- ✅ Proper HTML5 document structure -->
  <html lang="en">
    <head>
      <meta charset="utf-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>{{ pageTitle }} - Basil Application</title>
    </head>
    <body>
      <!-- Skip navigation links -->
      <a href="#main-content" class="skip-link">Skip to main content</a>
      <a href="#navigation" class="skip-link">Skip to navigation</a>

      <!-- Main page structure -->
      <header role="banner">
        <nav id="navigation" role="navigation" aria-label="Main navigation">
          <!-- Navigation content -->
        </nav>
      </header>

      <main id="main-content" role="main">
        <!-- Page content -->
      </main>

      <footer role="contentinfo">
        <!-- Footer content -->
      </footer>
    </body>
  </html>
</template>
```

**Language Declaration**

```vue
<template>
  <!-- ✅ Document language -->
  <html :lang="currentLocale">
    <!-- Page content -->

    <!-- ✅ Language changes within content -->
    <p>
      Welcome to our application.
      <span lang="fr">Bienvenue dans notre application.</span>
    </p>
  </html>
</template>
```

#### Development Rules

- Every page **MUST** have a unique, descriptive `<title>`
- Document language **MUST** be declared with `lang` attribute
- Language changes **MUST** be marked with `lang` attribute
- Page **MUST** be valid HTML5

### 9. Information Structure (11 criteria)

Content must be logically structured with proper headings and landmarks.

#### Best Practices

**Heading Hierarchy**

```vue
<template>
  <!-- ✅ Logical heading structure -->
  <main>
    <h1>User Management Dashboard</h1>

    <section>
      <h2>Active Users</h2>
      <div>
        <h3>Recently Logged In</h3>
        <!-- User list -->

        <h3>Frequent Users</h3>
        <!-- User list -->
      </div>
    </section>

    <section>
      <h2>User Statistics</h2>
      <div>
        <h3>Monthly Growth</h3>
        <!-- Statistics content -->
      </div>
    </section>
  </main>
</template>
```

**Landmark Regions**

```vue
<template>
  <!-- ✅ Semantic landmarks -->
  <div class="app-layout">
    <header role="banner">
      <nav role="navigation" aria-label="Main navigation">
        <!-- Primary navigation -->
      </nav>
    </header>

    <aside role="complementary" aria-label="Sidebar">
      <!-- Secondary content -->
    </aside>

    <main role="main">
      <h1>Page Title</h1>

      <section aria-labelledby="content-heading">
        <h2 id="content-heading">Main Content</h2>
        <!-- Content -->
      </section>

      <section aria-labelledby="related-heading">
        <h2 id="related-heading">Related Information</h2>
        <!-- Related content -->
      </section>
    </main>

    <footer role="contentinfo">
      <!-- Footer content -->
    </footer>
  </div>
</template>
```

#### Development Rules

- Use heading hierarchy (`h1` → `h2` → `h3`) without skipping levels
- Each page **MUST** have exactly one `h1` element
- Use semantic HTML5 elements (`<section>`, `<article>`, `<aside>`)
- Provide landmark regions with appropriate ARIA labels
- Lists **MUST** use proper `<ul>`, `<ol>`, `<dl>` elements

### 10. Information Presentation (10 criteria)

Visual presentation must not interfere with accessibility and information clarity.

#### Best Practices

**Responsive and Zoom-Friendly Design**

```scss
// ✅ Flexible layout supporting 200% zoom
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem;

  // Flexible grid that adapts to zoom
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1rem;
}

// ✅ Relative units for scalability
.text-content {
  font-size: 1rem; // 16px base
  line-height: 1.5;

  h2 {
    font-size: 1.5rem; // 24px
    margin-bottom: 1rem;
  }

  h3 {
    font-size: 1.25rem; // 20px
    margin-bottom: 0.75rem;
  }
}

// ✅ Avoid horizontal scrolling
.scrollable-content {
  overflow-x: auto;
  max-width: 100%;

  table {
    min-width: 600px; // Minimum readable width
  }
}
```

**Hidden Content Management**

```vue
<template>
  <!-- ✅ Screen reader only content -->
  <span class="sr-only">Additional context for screen readers</span>

  <!-- ✅ Properly hidden decorative content -->
  <div aria-hidden="true">
    <Icon name="decoration" />
  </div>

  <!-- ✅ Conditionally hidden content -->
  <div v-if="showDetails" :aria-hidden="!showDetails">
    <p>Additional details...</p>
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

#### Development Rules

- Content **MUST** be readable at 200% zoom without horizontal scrolling
- Use relative units (`rem`, `em`, `%`) instead of fixed pixels
- Information **MUST NOT** rely solely on visual positioning
- Hidden content **MUST** use proper techniques (`sr-only`, `aria-hidden`)
- Avoid CSS that interferes with user stylesheets

### 11. Forms (13 criteria)

Forms must be fully accessible with proper labels, validation, and error handling.

#### Best Practices

**Form Structure and Labels**

```vue
<template>
  <!-- ✅ Accessible form with proper structure -->
  <form @submit.prevent="handleSubmit" novalidate>
    <fieldset>
      <legend>User Information</legend>

      <!-- ✅ Required field with proper labeling -->
      <div class="form-group">
        <label for="email" class="form-label">
          Email Address
          <span aria-label="required">*</span>
        </label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="form-input"
          :class="{ error: errors.email }"
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

      <!-- ✅ Radio button group -->
      <fieldset class="form-group">
        <legend>Account Type</legend>
        <div class="radio-group">
          <div class="radio-option">
            <input
              id="account-personal"
              v-model="form.accountType"
              type="radio"
              value="personal"
              name="accountType"
            />
            <label for="account-personal">Personal</label>
          </div>
          <div class="radio-option">
            <input
              id="account-business"
              v-model="form.accountType"
              type="radio"
              value="business"
              name="accountType"
            />
            <label for="account-business">Business</label>
          </div>
        </div>
      </fieldset>

      <!-- ✅ Checkbox with proper association -->
      <div class="form-group">
        <input id="newsletter" v-model="form.newsletter" type="checkbox" class="form-checkbox" />
        <label for="newsletter" class="checkbox-label">
          Subscribe to our newsletter for updates and tips
        </label>
      </div>
    </fieldset>

    <!-- ✅ Form actions -->
    <div class="form-actions">
      <button type="submit" class="btn-primary" :disabled="isSubmitting">
        {{ isSubmitting ? 'Creating Account...' : 'Create Account' }}
      </button>
      <button type="button" @click="resetForm" class="btn-secondary">Reset Form</button>
    </div>
  </form>
</template>
```

**Error Handling and Validation**

```vue
<script setup lang="ts">
const form = reactive({
  email: '',
  accountType: '',
  newsletter: false,
})

const errors = reactive({
  email: '',
  accountType: '',
})

const { announceToScreenReader } = useAccessibility()

// Real-time validation
watch(
  () => form.email,
  (newEmail) => {
    if (newEmail && !isValidEmail(newEmail)) {
      errors.email = 'Please enter a valid email address'
    } else {
      errors.email = ''
    }
  },
)

const handleSubmit = async () => {
  // Validate form
  const formErrors = validateForm(form)

  if (Object.keys(formErrors).length > 0) {
    Object.assign(errors, formErrors)

    // Announce errors to screen readers
    const errorCount = Object.keys(formErrors).length
    announceToScreenReader(
      `Form has ${errorCount} error${errorCount > 1 ? 's' : ''}. Please review and correct.`,
      'assertive',
    )

    // Focus first error field
    const firstErrorField = document.querySelector('[aria-invalid="true"]') as HTMLElement
    firstErrorField?.focus()

    return
  }

  // Submit form
  try {
    isSubmitting.value = true
    await submitForm(form)
    announceToScreenReader('Account created successfully!', 'polite')
  } catch (error) {
    announceToScreenReader('Error creating account. Please try again.', 'assertive')
  } finally {
    isSubmitting.value = false
  }
}
</script>
```

#### Development Rules

- All form controls **MUST** have associated labels
- Use `<fieldset>` and `<legend>` for grouped form controls
- Required fields **MUST** be clearly indicated
- Error messages **MUST** be associated with their controls using `aria-describedby`
- Use `aria-invalid` to indicate validation state
- Provide helpful instructions and format requirements
- Support autocomplete attributes for better UX

### 12. Navigation (12 criteria)

Navigation must be consistent, accessible, and provide multiple ways to find content.

#### Best Practices

**Primary Navigation**

```vue
<template>
  <!-- ✅ Accessible main navigation -->
  <nav role="navigation" aria-label="Main navigation">
    <ul class="nav-list">
      <li class="nav-item">
        <NuxtLink
          to="/"
          class="nav-link"
          :class="{ active: $route.path === '/' }"
          :aria-current="$route.path === '/' ? 'page' : undefined"
        >
          Home
        </NuxtLink>
      </li>
      <li class="nav-item">
        <button
          class="nav-link nav-dropdown-trigger"
          :aria-expanded="showProductsMenu"
          aria-haspopup="true"
          :aria-controls="productsMenuId"
          @click="toggleProductsMenu"
        >
          Products
          <Icon name="chevron-down" aria-hidden="true" />
        </button>

        <!-- ✅ Dropdown submenu -->
        <ul v-if="showProductsMenu" :id="productsMenuId" class="nav-submenu" role="menu">
          <li role="menuitem">
            <NuxtLink to="/products/web" class="nav-sublink"> Web Applications </NuxtLink>
          </li>
          <li role="menuitem">
            <NuxtLink to="/products/mobile" class="nav-sublink"> Mobile Apps </NuxtLink>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>
```

**Breadcrumb Navigation**

```vue
<template>
  <!-- ✅ Accessible breadcrumb navigation -->
  <nav aria-label="Breadcrumb" class="breadcrumb">
    <ol class="breadcrumb-list">
      <li class="breadcrumb-item">
        <NuxtLink to="/" class="breadcrumb-link">
          <Icon name="home" aria-hidden="true" />
          Home
        </NuxtLink>
      </li>
      <li class="breadcrumb-item">
        <Icon name="chevron-right" aria-hidden="true" class="breadcrumb-separator" />
        <NuxtLink to="/products" class="breadcrumb-link"> Products </NuxtLink>
      </li>
      <li class="breadcrumb-item">
        <Icon name="chevron-right" aria-hidden="true" class="breadcrumb-separator" />
        <span aria-current="page" class="breadcrumb-current"> Web Applications </span>
      </li>
    </ol>
  </nav>
</template>
```

**Search Functionality**

```vue
<template>
  <!-- ✅ Accessible search -->
  <form role="search" @submit.prevent="handleSearch" class="search-form">
    <label for="search-input" class="search-label"> Search </label>
    <div class="search-input-group">
      <input
        id="search-input"
        v-model="searchQuery"
        type="search"
        class="search-input"
        placeholder="Search products, docs, help..."
        :aria-expanded="showSuggestions"
        :aria-haspopup="showSuggestions"
        :aria-owns="suggestionsList"
        autocomplete="off"
        @input="handleSearchInput"
        @keydown.down.prevent="focusNextSuggestion"
        @keydown.up.prevent="focusPreviousSuggestion"
        @keydown.esc="closeSuggestions"
      />
      <button type="submit" class="search-button" aria-label="Search">
        <Icon name="search" aria-hidden="true" />
      </button>
    </div>

    <!-- ✅ Search suggestions -->
    <ul
      v-if="showSuggestions && suggestions.length"
      :id="suggestionsList"
      role="listbox"
      class="search-suggestions"
    >
      <li
        v-for="(suggestion, index) in suggestions"
        :key="suggestion.id"
        role="option"
        :aria-selected="focusedSuggestion === index"
        class="suggestion-item"
        @click="selectSuggestion(suggestion)"
      >
        {{ suggestion.title }}
      </li>
    </ul>
  </form>
</template>
```

#### Development Rules

- Navigation **MUST** be consistent across all pages
- Current page/section **MUST** be indicated with `aria-current`
- Navigation **MUST** be keyboard accessible
- Provide multiple ways to find content (navigation, search, sitemap)
- Group related navigation items logically
- Skip links **SHOULD** be provided for keyboard users

### 13. Consultation (4 criteria)

Content must be accessible and not interfere with assistive technologies.

#### Best Practices

**Document Downloads**

```vue
<template>
  <!-- ✅ Accessible document links -->
  <div class="document-list">
    <h3>Available Documents</h3>
    <ul>
      <li class="document-item">
        <a href="/documents/user-guide.pdf" class="document-link" download>
          User Guide
          <span class="document-meta"> (PDF, 2.3 MB, 45 pages) </span>
        </a>
        <p class="document-description">Complete guide for using all application features</p>
      </li>
      <li class="document-item">
        <a href="/documents/api-reference.docx" class="document-link" download>
          API Reference
          <span class="document-meta"> (DOCX, 1.1 MB) </span>
        </a>
        <p class="document-description">Technical documentation for developers</p>
      </li>
    </ul>
  </div>
</template>
```

**Page Refresh and Redirects**

```vue
<script setup lang="ts">
// ✅ Accessible page updates
const { announceToScreenReader } = useAccessibility()

// Announce automatic updates
const refreshData = async () => {
  try {
    const newData = await fetchLatestData()
    data.value = newData
    announceToScreenReader('Data has been updated', 'polite')
  } catch (error) {
    announceToScreenReader('Failed to update data', 'assertive')
  }
}

// Handle redirects with user notification
const redirectWithNotice = (url: string, delay = 5000) => {
  announceToScreenReader(`You will be redirected to ${url} in ${delay / 1000} seconds`, 'assertive')

  setTimeout(() => {
    navigateTo(url)
  }, delay)
}
</script>
```

#### Development Rules

- Automatic content changes **MUST** be announced to screen readers
- Page refreshes **SHOULD** be announced or avoided
- Time limits **MUST** be adjustable or removable
- Moving content **MUST** be pausable
- Provide alternatives for complex interactions

## 🧪 Testing and Validation

### Automated Testing Tools

```typescript
// vitest.config.ts - Add accessibility testing
export default defineConfig({
  test: {
    // ... other config
    setupFiles: ['./test/setup.ts'],
  },
})

// test/setup.ts
import { configure } from '@testing-library/vue'
import 'jest-axe/extend-expect'

configure({ testIdAttribute: 'data-testid' })

// test/accessibility.test.ts
import { describe, it, expect } from 'vitest'
import { render } from '@testing-library/vue'
import { axe, toHaveNoViolations } from 'jest-axe'
import MyComponent from '@/components/MyComponent.vue'

expect.extend(toHaveNoViolations)

describe('MyComponent Accessibility', () => {
  it('should not have accessibility violations', async () => {
    const { container } = render(MyComponent, {
      props: {
        /* test props */
      },
    })

    const results = await axe(container)
    expect(results).toHaveNoViolations()
  })
})
```

### Manual Testing Checklist

**Keyboard Navigation**

- [ ] All interactive elements are focusable with Tab
- [ ] Focus order is logical and intuitive
- [ ] All functionality is available via keyboard
- [ ] Focus indicators are visible and high contrast
- [ ] Escape key closes modals/dropdowns

**Screen Reader Testing**

- [ ] Test with NVDA (Windows), VoiceOver (Mac), or Orca (Linux)
- [ ] All content is announced correctly
- [ ] Navigation landmarks work properly
- [ ] Form labels and errors are announced
- [ ] Dynamic content changes are announced

**Color and Contrast**

- [ ] Use tools like WebAIM Color Contrast Checker
- [ ] Test with color blindness simulators
- [ ] Ensure information isn't conveyed by color alone
- [ ] Verify focus indicators meet contrast requirements

**Zoom and Responsive Testing**

- [ ] Test at 200% zoom without horizontal scrolling
- [ ] Verify functionality at different viewport sizes
- [ ] Check mobile accessibility with touch/voice input

### RGAA Compliance Validation

```bash
# Install accessibility linting tools
npm install --save-dev eslint-plugin-jsx-a11y @axe-core/cli

# Add to .eslintrc.js
module.exports = {
  extends: [
    'plugin:jsx-a11y/recommended'
  ],
  rules: {
    // Enforce RGAA-specific rules
    'jsx-a11y/lang': 'error',
    'jsx-a11y/heading-has-content': 'error',
    'jsx-a11y/no-redundant-roles': 'error',
  }
}

# Run automated accessibility tests
npx axe-cli http://localhost:3000 --tags wcag21aa
```

## 📋 Development Workflow Integration

### Pre-commit Hooks

```json
// package.json
{
  "husky": {
    "hooks": {
      "pre-commit": "lint-staged"
    }
  },
  "lint-staged": {
    "*.{vue,js,ts}": ["eslint --fix", "accessibility-check"]
  }
}
```

### Accessibility Review Checklist

Before merging any PR, ensure:

- [ ] All new interactive elements are keyboard accessible
- [ ] Proper ARIA attributes are used for custom components
- [ ] Color contrast meets RGAA requirements
- [ ] Form elements have proper labels and validation
- [ ] Dynamic content includes screen reader announcements
- [ ] No automated accessibility test failures
- [ ] Manual testing completed with keyboard and screen reader

## 🔧 Useful Tools and Resources

### Development Tools

- **axe DevTools**: Browser extension for accessibility testing
- **WAVE**: Web accessibility evaluation tool
- **Lighthouse**: Built-in Chrome accessibility audit
- **Color Oracle**: Color blindness simulator
- **Screen readers**: NVDA (free), VoiceOver (Mac), ORCA (Linux)

### Code Libraries

```bash
# Vue accessibility helpers
npm install @vue/a11y-utils

# Focus management
npm install focus-trap-vue

# ARIA utilities
npm install aria-hidden @reach/auto-id
```

### API Platform Accessibility

```php
// Ensure API responses support accessibility
#[ApiResource(
    normalizationContext: [
        'groups' => ['user:read'],
        'enable_max_depth' => true
    ]
)]
class User
{
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Email is required for accessibility compliance')]
    private string $email;

    // Provide alt text data for frontend
    #[Groups(['user:read'])]
    public function getAvatarAltText(): string
    {
        return "Profile picture of {$this->firstName} {$this->lastName}";
    }
}
```

## 📝 Conclusion

Following this RGAA 4.1 compliance guide ensures that the Basile project meets French accessibility requirements while providing an excellent user experience for all users. Regular testing, team training, and continuous improvement are key to maintaining accessibility standards.

Remember: **Accessibility is not a feature to be added later—it's a fundamental aspect of good development that should be considered from the beginning of every project.**

For questions or clarifications about accessibility implementation, consult with the team's accessibility specialist or refer to the official RGAA documentation at [accessibilite.numerique.gouv.fr](https://accessibilite.numerique.gouv.fr/).
