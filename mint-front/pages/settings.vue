<template>
  <div class="max-w-7xl mx-auto">
    <Card :title="$t('settings.title')">
      <div class="space-y-6">
        <div>
          <h3 class="text-xl font-semibold mb-2" data-testid="language-title">{{ $t('settings.language.title') }}</h3>
          <p class="text-gray-600 mb-4" data-testid="language-description">{{ $t('settings.language.description') }}</p>
          <LocaleSwitcher />
        </div>

        <div>
          <h3 class="text-xl font-semibold mb-2" data-testid="theme-title">{{ $t('settings.theme.title') }}</h3>
          <p class="text-gray-600 mb-4" data-testid="theme-description">{{ $t('settings.theme.description') }}</p>
        </div>

        <div>
          <h3 class="text-xl font-semibold mb-2" data-testid="notifications-title">{{ $t('settings.notifications.title') }}</h3>
          <p class="text-gray-600 mb-4" data-testid="notifications-description">{{ $t('settings.notifications.description') }}</p>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
.settings-section {
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.settings-section:last-child {
  border-bottom: none;
}
</style>
