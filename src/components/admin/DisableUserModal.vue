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
        <h3 class="text-lg font-semibold text-base">{{ t('admin.disableUser.title', 'Disable User') }}</h3>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <div class="mb-6">
        <p class="text-sm text-secondary">
          {{ t('admin.disableUser.confirmText', 'Are you sure you want to disable') }} <span class="font-semibold">{{ username }}</span>?
        </p>
      </div>

      <!-- Warning Alert -->
      <Alert
        variant="warning"
        class="mb-6"
        icon="fa-exclamation-triangle"
        :title="t('admin.disableUser.actionTitle', 'This action will:')"
        :description="t('admin.disableUser.actionDescription', '• Prevent the user from logging in • Revoke all active sessions • Preserve all user data. You can re-enable the user later if needed.')"
      />

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button variant="secondary" :label="t('admin.disableUser.cancel', 'Cancel')" @click="$emit('close')" />
        <Button variant="accent" :label="t('admin.disableUser.confirm', 'Disable User')" :loading="isLoading" @click="$emit('confirm')" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Alert, Button } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  userId: string
  username: string
  isLoading?: boolean
}>()

defineEmits<{
  confirm: []
  close: []
}>()
</script>
