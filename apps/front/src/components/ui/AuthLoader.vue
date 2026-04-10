<template>
  <div class="bg-surface flex min-h-screen items-center justify-center">
    <div class="text-center">
      <div class="border-primary mx-auto mb-4 h-16 w-16 animate-spin rounded-full border-b-2"></div>
      <h2 class="text-foreground mb-2 text-xl font-semibold">
        {{ t('common.auth.loader.title') }}
      </h2>
      <p class="text-neutral-black-font">{{ t('common.auth.loader.message') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
