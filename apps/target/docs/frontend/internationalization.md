# Internationalization (i18n) Guide

This guide covers the internationalization implementation using Nuxt i18n module in the Basil frontend application, including setup, translation management, and best practices.

## 🌍 i18n Configuration

### Nuxt i18n Setup

```typescript
// nuxt.config.ts
export default defineNuxtConfig({
  modules: ['@nuxtjs/i18n'],

  i18n: {
    defaultLocale: 'en',
    strategy: 'no_prefix',
    baseUrl: process.env.NUXT_APP_BASE_URL || 'http://localhost:3000',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'i18n_redirected',
      redirectOn: 'root',
    },
    locales: [
      {
        code: 'en',
        iso: 'en-US',
        name: 'English',
        file: 'en.json',
      },
      {
        code: 'fr',
        iso: 'fr-FR',
        name: 'Français',
        file: 'fr.json',
      },
    ],
  },
})
```

```typescript
// i18n/i18n.config.ts
export default defineI18nConfig(() => ({
  datetimeFormats: {
    en: {
      long: {
        year: '2-digit',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
    },
    fr: {
      long: {
        year: '2-digit',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      },
    },
  },
}))
```

## 📁 Translation Files Structure

### Directory Organization

```
pwa/i18n/locales/
├── en.json          # English translations (default)
└── fr.json          # French translations
```

### Base Translation File (Flat Structure)

```json
// i18n/locales/en.json
{
  "common.actions.save": "Save",
  "common.actions.cancel": "Cancel",
  "common.actions.delete": "Delete",
  "common.actions.edit": "Edit",
  "common.actions.create": "Create",
  "common.actions.search": "Search",
  "common.actions.filter": "Filter",
  "common.actions.reset": "Reset",
  "common.actions.submit": "Submit",
  "common.actions.close": "Close",
  "common.actions.confirm": "Confirm",
  "common.actions.back": "Back",
  "common.actions.next": "Next",
  "common.actions.previous": "Previous",
  "common.actions.loading": "Loading...",
  "common.actions.retry": "Try Again",

  "common.labels.name": "Name",
  "common.labels.email": "Email",
  "common.labels.password": "Password",
  "common.labels.confirmPassword": "Confirm Password",
  "common.labels.firstName": "First Name",
  "common.labels.lastName": "Last Name",
  "common.labels.phone": "Phone Number",
  "common.labels.address": "Address",
  "common.labels.city": "City",
  "common.labels.country": "Country",
  "common.labels.status": "Status",
  "common.labels.role": "Role",
  "common.labels.createdAt": "Created At",
  "common.labels.updatedAt": "Updated At",

  "common.status.active": "Active",
  "common.status.inactive": "Inactive",
  "common.status.pending": "Pending",
  "common.status.approved": "Approved",
  "common.status.rejected": "Rejected",
  "common.status.draft": "Draft",
  "common.status.published": "Published",

  "common.messages.success": "Operation completed successfully",
  "common.messages.error": "An error occurred",
  "common.messages.warning": "Warning",
  "common.messages.info": "Information",
  "common.messages.noData": "No data available",
  "common.messages.noResults": "No results found",
  "common.messages.confirmDelete": "Are you sure you want to delete this item?",
  "common.messages.unsavedChanges": "You have unsaved changes. Are you sure you want to leave?",

  "navigation.dashboard": "Dashboard",
  "navigation.users": "Users",
  "navigation.settings": "Settings",
  "navigation.profile": "Profile",
  "navigation.logout": "Logout",
  "navigation.home": "Home",
  "navigation.about": "About",
  "navigation.contact": "Contact",

  "auth.login.title": "Sign In",
  "auth.login.subtitle": "Welcome back! Please sign in to your account.",
  "auth.login.email": "Email Address",
  "auth.login.password": "Password",
  "auth.login.rememberMe": "Remember me",
  "auth.login.forgotPassword": "Forgot your password?",
  "auth.login.signIn": "Sign In",
  "auth.login.noAccount": "Don't have an account?",
  "auth.login.signUp": "Sign up here",

  "auth.register.title": "Create Account",
  "auth.register.subtitle": "Get started by creating your account.",
  "auth.register.firstName": "First Name",
  "auth.register.lastName": "Last Name",
  "auth.register.email": "Email Address",
  "auth.register.password": "Password",
  "auth.register.confirmPassword": "Confirm Password",
  "auth.register.terms": "I agree to the {termsLink} and {privacyLink}",
  "auth.register.termsOfService": "Terms of Service",
  "auth.register.privacyPolicy": "Privacy Policy",
  "auth.register.createAccount": "Create Account",
  "auth.register.hasAccount": "Already have an account?",
  "auth.register.signIn": "Sign in here",

  "auth.errors.invalidCredentials": "Invalid email or password",
  "auth.errors.emailRequired": "Email is required",
  "auth.errors.passwordRequired": "Password is required",
  "auth.errors.passwordTooShort": "Password must be at least 8 characters",
  "auth.errors.passwordsDoNotMatch": "Passwords do not match",
  "auth.errors.emailInvalid": "Please enter a valid email address",
  "auth.errors.termsRequired": "You must agree to the terms and conditions",

  "users.title": "Users",
  "users.subtitle": "Manage user accounts and permissions",
  "users.addUser": "Add User",
  "users.editUser": "Edit User",
  "users.deleteUser": "Delete User",
  "users.userDetails": "User Details",
  "users.noUsers": "No users found",
  "users.searchPlaceholder": "Search users by name or email...",

  "users.filters.all": "All Users",
  "users.filters.active": "Active",
  "users.filters.inactive": "Inactive",
  "users.filters.role": "Filter by Role",

  "users.table.name": "Name",
  "users.table.email": "Email",
  "users.table.role": "Role",
  "users.table.status": "Status",
  "users.table.lastLogin": "Last Login",
  "users.table.actions": "Actions",

  "users.form.personalInfo": "Personal Information",
  "users.form.accountInfo": "Account Information",
  "users.form.permissions": "Permissions",
  "users.form.firstName": "First Name",
  "users.form.lastName": "Last Name",
  "users.form.email": "Email Address",
  "users.form.role": "Role",
  "users.form.status": "Account Status",
  "users.form.sendWelcomeEmail": "Send welcome email",

  "users.messages.created": "User created successfully",
  "users.messages.updated": "User updated successfully",
  "users.messages.deleted": "User deleted successfully",
  "users.messages.error": "Failed to {action} user",

  "errors.page.404.title": "Page Not Found",
  "errors.page.404.message": "The page you're looking for doesn't exist.",
  "errors.page.404.goHome": "Go Home",
  "errors.page.500.title": "Server Error",
  "errors.page.500.message": "Something went wrong on our end.",
  "errors.page.500.retry": "Try Again",

  "errors.network.offline": "You appear to be offline",
  "errors.network.timeout": "Request timed out",
  "errors.network.serverError": "Server error occurred",

  "validations.required": "{field} is required",
  "validations.email": "Please enter a valid email address",
  "validations.minLength": "{field} must be at least {min} characters",
  "validations.maxLength": "{field} must not exceed {max} characters",
  "validations.pattern": "{field} format is invalid",
  "validations.numeric": "{field} must be a number",
  "validations.positive": "{field} must be a positive number",
  "validations.url": "Please enter a valid URL",
  "validations.phone": "Please enter a valid phone number"
}
```

### French Translation Example (Flat Structure)

```json
// i18n/locales/fr.json
{
  "common.actions.save": "Enregistrer",
  "common.actions.cancel": "Annuler",
  "common.actions.delete": "Supprimer",
  "common.actions.edit": "Modifier",
  "common.actions.create": "Créer",
  "common.actions.search": "Rechercher",
  "common.actions.filter": "Filtrer",
  "common.actions.reset": "Réinitialiser",
  "common.actions.submit": "Soumettre",
  "common.actions.close": "Fermer",
  "common.actions.confirm": "Confirmer",
  "common.actions.back": "Retour",
  "common.actions.next": "Suivant",
  "common.actions.previous": "Précédent",
  "common.actions.loading": "Chargement...",
  "common.actions.retry": "Réessayer",

  "common.labels.name": "Nom",
  "common.labels.email": "Email",
  "common.labels.password": "Mot de passe",
  "common.labels.confirmPassword": "Confirmer le mot de passe",
  "common.labels.firstName": "Prénom",
  "common.labels.lastName": "Nom de famille",
  "common.labels.phone": "Numéro de téléphone",
  "common.labels.address": "Adresse",
  "common.labels.city": "Ville",
  "common.labels.country": "Pays",
  "common.labels.status": "Statut",
  "common.labels.role": "Rôle",
  "common.labels.createdAt": "Créé le",
  "common.labels.updatedAt": "Mis à jour le",

  "common.status.active": "Actif",
  "common.status.inactive": "Inactif",
  "common.status.pending": "En attente",
  "common.status.approved": "Approuvé",
  "common.status.rejected": "Rejeté",
  "common.status.draft": "Brouillon",
  "common.status.published": "Publié",

  "auth.login.title": "Se connecter",
  "auth.login.subtitle": "Bienvenue ! Veuillez vous connecter à votre compte.",
  "auth.login.email": "Adresse email",
  "auth.login.password": "Mot de passe",
  "auth.login.rememberMe": "Se souvenir de moi",
  "auth.login.forgotPassword": "Mot de passe oublié ?",
  "auth.login.signIn": "Se connecter",
  "auth.login.noAccount": "Pas de compte ?",
  "auth.login.signUp": "S'inscrire ici",

  "users.title": "Utilisateurs",
  "users.subtitle": "Gérer les comptes utilisateurs et permissions",
  "users.addUser": "Ajouter un utilisateur",
  "users.editUser": "Modifier l'utilisateur",
  "users.deleteUser": "Supprimer l'utilisateur",
  "users.noUsers": "Aucun utilisateur trouvé",
  "users.searchPlaceholder": "Rechercher par nom ou email...",

  "users.messages.created": "Utilisateur créé avec succès",
  "users.messages.updated": "Utilisateur mis à jour avec succès",
  "users.messages.deleted": "Utilisateur supprimé avec succès",
  "users.messages.error": "Échec de l'action {action} utilisateur"
}
```

## 🔧 Translation Usage in Components

### Basic Translation Usage

```vue
<template>
  <div class="user-form">
    <h1>{{ $t('users.addUser') }}</h1>

    <form @submit.prevent="handleSubmit">
      <div class="form-field">
        <label>{{ $t('common.labels.firstName') }}</label>
        <input v-model="form.firstName" :placeholder="$t('users.form.firstName')" type="text" />
      </div>

      <div class="form-field">
        <label>{{ $t('common.labels.email') }}</label>
        <input v-model="form.email" :placeholder="$t('common.labels.email')" type="email" />
      </div>

      <div class="form-actions">
        <button type="button" @click="handleCancel">
          {{ $t('common.actions.cancel') }}
        </button>
        <button type="submit">
          {{ $t('common.actions.save') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
// Access translation function
const { t } = useI18n()

// Use in computed properties
const pageTitle = computed(() => t('users.addUser'))

// Use in methods
const showSuccessMessage = () => {
  const message = t('users.messages.created')
  // Show notification
}
</script>
```

### Translation with Interpolation

```vue
<template>
  <div>
    <!-- Basic interpolation -->
    <p>{{ $t('welcome.message', { name: user.name }) }}</p>

    <!-- HTML interpolation -->
    <p
      v-html="
        $t('terms.agreement', {
          termsLink: `<a href='/terms'>${$t('auth.register.termsOfService')}</a>`,
          privacyLink: `<a href='/privacy'>${$t('auth.register.privacyPolicy')}</a>`,
        })
      "
    ></p>

    <!-- Pluralization -->
    <p>{{ $t('items.count', itemCount, { count: itemCount }) }}</p>

    <!-- Number formatting -->
    <p>{{ $n(price, 'currency') }}</p>

    <!-- Date formatting -->
    <p>{{ $d(new Date(), 'long') }}</p>
  </div>
</template>

<script setup lang="ts">
const { t, n, d } = useI18n()

const user = ref({ name: 'John Doe' })
const itemCount = ref(5)
const price = ref(29.99)
</script>
```

### Composition API Usage

```vue
<script setup lang="ts">
const { t, locale, availableLocales, setLocale } = useI18n()

// Get current locale
const currentLocale = computed(() => locale.value)

// Get available locales
const locales = computed(() => availableLocales)

// Change locale
const changeLanguage = async (newLocale: string) => {
  await setLocale(newLocale)
}

// Translation with computed
const dynamicMessage = computed(() => {
  return t('dynamic.message', {
    timestamp: new Date().toISOString(),
  })
})

// Translation in methods
const showErrorMessage = (error: string) => {
  const message = t('errors.generic', { error })
  console.error(message)
}
</script>
```

## 🎛️ Language Switcher Component

```vue
<template>
  <div class="language-switcher">
    <button type="button" class="language-button" @click="toggleDropdown">
      <Icon name="globe" class="h-4 w-4" />
      <span class="ml-2">{{ currentLanguage?.name }}</span>
      <Icon name="chevron-down" class="ml-1 h-4 w-4" />
    </button>

    <div v-show="isOpen" class="language-dropdown" @click.stop>
      <button
        v-for="locale in availableLocales"
        :key="locale.code"
        type="button"
        class="language-option"
        :class="{ active: locale.code === currentLocale }"
        @click="selectLanguage(locale.code)"
      >
        <span class="flag">{{ getFlag(locale.code) }}</span>
        <span class="name">{{ locale.name }}</span>
        <Icon
          v-if="locale.code === currentLocale"
          name="check"
          class="ml-auto h-4 w-4 text-green-600"
        />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const { locale, locales, setLocale } = useI18n()

const isOpen = ref(false)

const currentLocale = computed(() => locale.value)

const availableLocales = computed(() => locales.value)

const currentLanguage = computed(() =>
  availableLocales.value.find((l) => l.code === currentLocale.value),
)

const toggleDropdown = () => {
  isOpen.value = !isOpen.value
}

const selectLanguage = async (localeCode: string) => {
  await setLocale(localeCode)
  isOpen.value = false

  // Save preference
  const settingsStore = useSettingsStore()
  await settingsStore.updateSettings({ language: localeCode })
}

const getFlag = (localeCode: string) => {
  const flags: Record<string, string> = {
    en: '🇺🇸',
    fr: '🇫🇷',
  }
  return flags[localeCode] || '🌐'
}

// Close dropdown when clicking outside
onClickOutside(templateRef, () => {
  isOpen.value = false
})
</script>

<style scoped>
.language-switcher {
  @apply relative inline-block;
}

.language-button {
  @apply flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none;
}

.language-dropdown {
  @apply absolute right-0 z-50 mt-2 w-48 rounded-md border border-gray-200 bg-white shadow-lg;
}

.language-option {
  @apply flex w-full items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none;
}

.language-option.active {
  @apply bg-blue-50 text-blue-700;
}

.flag {
  @apply mr-3 text-lg;
}

.name {
  @apply flex-1 text-left;
}
</style>
```

## 🔄 Dynamic Translation Loading

### Lazy Loading Translations

```typescript
// composables/useTranslations.ts
export const useTranslations = () => {
  const { locale } = useI18n()

  const loadModuleTranslations = async (module: string) => {
    try {
      const translations = await import(`~/i18n/modules/${module}.json`)

      // Merge with existing translations
      const { mergeLocaleMessage } = useI18n()
      mergeLocaleMessage(locale.value, {
        [module]: translations.default,
      })
    } catch (error) {
      console.warn(`Failed to load translations for module: ${module}`)
    }
  }

  const loadUserTranslations = async (userId: string) => {
    try {
      const { getUserTranslations } = useApi()
      const userTranslations = await getUserTranslations(userId)

      Object.entries(userTranslations).forEach(([locale, translations]) => {
        const { mergeLocaleMessage } = useI18n()
        mergeLocaleMessage(locale, translations)
      })
    } catch (error) {
      console.warn('Failed to load user-specific translations')
    }
  }

  return {
    loadModuleTranslations,
    loadUserTranslations,
  }
}
```

### Route-based Translation Loading

```vue
<!-- pages/admin/users.vue -->
<script setup lang="ts">
const { loadModuleTranslations } = useTranslations()

// Load admin-specific translations
await loadModuleTranslations('admin')
await loadModuleTranslations('users')

definePageMeta({
  middleware: 'auth',
  layout: 'admin',
})
</script>
```

## 🎯 Translation Management

### Translation Keys Convention (Flat Structure)

```typescript
// types/i18n.ts
export interface TranslationSchema {
  // Flat structure with dot notation
  'common.actions.save': string
  'common.actions.cancel': string
  'common.labels.firstName': string
  'common.labels.email': string
  'users.form.firstName': string
  'users.messages.created': string
  'auth.login.title': string
  'errors.page.404.title': string

  // With parameters
  'validations.required': string // Will use interpolation: { field: string }
  'users.messages.error': string // Will use interpolation: { action: string }
  'validations.minLength': string // Will use interpolation: { field: string, min: number }
}

// Usage with type safety
const { t } = useI18n<TranslationSchema>()
const message = t('users.form.firstName') // Type-safe
const validationMessage = t('validations.required', { field: 'Email' })
```

### Benefits of Flat Structure

1. **Simplicity**: No nested object handling required
2. **Performance**: Faster key lookups without object traversal
3. **Export/Import**: Easier CSV or spreadsheet management for translators
4. **Search & Replace**: Simple text-based operations across files
5. **Type Safety**: Better TypeScript autocomplete and validation
6. **Debugging**: Clearer error messages with full key paths
7. **Merge Conflicts**: Fewer Git conflicts with flat structure

### Translation Validation (Flat Structure)

```typescript
// utils/translation-validator.ts
export const validateTranslations = (translations: Record<string, string>) => {
  const errors: string[] = []

  Object.entries(translations).forEach(([key, value]) => {
    // Validate key format (dot notation)
    if (!/^[a-zA-Z0-9._]+$/.test(key)) {
      errors.push(`Invalid key format: ${key}`)
    }

    // Validate value
    if (typeof value !== 'string') {
      errors.push(`Invalid translation value at ${key}: expected string, got ${typeof value}`)
    } else if (value.trim() === '') {
      errors.push(`Empty translation at ${key}`)
    }

    // Validate interpolation syntax
    const interpolationRegex = /\{([^}]+)\}/g
    const matches = value.match(interpolationRegex)
    if (matches) {
      matches.forEach((match) => {
        const param = match.slice(1, -1) // Remove { }
        if (!/^[a-zA-Z0-9_]+$/.test(param)) {
          errors.push(`Invalid interpolation parameter "${param}" in ${key}`)
        }
      })
    }
  })

  return errors
}

// Usage in build process
const englishTranslations = await import('./i18n/en.json')
const validationErrors = validateTranslations(englishTranslations.default)

if (validationErrors.length > 0) {
  console.warn('Translation validation errors:', validationErrors)
}
```

## 🧪 Testing Internationalization

### Translation Tests (Flat Structure)

```typescript
// tests/i18n/translations.test.ts
import { describe, it, expect } from 'vitest'
import en from '~/i18n/en.json'
import fr from '~/i18n/fr.json'

describe('Translation Files', () => {
  const locales = { en, fr }

  it('should have consistent keys across all locales', () => {
    const englishKeys = Object.keys(en)

    Object.entries(locales).forEach(([locale, translations]) => {
      if (locale === 'en') return

      const localeKeys = Object.keys(translations)
      const missingKeys = englishKeys.filter((key) => !localeKeys.includes(key))
      const extraKeys = localeKeys.filter((key) => !englishKeys.includes(key))

      expect(missingKeys).toEqual([])
      expect(extraKeys).toEqual([])
    })
  })

  it('should not have empty translations', () => {
    Object.entries(locales).forEach(([locale, translations]) => {
      const emptyKeys = Object.entries(translations)
        .filter(([key, value]) => !value || value.trim() === '')
        .map(([key]) => key)

      expect(emptyKeys).toEqual([])
    })
  })

  it('should have valid key format (dot notation)', () => {
    Object.entries(locales).forEach(([locale, translations]) => {
      const invalidKeys = Object.keys(translations).filter((key) => !/^[a-zA-Z0-9._]+$/.test(key))
      expect(invalidKeys).toEqual([])
    })
  })

  it('should have valid interpolation syntax', () => {
    Object.entries(locales).forEach(([locale, translations]) => {
      const invalidKeys = findInvalidInterpolations(translations)
      expect(invalidKeys).toEqual([])
    })
  })

  it('should have consistent interpolation parameters across locales', () => {
    const englishInterpolations = getInterpolationParams(en)

    Object.entries(locales).forEach(([locale, translations]) => {
      if (locale === 'en') return

      const localeInterpolations = getInterpolationParams(translations)

      Object.keys(englishInterpolations).forEach((key) => {
        if (key in localeInterpolations) {
          expect(localeInterpolations[key]).toEqual(englishInterpolations[key])
        }
      })
    })
  })
})

function findInvalidInterpolations(translations: Record<string, string>): string[] {
  const invalidKeys: string[] = []
  const interpolationRegex = /\{([^}]+)\}/g

  Object.entries(translations).forEach(([key, value]) => {
    const matches = value.match(interpolationRegex)
    if (matches) {
      matches.forEach((match) => {
        const param = match.slice(1, -1) // Remove { }
        if (!/^[a-zA-Z0-9_]+$/.test(param)) {
          invalidKeys.push(key)
        }
      })
    }
  })

  return [...new Set(invalidKeys)]
}

function getInterpolationParams(translations: Record<string, string>): Record<string, string[]> {
  const params: Record<string, string[]> = {}
  const interpolationRegex = /\{([^}]+)\}/g

  Object.entries(translations).forEach(([key, value]) => {
    const matches = value.match(interpolationRegex)
    if (matches) {
      params[key] = matches.map((match) => match.slice(1, -1)).sort()
    }
  })

  return params
}
```

### Component i18n Tests

```typescript
// tests/components/LanguageSwitcher.test.ts
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import LanguageSwitcher from '~/components/LanguageSwitcher.vue'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: {
    en: { test: 'Test' },
    fr: { test: 'Test FR' },
  },
})

describe('LanguageSwitcher', () => {
  it('should display current language', () => {
    const wrapper = mount(LanguageSwitcher, {
      global: {
        plugins: [i18n],
      },
    })

    expect(wrapper.text()).toContain('English')
  })

  it('should switch language when option is selected', async () => {
    const wrapper = mount(LanguageSwitcher, {
      global: {
        plugins: [i18n],
      },
    })

    // Open dropdown
    await wrapper.find('.language-button').trigger('click')

    // Select French
    await wrapper.find('[data-locale="fr"]').trigger('click')

    expect(i18n.global.locale.value).toBe('fr')
  })
})
```

### Date formatting

Dates must always be localized when displayed, using the correct format and locale. For instance:

```typescript
const { d } = useI18n()

const formattedLastUpdate = computed(() => {
  if (!props.lastUpdate) return ''
  return d(props.lastUpdate, 'long')
})
```

Locale should already be set. List of available formats are specified in _i18n/i18n.config.ts_. Only add new format if needed (i.e. 'short', 'full').

## 🔧 Advanced i18n Features

### Custom Translation Directives

```typescript
// plugins/i18n-directives.ts
export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.directive('t', {
    mounted(el, binding) {
      const { t } = useI18n()
      el.textContent = t(binding.value)
    },
    updated(el, binding) {
      const { t } = useI18n()
      el.textContent = t(binding.value)
    },
  })

  nuxtApp.vueApp.directive('t-html', {
    mounted(el, binding) {
      const { t } = useI18n()
      el.innerHTML = t(binding.value)
    },
    updated(el, binding) {
      const { t } = useI18n()
      el.innerHTML = t(binding.value)
    },
  })
})
```

### SEO and Meta Tags

```vue
<!-- pages/index.vue -->
<script setup lang="ts">
const { t, locale } = useI18n()

// SEO meta tags
useSeoMeta({
  title: () => t('pages.home.title'),
  description: () => t('pages.home.description'),
  ogTitle: () => t('pages.home.title'),
  ogDescription: () => t('pages.home.description'),
  ogLocale: () => locale.value,
})

// Structured data
useJsonld({
  '@context': 'https://schema.org',
  '@type': 'WebPage',
  name: () => t('pages.home.title'),
  description: () => t('pages.home.description'),
  inLanguage: () => locale.value,
})
</script>
```

### Translation Caching

```typescript
// plugins/translation-cache.client.ts
export default defineNuxtPlugin(() => {
  const { locale } = useI18n()

  // Cache translations in localStorage
  watch(
    locale,
    async (newLocale) => {
      const cacheKey = `translations_${newLocale}`
      const cached = localStorage.getItem(cacheKey)

      if (!cached) {
        try {
          const translations = await $fetch(`/api/translations/${newLocale}`)
          localStorage.setItem(cacheKey, JSON.stringify(translations))
        } catch (error) {
          console.warn('Failed to cache translations')
        }
      }
    },
    { immediate: true },
  )
})
```

## 📝 Best Practices

### Translation Key Organization (Flat Structure)

1. **Use consistent dot notation** - Follow a clear naming convention like `module.section.key`
2. **Keep keys descriptive** - Use clear, meaningful key names that describe the content
3. **Group logically** - Organize keys by feature/module prefix (e.g., `users.`, `auth.`, `common.`)
4. **Use consistent naming** - Follow camelCase after the dots consistently
5. **Avoid excessive nesting** - Limit to 3-4 levels maximum (e.g., `module.section.subsection.key`)
6. **Standard prefixes** - Use common prefixes like:
   - `common.` for shared elements
   - `errors.` for error messages
   - `validations.` for form validations
   - `navigation.` for menu items
   - `auth.` for authentication
   - `users.` for user management

**Example key naming convention:**

```
common.actions.save
common.labels.firstName
users.form.title
users.messages.created
auth.login.title
errors.page.404.title
validations.required
navigation.dashboard
```

### Migration from Nested to Flat Structure

If you need to migrate from a nested structure to a flat structure, here's a utility:

```typescript
// utils/flatten-translations.ts
export function flattenTranslations(obj: Record<string, any>, prefix = ''): Record<string, string> {
  const flattened: Record<string, string> = {}

  Object.entries(obj).forEach(([key, value]) => {
    const newKey = prefix ? `${prefix}.${key}` : key

    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      Object.assign(flattened, flattenTranslations(value, newKey))
    } else if (typeof value === 'string') {
      flattened[newKey] = value
    }
  })

  return flattened
}

// Usage example
const nestedTranslations = {
  common: {
    actions: {
      save: 'Save',
      cancel: 'Cancel',
    },
  },
}

const flatTranslations = flattenTranslations(nestedTranslations)
// Result: { 'common.actions.save': 'Save', 'common.actions.cancel': 'Cancel' }
```

### Content Guidelines

1. **Write for translation** - Use clear, simple language
2. **Avoid concatenation** - Use interpolation instead of string concatenation
3. **Consider text expansion** - Some languages require more space
4. **Use neutral tone** - Avoid culturally specific references
5. **Provide context** - Add comments for translators when needed

### Performance Optimization

1. **Lazy load translations** - Load only needed translations
2. **Use translation caching** - Cache frequently used translations
3. **Optimize bundle size** - Remove unused translations in production
4. **Implement fallbacks** - Always provide fallback translations
5. **Monitor translation usage** - Track which translations are actually used

### Accessibility Considerations

1. **Support RTL languages** - Consider right-to-left language support
2. **Use proper language attributes** - Set correct lang attributes
3. **Consider font requirements** - Ensure fonts support all character sets
4. **Test with screen readers** - Verify accessibility across languages
5. **Provide language alternatives** - Offer content in multiple languages

This internationalization guide provides a comprehensive foundation for building multilingual applications in the Basil project with proper translation management, testing, and optimization strategies.
