<template>
  <div class="min-h-screen bg-bg3">
    <div class="container mx-auto px-4 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold">{{ $t('workspace.admin.title') }}</h1>
        <p class="text-secondary mt-2">{{ $t('workspace.admin.description') }}</p>
      </div>

      <!-- Workspace Management Content -->
      <div class="bg-bg1 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold mb-4">{{ $t('workspace.admin.workspaces') }}</h2>
        <p class="text-secondary">This page is protected by the 'admin.workspaces' permission.</p>

        <!-- Add your workspace management UI here -->
        <div class="mt-6">
          <p class="text-sm text-secondary">
            User permissions: {{ userPermissions.join(', ') || 'None' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const userPermissions = authStore.userPermissions
</script>
