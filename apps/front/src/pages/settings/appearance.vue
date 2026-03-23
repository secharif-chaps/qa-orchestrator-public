<template>
  <div class="flex flex-col gap-6">
    <!-- Language Settings -->
    <LocaleSection />

    <!-- Theme Settings -->
    <!-- TEMPORARY Dark mode disabled, waiting for designs -->
    <ThemeSection v-if="false" :current-theme="currentTheme" @theme-change="handleThemeChange" />
  </div>
</template>

<script setup lang="ts">
import LocaleSection from '@/components/settings/appearance/LocaleSection.vue'
import ThemeSection from '@/components/settings/appearance/ThemeSection.vue'
import { useTheme } from '@/composables/useTheme'

const { theme: currentTheme, setTheme } = useTheme()

function handleThemeChange(themeValue: string) {
  setTheme(themeValue as 'light' | 'dark' | 'system')
}
</script>
