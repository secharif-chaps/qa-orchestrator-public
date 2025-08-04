<template>
  <div class="min-h-screen flex items-center justify-center bg-bg3">
    <div class="text-center">
      <div class="mb-8">
        <h1 class="text-6xl font-bold text-primary">403</h1>
        <h2 class="text-2xl font-semibold mt-4">
          {{ $t('errors.forbidden.title', 'Access Forbidden') }}
        </h2>
        <p class="text-secondary mt-2">
          {{ $t('errors.forbidden.message', "You don't have permission to access this page.") }}
        </p>
      </div>

      <div class="space-x-4">
        <button
          @click="$router.push('/')"
          class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/80 transition-colors"
        >
          {{ $t('errors.forbidden.goHome', 'Go to Home') }}
        </button>
        <button
          @click="$router.back()"
          class="bg-bg2 text-base px-6 py-2 rounded-lg hover:bg-bg1 transition-colors"
        >
          {{ $t('errors.forbidden.goBack', 'Go Back') }}
        </button>
      </div>

      <div class="mt-8 text-sm text-secondary">
        <p>
          {{
            $t(
              'errors.forbidden.contact',
              'If you believe this is an error, please contact your administrator.',
            )
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// No special setup needed for 403 page
</script>
