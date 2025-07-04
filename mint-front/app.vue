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
  // Load saved locale on app initialization
  const savedLocale = localStorage.getItem(STORAGE_KEY)
  if (savedLocale) {
    locale.value = savedLocale
  }
})

useHead({
  bodyAttrs: {
    class: 'bg-bg3 dark:bg-slate-900 text-slate-900 dark:text-slate-100'
  }
})

const { theme } = useTheme()
</script>
