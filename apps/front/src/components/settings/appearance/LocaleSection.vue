<template>
  <div class="bg-base-100 border-primary-stroke rounded-card border">
    <div class="border-primary-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.language.title') }}</h2>
      <p class="text-secondary mt-1 text-sm">
        {{ $t('settings.appearance.language.description') }}
      </p>
    </div>
    <div class="px-6 py-6">
      <LocaleSwitcher />
    </div>
  </div>
</template>

<script setup lang="ts">
import LocaleSwitcher from './LocaleSwitcher.vue'
</script>
