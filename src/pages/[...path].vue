<template>
  <div class="flex min-h-screen items-center justify-center">
    <div class="max-w-md px-4 text-center">
      <div class="mb-8">
        <h1 class="text-secondary text-6xl font-bold">404</h1>
        <h2 class="mt-4 text-2xl font-semibold">
          {{ $t('errors.notFound.title', 'Page Not Found') }}
        </h2>
        <p class="text-secondary mt-2">
          {{
            $t(
              'errors.notFound.message',
              "The page you're looking for doesn't exist or has been moved.",
            )
          }}
        </p>
      </div>

      <div class="flex space-x-4">
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

      <div class="text-secondary mt-8 text-sm">
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
import { Button } from '@owlint/feathers-vue'
</script>

<route lang="yaml">
meta:
  requiresAuth: false
  title: 'Page Not Found'
</route>
