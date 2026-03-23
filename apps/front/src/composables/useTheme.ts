import { onMounted, onUnmounted, readonly, ref, watch } from 'vue'

// Global state - shared across all component instances
const STORAGE_KEY = 'user-theme'
const themes = ['light', 'dark', 'system'] as const
type Theme = (typeof themes)[number]

// Global reactive state
// Default have to be system when design is be ready
const globalTheme = ref<Theme>('light')
const globalIsDark = ref(false)
let isInitialized = false

// Get system preference
const getSystemTheme = (): 'light' | 'dark' => {
  if (typeof window === 'undefined') return 'light'
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

// Apply theme to document
const applyTheme = (targetTheme: 'light' | 'dark') => {
  if (typeof document === 'undefined') return

  const root = document.documentElement

  if (targetTheme === 'dark') {
    root.classList.add('dark')
    globalIsDark.value = true
  } else {
    root.classList.remove('dark')
    globalIsDark.value = false
  }

  // Apply CSS custom properties for smooth transitions
  root.style.setProperty('--theme-transition', 'background-color 0.2s ease, color 0.2s ease')
}

// Update theme based on current selection
const updateTheme = () => {
  const targetTheme = globalTheme.value === 'system' ? getSystemTheme() : globalTheme.value
  applyTheme(targetTheme)
}

// Set theme and persist to localStorage
// TEMPORARY: Force light theme until dark mode designs are delivered.
// When ready, remove the early return below.
const setTheme = (newTheme: Theme) => {
  // Dark mode not yet designed — ignore theme changes
  if (newTheme !== 'light') return

  globalTheme.value = newTheme

  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, newTheme)
  }

  updateTheme()
}

// Initialize theme on client
// TEMPORARY: Force light theme for all users until dark mode designs are delivered.
// When dark mode mockups are ready, restore localStorage logic:
//   const stored = localStorage.getItem(STORAGE_KEY) as Theme | null
//   globalTheme.value = stored && themes.includes(stored) ? stored : 'system'
const initTheme = () => {
  if (typeof window === 'undefined' || isInitialized) return

  // Force light theme — dark mode designs not yet available
  globalTheme.value = 'light'

  // Apply initial theme
  updateTheme()

  // Listen for system theme changes (will be useful when 'system' is re-enabled)
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  const handleSystemThemeChange = () => {
    if (globalTheme.value === 'system') {
      updateTheme()
    }
  }

  mediaQuery.addEventListener('change', handleSystemThemeChange)

  isInitialized = true
}

// Watch theme changes globally
watch(
  globalTheme,
  () => {
    if (isInitialized) {
      updateTheme()
    }
  },
  { immediate: false },
)

export const useTheme = () => {
  // Initialize on first use in client
  if (!isInitialized) {
    initTheme()
  }

  // Initialize on mounted for SSR compatibility
  onMounted(() => {
    if (!isInitialized) {
      initTheme()
    }
  })

  // Cleanup on unmount (only for the last component)
  onUnmounted(() => {
    // Only cleanup if no other components are using this
    // In practice, this is rarely needed since theme is global
  })

  return {
    theme: readonly(globalTheme),
    isDark: readonly(globalIsDark),
    themes,
    setTheme,
    getSystemTheme,
  }
}
