<template>
  <div class="border-primary-lighter-stroke rounded-card border bg-white">
    <div class="border-primary-lighter-stroke border-b px-6 py-4">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.language.title') }}</h2>
      <p class="text-neutral-black-font mt-1 text-sm">
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
