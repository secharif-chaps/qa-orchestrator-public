<template>
  <div class="flex min-h-[32px] shrink-0 items-center gap-8 bg-white">
    <div v-if="!isHidden" class="flex w-full items-center justify-between gap-4">
      <!-- Select all checkbox -->
      <label class="flex items-center gap-2">
        <Checkbox
          id="select-all-documents"
          v-model="selectAll"
          :value="true"
          :disabled="isDisabled"
        />
        <span class="text-sm text-gray-700">
          {{ $t('common.action.selectAll') }}
          <span v-if="hasSelectedDocuments">
            {{
              $t('common.action.selectedCount', {
                count: selectedDocuments.length,
              })
            }}
          </span>
        </span>
      </label>

      <!-- Document validation buttons -->
      <div class="relative flex items-center gap-3">
        <div
          v-if="isUserEditable && hasSelectedDocuments"
          class="flex h-8 rounded-md border border-gray-200 bg-white"
        >
          <Button
            variant="tertiary"
            icon="fa-thumbs-up"
            class="rounded-r-none"
            :disabled="isBatchProcessing"
            @click="validateSelectedDocuments"
          >
            {{ $t('documents.validation.accept') }}
          </Button>
          <Button
            variant="tertiary"
            icon="fa-thumbs-down"
            class="rounded-l-none"
            :disabled="isBatchProcessing"
            @click="rejectSelectedDocuments"
          >
            {{ $t('documents.validation.reject') }}
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Checkbox, type CheckboxType } from '@owlint/feathers-vue'
import { useWatchFileStore } from '@target/stores/watchFile'
import { useWatchFileDocumentsStore } from '@target/stores/watchFileDocuments'
import { watchDebounced } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref, watch, watchEffect } from 'vue'

interface Props {
  isHidden: boolean
  isDisabled: boolean
  selectedDocuments: string[]
  isBatchProcessing?: boolean
}

const { selectedDocuments, isBatchProcessing = false } = defineProps<Props>()

const watchFileStore = useWatchFileStore()
const { isUserEditable } = storeToRefs(watchFileStore)

const emit = defineEmits<{
  (e: 'validate' | 'reject', value: string): void
  (e: 'search'): void
}>()

const selectAll = defineModel<CheckboxType>('selectAll', { required: true })

const watchFileDocumentsStore = useWatchFileDocumentsStore()
const { searchQuery, sortBy, sortOrder } = storeToRefs(watchFileDocumentsStore)

const searchInput = ref(searchQuery.value)

const isSearchExpanded = ref(false)

const hasSelectedDocuments = computed(() => selectedDocuments.length > 0)

const validateSelectedDocuments = () => {
  emit('validate', selectedDocuments.join(','))
}

const rejectSelectedDocuments = () => {
  emit('reject', selectedDocuments.join(','))
}

watch(hasSelectedDocuments, (newValue) => {
  if (!newValue) {
    isSearchExpanded.value = false
  }
})

watchEffect(() => {
  if (searchQuery.value === '') {
    searchInput.value = ''
  }
})

watchDebounced(
  searchInput,
  (newval) => {
    searchQuery.value = newval
    watchFileDocumentsStore.resetPagination()
  },
  { debounce: 500, maxWait: 1000 },
)

onMounted(() => {
  const savedSort = sessionStorage.getItem('documentsSort')
  if (savedSort) {
    const { sortBy: savedSortBy, sortOrder: savedSortOrder } = JSON.parse(savedSort)
    sortBy.value = savedSortBy ?? 'datePublish'
    sortOrder.value = savedSortOrder ?? 'ASC'
  }
})
</script>
