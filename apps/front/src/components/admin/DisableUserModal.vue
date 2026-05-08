<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-white/20 backdrop-blur-sm"
    @click.self="$emit('close')"
  >
    <div
      class="border-primary-lighter-stroke mx-4 w-full max-w-112 rounded-xl border bg-white p-6 shadow-2xl"
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
        <p class="text-neutral-black-font text-sm">
          <i18n-t scope="global" keypath="admin.disableUser.confirmText" tag="span">
            <template #username>
              <span class="font-semibold">{{ username }}</span>
            </template>
          </i18n-t>
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
