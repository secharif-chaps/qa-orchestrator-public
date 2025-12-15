<template>
  <!-- This page is disabled - redirecting to home -->
  <div class="flex flex-col items-center justify-center min-h-[50vh] gap-4">
    <i class="fa fa-users text-6xl text-secondary/30"></i>
    <h2 class="text-xl font-semibold text-secondary">
      {{ $t('team.users.disabled.title', 'Team Management Unavailable') }}
    </h2>
    <p class="text-secondary text-center max-w-md">
      {{ $t('team.users.disabled.description', 'Team user management has been temporarily disabled. Please contact your administrator for user management needs.') }}
    </p>
    <Button
      variant="primary"
      :label="$t('common.goHome', 'Go to Home')"
      @click="router.push('/')"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - organization.read
  title: 'Team Users (Disabled)'
</route>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Button } from '@owlint/feathers-vue'

const router = useRouter()

// Auto-redirect to home after a short delay (optional)
// Uncomment the following to enable auto-redirect:
// import { onMounted } from 'vue'
// onMounted(() => {
//   setTimeout(() => router.push('/'), 2000)
// })
</script>
