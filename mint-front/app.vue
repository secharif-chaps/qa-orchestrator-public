<template>
  <div :class="`${theme}`">
    <NuxtLayout > <NuxtPage /> </NuxtLayout>
    {{ theme }}
  </div>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const STORAGE_KEY = 'user-locale'

onMounted(() => {
  if (typeof localStorage !== 'undefined') {
    const savedLocale = localStorage.getItem(STORAGE_KEY)
  if (savedLocale) {
    locale.value = savedLocale
  }
    // Load saved accent color
    const savedAccent = localStorage.getItem('accent-color')
    if (savedAccent) {
      document.documentElement.setAttribute('data-theme', savedAccent)
    }
  }

  
})

useHead({
  bodyAttrs: {
    class: 'bg-bg3 text-slate-900 dark:text-slate-100'
  }
})

const { theme } = useTheme()
</script>
