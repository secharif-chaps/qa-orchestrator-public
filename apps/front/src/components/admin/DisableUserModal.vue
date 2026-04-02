<template>
  <div
    class="bg-base-100/20 fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm"
    @click.self="$emit('close')"
  >
    <div
      class="bg-base-100 border-primary-stroke mx-4 w-full max-w-md rounded-xl border p-6 shadow-2xl"
    >
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <h3 class="text-base text-lg font-semibold">
          {{ t('admin.disableUser.title') }}
        </h3>
        <Button variant="tertiary" icon="fa fa-times" @click="$emit('close')" />
      </div>

      <!-- User Info -->
      <div class="mb-6">
        <p class="text-secondary text-sm">
          {{ t('admin.disableUser.confirmText') }}
          <span class="font-semibold">{{ username }}</span
          >?
        </p>
      </div>

      <!-- Warning Alert -->
      <Alert
        variant="warning"
        class="mb-6"
        icon="fa-exclamation-triangle"
        :title="t('admin.disableUser.actionTitle')"
        :description="t('admin.disableUser.actionDescription')"
      />

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :label="t('admin.disableUser.cancel')"
          @click="$emit('close')"
        />
        <Button
          variant="accent"
          :label="t('admin.disableUser.confirm')"
          :loading="isLoading"
          @click="$emit('confirm')"
        />
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
