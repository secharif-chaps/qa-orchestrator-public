import { ref, watch, onMounted, onUnmounted, nextTick, readonly } from 'vue'

// Global state - shared across all component instances
const STORAGE_KEY = 'user-theme'
const themes = ['light', 'dark', 'system'] as const
type Theme = (typeof themes)[number]

// Global reactive state
const globalTheme = ref<Theme>('system')
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
const setTheme = (newTheme: Theme) => {
  globalTheme.value = newTheme

  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, newTheme)
    console.log('Theme saved to localStorage:', newTheme)
  }

  // Apply immediately without waiting for nextTick
  updateTheme()

  // Also schedule for nextTick as fallback
  nextTick(() => {
    console.log('Applying theme via nextTick as fallback')
    updateTheme()
  })
}

// Initialize theme on client
const initTheme = () => {
  if (typeof window === 'undefined' || isInitialized) return

  // Load from localStorage or default to system
  const stored = localStorage.getItem(STORAGE_KEY) as Theme | null
  globalTheme.value = stored && themes.includes(stored) ? stored : 'system'

  // Apply initial theme
  updateTheme()

  // Listen for system theme changes
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
