<template>
  <div class="min-h-screen flex items-center justify-center bg-surface">
    <div class="text-center">
      <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-primary mx-auto mb-4"></div>
      <h2 class="text-xl font-semibold text-foreground mb-2">{{ t('auth.loader.title') }}</h2>
      <p class="text-secondary">{{ t('auth.loader.message') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
