<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h2 class="text-lg font-semibold">{{ $t('settings.appearance.language.title') }}</h2>
      <p class="text-sm text-secondary mt-1">
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

// No props or emits needed - LocaleSwitcher is self-contained
</script>
