<template>
  <div class="min-h-screen flex items-center justify-center bg-base-300">
    <div class="text-center max-w-md px-4">
      <div class="mb-8">
        <h1 class="text-6xl font-bold text-primary-light-content">404</h1>
        <h2 class="text-2xl font-semibold mt-4">
          {{ $t('errors.notFound.title', 'Page Not Found') }}
        </h2>
        <p class="text-primary-light-content mt-2">
          {{
            $t(
              'errors.notFound.message',
              "The page you're looking for doesn't exist or has been moved.",
            )
          }}
        </p>
      </div>

      <div class="space-x-4">
        <Button
          :label="$t('errors.notFound.goHome', 'Go to Home')"
          variant="primary"
          @click="$router.push('/')"
        />
        <Button
          :label="$t('errors.notFound.goBack', 'Go Back')"
          variant="secondary"
          @click="$router.back()"
        />
      </div>

      <div class="mt-8 text-sm text-primary-light-content">
        <p>
          {{
            $t(
              'errors.notFound.help',
              'If you believe this page should exist, please contact support.',
            )
          }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue'
</script>

<route lang="yaml">
meta:
  requiresAuth: false
  title: 'Page Not Found'
</route>
