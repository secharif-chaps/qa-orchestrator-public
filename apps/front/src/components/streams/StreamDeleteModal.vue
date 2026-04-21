<template>
  <div
    v-if="modelValue"
    role="dialog"
    aria-modal="true"
    aria-labelledby="stream-delete-title"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="modelValue = false"
    @keydown.escape="modelValue = false"
  >
    <div class="bg-base-100 mx-4 flex w-full max-w-md flex-col gap-4 rounded-lg p-6 shadow-xl">
      <!-- Header -->
      <div class="flex items-center gap-3">
        <div class="bg-error-light flex h-12 w-12 items-center justify-center rounded-lg">
          <Icon icon="fa-trash" class="text-error-light-content text-xl" />
        </div>
        <div>
          <h3 id="stream-delete-title" class="text-lg font-semibold">
            {{ t('stream.deleteModal.title') }}
          </h3>
        </div>
      </div>

      <!-- Warning Message -->
      <div>
        <p class="text-secondary">
          {{ t('stream.deleteModal.description', { name: streamName }) }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button
          variant="secondary"
          :label="t('stream.form.cancel')"
          :disabled="isDeleting"
          @click="modelValue = false"
        />
        <Button
          variant="primary"
          color="danger"
          :label="t('stream.actions.delete')"
          :loading="isDeleting"
          @click="$emit('confirm')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Icon } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

interface Props {
  streamName: string
  isDeleting: boolean
}

defineProps<Props>()

const modelValue = defineModel<boolean>({ required: true })

defineEmits<{
  confirm: []
}>()

const { t } = useI18n()
</script>
