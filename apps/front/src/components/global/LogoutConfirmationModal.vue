<template>
  <Modal
    v-model:display-modal="showModal"
    :title="t('logout.title')"
    icon="fa fa-arrow-right-from-bracket"
    size="md"
    color=""
  >
    <template #description>
      {{ t('logout.subtitle') }}
    </template>

    <div class="flex flex-col gap-4">
      <p class="text-secondary text-sm">
        {{ t('logout.message') }}
      </p>
    </div>

    <template #footer>
      <Button
        variant="primary"
        intent="danger"
        icon="fa fa-arrow-right-from-bracket"
        :label="t('logout.confirm')"
        :loading="isLoading"
        :disabled="isLoading"
        @click="$emit('confirm')"
      />
      <Button variant="tertiary" :label="t('logout.cancel')" @click="showModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Modal } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps<{
  isLoading?: boolean
}>()

const showModal = defineModel<boolean>({
  required: true,
  default: false,
})

defineEmits<{
  confirm: []
  close: []
}>()
</script>
