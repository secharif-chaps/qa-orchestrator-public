<template>
  <div class="space-y-6">
    <!-- Language Settings -->
    <LocaleSection />

    <!-- Theme Settings -->
    <ThemeSection :current-theme="currentTheme" @theme-change="handleThemeChange" />
  </div>
</template>

<script setup lang="ts">
import LocaleSection from '@/components/settings/appearance/LocaleSection.vue'
import ThemeSection from '@/components/settings/appearance/ThemeSection.vue'
import { useTheme } from '@/composables/useTheme'

const { theme: currentTheme, setTheme } = useTheme()

const handleThemeChange = (themeValue: string) => {
  setTheme(themeValue as 'light' | 'dark' | 'system')
}
</script>
