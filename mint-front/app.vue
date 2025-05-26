<template>
  <NuxtLayout name="main"> <NuxtPage /> </NuxtLayout>
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
</script>
