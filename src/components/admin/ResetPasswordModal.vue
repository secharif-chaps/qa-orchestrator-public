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
        <Button variant="tertiary" icon="fa fa-times" icon-only @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <div class="mb-6">
        <p class="text-sm text-secondary">
          Reset password for <span class="font-semibold">{{ user.username }}</span>
        </p>
      </div>

      <!-- Info Alert -->
      <Alert variant="info" class="mb-6" icon="fa fa-info-circle">
        <div>
          <p class="text-sm">
            A password reset email will be sent to
            <span class="font-semibold">{{ user.email }}</span>
          </p>
          <p class="text-sm mt-1">
            The user will be required to change their password on next login.
          </p>
        </div>
      </Alert>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button variant="secondary" @click="$emit('close')"> Cancel </Button>
        <Button variant="primary" :loading="isLoading" @click="$emit('confirm')">
          Send Reset Email
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
