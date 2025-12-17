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
        <h3 class="text-lg font-semibold text-base">Reset Password</h3>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <div class="mb-6">
        <p class="text-sm text-secondary">
          Reset password for <span class="font-semibold">{{ user.username }}</span>
        </p>
      </div>

      <!-- Info Alert -->
      <Alert
        variant="info"
        class="mb-6"
        icon="fa-info-circle"
        :title="`Password reset email will be sent to ${user.email}`"
        description="The user will be required to change their password on next login."
      />

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button variant="secondary" label="Cancel" @click="$emit('close')" />
        <Button variant="primary" label="Send Reset Email" :loading="isLoading" @click="$emit('confirm')" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { AdminUserResponse } from '@/types/admin-user'
import { Alert, Button } from '@owlint/feathers-vue'

defineProps<{
  user: AdminUserResponse
  isLoading?: boolean
}>()

defineEmits<{
  confirm: []
  close: []
}>()
</script>
