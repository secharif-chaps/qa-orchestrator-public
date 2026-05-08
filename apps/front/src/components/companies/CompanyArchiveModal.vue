<template>
  <Modal
    v-model:display-modal="showArchiveModal"
    :title="t('screen.company.delete.title')"
    icon="fa fa-trash"
    size="lg"
    color=""
  >
    <template #description>
      {{ t('screen.company.delete.subtitle') }}
    </template>

    <div v-if="companyToArchive" class="flex flex-col gap-4">
      <p class="text-neutral-black-font text-sm">
        {{ t('screen.company.archive.warning.message') }}
      </p>

      <!-- Company Details -->
      <div class="bg-primary-lightest rounded-sm p-4">
        <h4 class="mb-3 text-base font-medium">
          {{ t('screen.company.delete.details') }}
        </h4>
        <div class="flex flex-col gap-2 text-sm">
          <div class="flex justify-between">
            <span class="text-neutral-black-font">{{
              t('screen.company.detailsLabels.name')
            }}</span>
            <span class="font-medium">{{ companyToArchive.name }}</span>
          </div>
          <div v-if="companyToArchive.website" class="flex justify-between">
            <span class="text-neutral-black-font">{{
              t('screen.company.detailsLabels.website')
            }}</span>
            <span class="text-xs">{{ companyToArchive.website }}</span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button
        variant="primary"
        intent="danger"
        icon="fa fa-trash"
        :label="t('screen.company.delete.confirm.button')"
        :loading="isLoading"
        :disabled="isLoading"
        @click="handleArchive"
      />
      <Button variant="tertiary" :label="t('common.cancel')" @click="showArchiveModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { useArchiveCompany } from '@/mutations/companies'
import type { Company } from '@/types/company'
import { Button, Modal } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  companyToArchive?: Company | null
}

const props = defineProps<Props>()

const showArchiveModal = defineModel<boolean>({
  required: true,
  default: false,
})

const emit = defineEmits<{
  'archive-company': []
}>()

// Use mutation for archiving with cache invalidation
const { archiveCompany, isLoading } = useArchiveCompany()

const handleArchive = async () => {
  if (!props.companyToArchive?.id) return

  try {
    await archiveCompany({
      companyId: props.companyToArchive.id.toString(),
      companyName: props.companyToArchive.name,
    })

    // Emit event for parent
    emit('archive-company')

    // Close modal
    showArchiveModal.value = false
  } catch (error) {
    // Error toast is shown by the mutation's onError handler
    console.error('Error archiving company:', error)
  }
}
</script>
