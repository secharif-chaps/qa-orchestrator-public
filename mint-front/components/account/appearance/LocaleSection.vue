<template>
  <div class="bg-bg1 border border-border-2 rounded-lg">
    <div class="px-6 py-4 border-b border-border-2">
      <h2 class="text-lg font-semibold">{{ $t('account.appearance.language.title') }}</h2>
      <p class="text-sm text-secondary mt-1">{{ $t('account.appearance.language.description') }}</p>
    </div>
    <div class="px-6 py-6">
      <LocaleSwitcher />
    </div>
  </div>
</template>

<script setup lang="ts">
// No props or emits needed - LocaleSwitcher is self-contained
</script>