<template>
  <!-- Team Settings Tab -->
  <div class="bg-base-100 p-6 rounded-lg">
    <div class="flex flex-col items-center justify-center py-12 text-center">
      <i class="fa fa-cog text-4xl text-secondary mb-4"></i>
      <h3 class="text-lg font-semibold text-secondary mb-2">
        {{ $t('team.settings.title', 'Team Settings') }}
      </h3>
      <p class="text-secondary">
        {{ $t('team.settings.coming_soon', 'Team settings will be available soon') }}
      </p>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.read
</route>

<script setup lang="ts"></script>
