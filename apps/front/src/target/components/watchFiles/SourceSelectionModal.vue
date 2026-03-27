<template>
  <Modal v-model:display-modal="isOpen" size="7xl" @close="closeModal">
    <template #title>
      <div class="flex w-full items-center justify-between">
        <span class="text-lg text-gray-900">
          {{ $t('target.watchFiles.sources.selection_modal.title') }}
        </span>
        <button
          class="flex cursor-pointer items-center p-1 text-gray-500 transition-colors hover:text-gray-700"
          @click="closeModal"
        >
          <Icon icon="fa-xmark" class="h-5 w-5" />
        </button>
      </div>
    </template>

    <template #description>
      <div class="relative -mt-2 h-[620px] overflow-hidden">
        <SourcesListTable
          v-if="watchFileId"
          v-model:selected-sources="selectedSources"
          :watch-file-id="watchFileId"
          :in-modal="true"
          :with-filters="true"
          :readonly="false"
          :batch-selection="true"
          :active="false"
        />
      </div>
    </template>

    <template #footer>
      <div class="flex flex-row-reverse gap-3">
        <Button v-if="selectedSources.length > 0" color="primary" @click="confirmSelection">
          {{
            $t('target.watchFiles.sources.selection_modal.add_sources', {
              count: selectedSources.length,
            })
          }}
        </Button>
        <Button variant="tertiary" @click="closeModal">
          {{ $t('common.button.cancel') }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { Button, Icon, Modal } from '@owlint/feathers-vue'
import { useBatchChangeSourceStatus } from '@target/api/mutations/sources'
import { SourceStatus } from '@target/types/source'
import { ref } from 'vue'
import SourcesListTable from './SourcesListTable.vue'

interface Props {
  watchFileId?: string
}

const { watchFileId = undefined } = defineProps<Props>()

const isOpen = defineModel<boolean>('isOpen', {
  required: true,
})

const selectedSources = ref<string[]>([])
const { batchChangeStatus } = useBatchChangeSourceStatus()

const closeModal = () => {
  isOpen.value = false
  selectedSources.value = []
}

const confirmSelection = async () => {
  if (!watchFileId) {
    console.error('No watchFileId provided')
    return
  }

  if (selectedSources.value.length === 0) {
    closeModal()
    return
  }

  await batchChangeStatus({
    watchFileId,
    sources: selectedSources.value.map((id) => ({ id })),
    status: SourceStatus.ACTIVE,
  })
  closeModal()
}
</script>
