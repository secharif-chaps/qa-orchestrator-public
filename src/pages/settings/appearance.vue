<template>
  <div class="space-y-6">
    <!-- Language Settings -->
    <LocaleSection />

    <!-- Theme Settings -->
    <ThemeSection :current-theme="currentTheme" @theme-change="handleThemeChange" />

    <!-- Accent Color Settings -->
    <AccentColorSection :current-accent="currentAccent" @accent-change="handleAccentChange" />

    <!-- Theme Preview -->
    <PreviewSection />
  </div>
</template>

<script setup lang="ts">
import LocaleSection from '@/components/settings/appearance/LocaleSection.vue'
import ThemeSection from '@/components/settings/appearance/ThemeSection.vue'
import PreviewSection from '@/components/settings/appearance/PreviewSection.vue'
import AccentColorSection from '@/components/settings/appearance/AccentColorSection.vue'
import { useTheme } from '@/composables/useTheme'
import { ref, onMounted } from 'vue'

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
</script>
