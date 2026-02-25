<template>
  <div
    class="shadow-3 border-sage-100 flex h-full flex-col gap-4 rounded-xl border pr-3"
    :class="{
      'p-6': !hasNoDocuments,
      'pl-3': hasNoDocuments || error,
    }"
  >
    <DocumentListHeader
      v-model:select-all="selectAll"
      :is-disabled="headerIsDisabled"
      :is-hidden="headerIsHidden"
      :selected-documents="selectedDocuments"
      :is-batch-processing="isBatchProcessing"
      @search="search"
      @validate="validateSelectedDocuments"
      @reject="rejectSelectedDocuments"
    />

    <ErrorMessage
      v-if="error"
      :title="$t('common.error.title.list')"
      width="full"
      style="margin: 1rem"
    />
    <EmptyState
      v-else-if="hasNoDocuments && (filtersCounts || searchQuery)"
      :title="$t('documents.empty.title')"
      :description="$t('documents.empty.description')"
      icon="fa-magnifying-glass"
      vertical-align="center"
    />
    <InformationMessage
      v-else-if="hasNoDocuments && !isWatchFileActive"
      :title="$t('documents.inactive.title')"
      :description="$t('documents.inactive.subtitle')"
      color="error"
      width="full"
    />
    <InformationMessage
      v-else-if="hasNoDocuments && isWatchFileActive"
      :title="$t('documents.loading.title')"
      :description="$t('documents.loading.subtitle')"
      color="info"
      icon="fa-spinner"
      vertical-align="center"
    />

    <template v-else>
      <div v-if="!isLoading && searchQuery" class="flex items-center gap-4">
        <p class="text-sm font-bold">
          {{ t('watch_files.documents.search.nb_results', { nb: totalItems }, totalItems) }}
        </p>
        <Button variant="secondary" size="sm" icon="fa-xmark" @click="searchQuery = ''">
          {{ $t('watch_files.documents.search.delete') }}
        </Button>
      </div>
      <div class="scrollable min-h-0 flex-1">
        <DocumentListSkeleton v-if="isLoading" />

        <template v-else>
          <div class="overflow-hidden rounded-lg border border-gray-200">
            <DocumentItem
              v-for="document in documents"
              :key="document.id"
              v-model:selected-documents="selectedDocuments"
              :selected-document-id="selectedDocumentId"
              :document="document"
              :is-batch-processing="isBatchProcessing"
              @click="handleDocumentClick(document)"
            />
          </div>
        </template>
      </div>

      <div class="shrink-0 rounded bg-white px-6 py-2 shadow-2xl">
        <div v-if="isLoading" class="flex items-center justify-between">
          <div class="h-4 animate-pulse rounded bg-gray-200" style="width: 200px"></div>
          <div class="flex items-center gap-2">
            <div class="h-8 w-8 animate-pulse rounded bg-gray-200"></div>
            <div class="h-8 w-8 animate-pulse rounded bg-gray-200"></div>
          </div>
        </div>

        <Pagination
          v-else
          v-model:current-page="currentPage"
          v-model:items-per-pages="itemsPerPage"
          :total="totalItems"
        >
          <template #result>
            {{ $t('documents.pagination.items_per_page') }}
          </template>
        </Pagination>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { Button, Pagination, type CheckboxType } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { useBatchDocumentValidation } from '@target/api/mutations/document'
import { getItemWatchFileQuery } from '@target/api/queries/watchFile'
import DocumentListHeader from '@target/components/documents/DocumentListHeader.vue'
import ErrorMessage from '@target/components/global/ErrorMessage.vue'
import InformationMessage from '@target/components/global/InformationMessage.vue'
import DocumentListSkeleton from '@target/components/skeletons/DocumentListSkeleton.vue'
import { useConfirmModal } from '@target/composables/useConfirmModal'
import { useWatchFileDocumentsStore } from '@target/stores/watchFileDocuments'
import type { Document } from '@target/types/document'
import { DocumentValidationAction } from '@target/types/document'
import { WATCH_FILE_STATUS } from '@target/types/watchFile'
import { storeToRefs } from 'pinia'
import { computed, onMounted, onUnmounted, ref, watch, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../global/EmptyState.vue'
import DocumentItem from './DocumentItem.vue'

const { t } = useI18n()
const { showConfirmModal } = useConfirmModal()

interface Props {
  documents: Document[]
  totalItems: number
  error?: string
  selectedDocumentId?: string
  isLoading: boolean
  watchFileId: string
}

const {
  documents,
  error = undefined,
  selectedDocumentId = undefined,
  isLoading,
  watchFileId,
} = defineProps<Props>()

const { data: watchFileData } = useQuery(getItemWatchFileQuery, () => ({
  id: watchFileId,
}))

const isWatchFileActive = computed(() => watchFileData.value?.status === WATCH_FILE_STATUS.ENABLED)

const emit = defineEmits<{
  (e: 'select', document: Document): void
  (e: 'search'): void
}>()

const watchFileDocumentsStore = useWatchFileDocumentsStore()
const { searchQuery, currentPage, itemsPerPage } = storeToRefs(watchFileDocumentsStore)

const filtersCounts = computed(() => watchFileDocumentsStore.filtersCounts)

const selectAll = ref<CheckboxType>(false)
const selectedDocuments = ref<string[]>([])

const isBatchProcessing = ref(false)

const hasNoDocuments = computed(() => !isLoading && !documents.length)

const headerIsHidden = computed(() => isLoading || !documents.length || !!error)

const headerIsDisabled = computed(() => isLoading || isBatchProcessing.value)

const toggleSelectAll = (checked: CheckboxType) => {
  if (checked === true) {
    selectedDocuments.value = documents.map((doc) => doc.id)
  } else if (checked === false) {
    selectedDocuments.value = []
  }
}

watch(selectAll, (newValue) => {
  toggleSelectAll(newValue)
})

watchEffect(() => {
  if (selectedDocuments.value.length === documents.length) {
    selectAll.value = true
  } else if (!selectedDocuments.value.length) {
    selectAll.value = false
  } else {
    selectAll.value = 'indeterminate'
  }
})

watch(currentPage, () => {
  selectedDocuments.value = []
  selectAll.value = false
})

const search = () => {
  emit('search')
  currentPage.value = 1
}

const handleDocumentClick = (doc: Document) => {
  emit('select', doc)
}

const { batchToggleDocumentStatus } = useBatchDocumentValidation({
  onSuccess: () => {
    // Reset selection after successful batch operation
    selectedDocuments.value = []
    selectAll.value = false
    isBatchProcessing.value = false
  },
  onError: () => {
    isBatchProcessing.value = false
  },
})

const validateSelectedDocuments = () => {
  if (selectedDocuments.value.length === 0) return

  const count = selectedDocuments.value.length

  showConfirmModal({
    title: t('watch_files.documents.batch_validate.modal.title'),
    message: t('watch_files.documents.batch_validate.modal.message', { count }, count),
    confirmLabel: t('common.button.confirm'),
    cancelLabel: t('common.button.cancel'),
    onConfirm: () => {
      isBatchProcessing.value = true
      batchToggleDocumentStatus({
        watchFileId,
        documentIds: selectedDocuments.value,
        action: DocumentValidationAction.ACCEPT,
      })
    },
  })
}

const rejectSelectedDocuments = () => {
  if (selectedDocuments.value.length === 0) return

  const count = selectedDocuments.value.length

  showConfirmModal({
    title: t('watch_files.documents.batch_reject.modal.title'),
    message: t('watch_files.documents.batch_reject.modal.message', { count }, count),
    confirmLabel: t('common.button.confirm'),
    cancelLabel: t('common.button.cancel'),
    onConfirm: () => {
      isBatchProcessing.value = true
      batchToggleDocumentStatus({
        watchFileId,
        documentIds: selectedDocuments.value,
        action: DocumentValidationAction.REFUSE,
      })
    },
  })
}

const handleKeydown = (event: KeyboardEvent) => {
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
    return
  }

  if (event.ctrlKey && event.key === 'a') {
    event.preventDefault()
    if (!isLoading && !error && documents.length > 0) {
      toggleSelectAll(true)
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>
