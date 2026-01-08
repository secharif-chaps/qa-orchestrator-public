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
      <p class="text-sm text-secondary">
        {{ t('company.archive.warning.message') }}
      </p>

      <!-- Company Details -->
      <div class="bg-base-200 rounded-lg p-4">
        <h4 class="font-medium text-base mb-3">
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
        :loading="archiveLoading"
        :disabled="archiveLoading"
        @click="archiveCompany"
      />
      <Button variant="tertiary" :label="t('common.cancel')" @click="showArchiveModal = false" />
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Modal } from '@owlint/feathers-vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQueryCache } from '@pinia/colada'
import type { Company } from '@/types/company'
import { deleteCompany as apiArchiveCompany } from '@/api/companies'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'
import { toast } from '@/utils/toast'

const { t } = useI18n()
const queryCache = useQueryCache()

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

const archiveLoading = ref(false)

const archiveCompany = async () => {
  if (!props.companyToArchive?.id) return

  archiveLoading.value = true

  try {
    await apiArchiveCompany(props.companyToArchive.id.toString())

    // Invalidate folder queries to refresh sidebar
    queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

    // Show success toast
    toast.success(
      t('company.archive.success', 'Company "{name}" has been archived successfully', {
        name: props.companyToArchive.name,
      }),
    )

    // Emit event first, then clean up
    emit('archive-company')

    // Small delay to ensure parent component processes the event
    setTimeout(() => {
      showArchiveModal.value = false
    }, 50)
  } catch (err) {
    console.error('Failed to archive company:', err)
    // Show error toast
    toast.error(
      t('company.archive.error', 'Failed to archive company "{name}". Please try again.', {
        name: props.companyToArchive.name,
      }),
    )
  } finally {
    archiveLoading.value = false
  }
}
</script>
