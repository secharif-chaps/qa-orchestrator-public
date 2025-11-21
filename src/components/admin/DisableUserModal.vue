<template>
  <div
    class="fixed inset-0 bg-base-100/20 backdrop-blur-sm flex items-center justify-center z-50"
    @click.self="$emit('close')"
  >
    <div
      class="bg-base-100 rounded-xl shadow-2xl border border-primary-stroke p-6 max-w-md w-full mx-4"
    >
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-base">Disable User</h3>
        <Button variant="tertiary" icon="fa fa-times" icon-only @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <div class="mb-6">
        <p class="text-sm text-secondary">
          Are you sure you want to disable <span class="font-semibold">{{ user.username }}</span>?
        </p>
      </div>

      <!-- Warning Alert -->
      <Alert variant="warning" class="mb-6" icon="fa fa-exclamation-triangle">
        <div>
          <p class="font-semibold">This action will:</p>
          <ul class="text-sm mt-1 list-disc list-inside">
            <li>Prevent the user from logging in</li>
            <li>Revoke all active sessions</li>
            <li>Preserve all user data</li>
          </ul>
          <p class="text-sm mt-2">You can re-enable the user later if needed.</p>
        </div>
      </Alert>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button variant="secondary" @click="$emit('close')"> Cancel </Button>
        <Button variant="accent" :loading="isLoading" @click="$emit('confirm')">
          Disable User
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { AdminUserResponse } from '@/types/admin-user'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'

defineProps<{
  user: AdminUserResponse
  isLoading?: boolean
}>()

defineEmits<{
  confirm: []
  close: []
}>()
</script>
