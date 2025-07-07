<template>
  <div class="space-y-6">
    <!-- Language Settings -->
    <AccountAppearanceLocaleSection />

    <!-- Theme Settings -->
    <AccountAppearanceThemeSection 
      :current-theme="currentTheme"
      @theme-change="handleThemeChange"
    />

    <!-- Accent Color Settings -->
    <AccountAppearanceAccentColorSection 
      :current-accent="currentAccent"
      @accent-change="handleAccentChange"
    />

    <!-- Theme Preview -->
    <AccountAppearancePreviewSection />
  </div>
</template>

<script setup lang="ts">

const { theme: currentTheme, setTheme } = useTheme()

// Accent color management
const currentAccent = ref('indigo')

const handleThemeChange = (themeValue: string) => {
  console.log('Theme change requested:', themeValue)
  setTheme(themeValue as 'light' | 'dark' | 'system')
  console.log('New theme set:', currentTheme.value)
}

// Accent color methods
const handleAccentChange = (accentValue: string) => {
  currentAccent.value = accentValue
  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', accentValue)
  }
  
  // Persist to localStorage
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('accent-color', accentValue)
  }
}

// Load saved accent color from localStorage
onMounted(() => {
  if (typeof localStorage !== 'undefined') {
    // Load saved accent color
    const savedAccent = localStorage.getItem('accent-color')
    if (savedAccent) {
      currentAccent.value = savedAccent
      document.documentElement.setAttribute('data-theme', savedAccent)
    }
  }
})

definePageMeta({
  title: 'Appearance Settings'
})
</script>