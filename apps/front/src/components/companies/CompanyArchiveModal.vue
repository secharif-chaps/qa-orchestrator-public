<template>
  <Modal
    v-model:display-modal="showArchiveModal"
    :title="t('company.archive.title')"
    icon="fa fa-box-archive"
    size="lg"
    color=""
  >
    <template #description>
      {{ t('company.archive.subtitle') }}
    </template>

    <div v-if="companyToArchive" class="flex flex-col gap-4">
      <p class="text-secondary text-sm">
        {{ t('company.archive.warning.message') }}
      </p>

      <!-- Company Details -->
      <div class="bg-base-200 rounded-lg p-4">
        <h4 class="mb-3 text-base font-medium">
          {{ t('company.archive.details') }}
        </h4>
        <div class="flex flex-col gap-2 text-sm">
          <div class="flex justify-between">
            <span class="text-secondary">{{ t('company.name') }}:</span>
            <span class="font-medium">{{ companyToArchive.name }}</span>
          </div>
          <div v-if="companyToArchive.website" class="flex justify-between">
            <span class="text-secondary">{{ t('company.website') }}:</span>
            <span class="text-xs">{{ companyToArchive.website }}</span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button
        variant="primary"
        intent="danger"
        icon="fa fa-box-archive"
        :label="t('company.archive.confirm.button')"
        :loading="isLoading"
        :disabled="isLoading"
        @click="handleArchive"
      />
      <Button variant="tertiary" :label="t('common.cancel')" @click="showArchiveModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Modal } from '@owlint/feathers-vue'
import { useI18n } from 'vue-i18n'
import type { Company } from '@/types/company'
import { useArchiveCompany } from '@/mutations/companies'

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
