<template>
  <Modal
    v-model:display-modal="showModal"
    :title="t('common.logout.title')"
    icon="fa fa-arrow-right-from-bracket"
    size="md"
    color=""
  >
    <template #description>
      {{ t('common.logout.subtitle') }}
    </template>

    <div class="flex flex-col gap-4">
      <p class="text-neutral-black-font text-sm">
        {{ t('common.logout.message') }}
      </p>
    </div>

    <template #footer>
      <Button
        variant="primary"
        intent="danger"
        icon="fa fa-arrow-right-from-bracket"
        :label="t('common.logout.confirm')"
        :loading="isLoading"
        :disabled="isLoading"
        @click="$emit('confirm')"
      />
      <Button variant="tertiary" :label="t('common.logout.cancel')" @click="showModal = false" />
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
