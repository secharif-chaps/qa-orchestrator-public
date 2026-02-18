<template>
  <div id="drawerContainer" class="flex h-full flex-col gap-4 pt-4">
    <div
      v-if="mayHaveDocuments"
      class="shadow-2 border-sage-100 mx-3 flex items-center justify-between gap-8 rounded-xl border p-3"
    >
      <div class="min-h-[32px]">
        <div v-if="documents.length > 0 || searchInput" class="relative flex items-center gap-3">
          <Searchbar
            id="document-search-input"
            v-model="searchInput"
            :placeholder="$t('watch_files.documents.search.placeholder')"
            class="w-74"
            size="sm"
          >
            <button
              v-if="searchQuery"
              class="flex items-center justify-between"
              @click="resetSearchbar"
            >
              <Icon icon="fa-xmark" />
            </button>
          </Searchbar>
          <Button
            :variant="displayMenuSorting ? 'accent' : 'tertiary'"
            size="sm"
            icon="fa-bars-filter"
            @click="displayMenuSorting = !displayMenuSorting"
          />
          <div
            v-if="displayMenuSorting"
            ref="menuSorting"
            class="rounded-2xs shadow-1 absolute top-full right-0 z-10 mt-2 flex w-max min-w-52 flex-col gap-2 border border-gray-200 bg-white"
          >
            <div>
              <div class="text-sage-800 p-2 text-xs">
                {{ $t('watch_files.sort.sort_by') }}
              </div>
              <div class="flex flex-col items-start">
                <div
                  class="rounded-2xs flex w-full items-center p-2"
                  :class="sortBy === 'datePublish' ? 'bg-sage-200' : 'hover:bg-sage-100'"
                >
                  <ORadio
                    id="radio-sort-datePublish"
                    v-model="sortBy"
                    value="datePublish"
                    @click="changeSort('datePublish')"
                  >
                    <label class="ml-2" for="radio-sort-datePublish">
                      <span class="flex items-center gap-2">
                        <Icon
                          v-if="sortBy === 'datePublish'"
                          :icon="sortOrder === 'ASC' ? 'fa-arrow-up' : 'fa-arrow-down'"
                          class="text-primary-500 text-base"
                        />
                        {{ $t('watch_files.filters.type.dates.publication') }}
                      </span>
                    </label>
                  </ORadio>
                </div>
                <div
                  class="rounded-2xs flex w-full items-center p-2"
                  :class="sortBy === 'dateCollect' ? 'bg-sage-200' : 'hover:bg-sage-100'"
                >
                  <ORadio
                    id="radio-sort-dateCollect"
                    v-model="sortBy"
                    value="dateCollect"
                    @click="changeSort('dateCollect')"
                  >
                    <label class="ml-2" for="radio-sort-dateCollect">
                      <span class="flex items-center gap-2">
                        <Icon
                          v-if="sortBy === 'dateCollect'"
                          :icon="sortOrder === 'ASC' ? 'fa-arrow-up' : 'fa-arrow-down'"
                          class="text-primary-500 text-base"
                        />
                        {{ $t('watch_files.filters.type.dates.collection') }}
                      </span>
                    </label>
                  </ORadio>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="relative flex min-h-0 flex-1">
      <!-- Sliding Left Panel - positioned relative to the document list container -->
      <div
        class="absolute top-0 bottom-0 left-0 transform transition-transform duration-300 ease-in-out"
        :class="filterPanelWidth"
      >
        <DocumentFilters
          v-model="displayDrawer"
          :facets="facets"
          :is-loading="isLoading"
          :error="error || undefined"
        />
      </div>
      <div
        class="ml-(--panel-document-filters-width) flex min-w-0 flex-1 flex-col pr-4 pb-4 transition-all duration-300 ease-in-out"
        :class="[{ 'mr-[30%]': isDetailPanelOpen }]"
      >
        <DocumentList
          :documents="documents"
          :total-items="totalItems"
          :is-loading="isLoading"
          :error="error?.message"
          :selected-document-id="selectedDocument?.id"
          :watch-file-id="watchFileId"
          @select="selectDocument"
          @search="handleSearch"
        />
      </div>

      <!-- Sliding Right Panel - positioned relative to the document list container -->
      <div
        class="absolute top-0 right-0 bottom-0 w-[30%] transform transition-transform duration-300 ease-in-out"
        :class="isDetailPanelOpen ? 'translate-x-0' : 'translate-x-full'"
      >
        <div class="relative flex h-full flex-col" tabindex="0">
          <div
            class="absolute bottom-0 left-0 flex w-full items-center justify-center bg-linear-to-t from-white to-white/30 py-7 backdrop-blur-[2px]"
          >
            <Button variant="accent" icon="fa-eye" @click="consultDocument">
              {{ $t('documents.detail.consultDocument') }}
            </Button>
          </div>
          <DocumentDetail :is-loading="isLoading" :document="selectedDocument" />
        </div>
      </div>

      <DocumentViewer
        :document="consultedDocument"
        :is-open="isViewerOpen"
        :is-loading="isConsultedDocumentLoading"
        :error="consultedDocumentError || undefined"
        @close="closeViewer"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Icon, ORadio, Searchbar } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { onClickOutside, watchDebounced } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, ref, useTemplateRef, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMarkDocumentAsSeen } from '~/api/mutations/document'
import { getCollectionDocumentQuery, getItemDocumentQuery } from '~/api/queries/document'
import DocumentDetail from '~/components/documents/DocumentDetail.vue'
import DocumentFilters from '~/components/documents/DocumentFilters.vue'
import DocumentList from '~/components/documents/DocumentList.vue'
import DocumentViewer from '~/components/documents/DocumentViewer.vue'
import { useWatchFileDocumentsStore } from '~/stores/watchFileDocuments'
import { useWatchFileFiltersStore } from '~/stores/watchFileFilters'
import type { Document, DocumentDateType } from '~/types/document'

definePage({
  meta: {
    layout: 'watch-file',
  },
})

const menuSortingRef = useTemplateRef('menuSorting')
onClickOutside(menuSortingRef, () => (displayMenuSorting.value = false))

const route = useRoute()
const router = useRouter()
const watchFileId = route.params.id as string

const selectedDocument = ref<Document | undefined>(undefined)
const isDetailPanelOpen = ref(false)
const isViewerOpen = ref(false)
const displayDrawer = ref(false)
const displayMenuSorting = ref(false)

const watchFileDocumentsStore = useWatchFileDocumentsStore()
const { displayFiltersPanel, currentPage, searchQuery, sortBy, sortOrder } =
  storeToRefs(watchFileDocumentsStore)

const searchInput = ref(searchQuery.value)

const filtersCounts = computed(() => watchFileDocumentsStore.filtersCounts)
const filterQuery = computed(() => watchFileDocumentsStore.filterQuery)
const queryParams = computed(() => watchFileDocumentsStore.queryParams)
const mayHaveDocuments = computed(
  () => !error.value && (isLoading.value || documents.value.length > 0 || searchInput.value),
)

// Handle left panel display
const filterPanelWidth = computed(() => {
  if (filtersCounts.value && displayFiltersPanel.value) {
    return 'w-[20%] min-w-64'
  } else if (filtersCounts.value && !displayFiltersPanel.value) {
    return 'w-auto'
  } else {
    return 'w-12'
  }
})

const { data, isLoading, error, refetch } = useQuery(getCollectionDocumentQuery, () => ({
  watchFileId,
  filters: queryParams.value,
}))

const { markAsSeen } = useMarkDocumentAsSeen()

const documents = computed(() => data.value?.items ?? [])
const totalItems = computed(() => data.value?.totalItems ?? 0)
const facets = computed(() => data.value?.facets)

// Document consultation query
const consultedDocumentId = ref<string | null>(null)
const {
  data: consultedDocument,
  isLoading: isConsultedDocumentLoading,
  error: consultedDocumentError,
} = useQuery(
  getItemDocumentQuery,
  computed(() => {
    return { id: consultedDocumentId.value! }
  }),
)

const consultDocument = () => {
  if (!selectedDocument.value?.id) return

  isViewerOpen.value = true
  consultedDocumentId.value = selectedDocument.value.id
}

const closeViewer = () => {
  consultedDocumentId.value = null
  isViewerOpen.value = false
}

const resetSearchbar = () => {
  searchQuery.value = ''
  searchInput.value = ''
}

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

function changeSort(fieldKey: DocumentDateType) {
  if (sortBy.value === fieldKey) {
    sortOrder.value = sortOrder.value === 'ASC' ? 'DESC' : 'ASC'
  } else {
    sortBy.value = fieldKey
    sortOrder.value = 'ASC'
  }
  sessionStorage.setItem(
    'documentsSort',
    JSON.stringify({
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
    }),
  )
}

const selectDocument = (doc: Document) => {
  if (selectedDocument.value?.id === doc.id) {
    return
  }

  markAsSeen({
    documentId: doc.id,
    watchFileId: watchFileId,
  })
  selectedDocument.value = doc
  isDetailPanelOpen.value = true
}

const closeDetailPanel = () => {
  selectedDocument.value = undefined
  isDetailPanelOpen.value = false
}

const handleSearch = () => {
  if (currentPage.value !== 1) {
    currentPage.value = 1
  } else {
    refetch()
  }
}

watchEffect(() => {
  if (!selectedDocument.value?.id) return

  const updatedDocument = documents.value.find(
    (document) => document.id === selectedDocument.value?.id,
  )

  if (updatedDocument) {
    selectedDocument.value = updatedDocument
  }
})

// Auto-select first document when list changes (initial load, search, page change),
// only if the list composition has actually changed (not just document properties)
const previousDocumentIds = ref<string[]>([])

watch(
  [documents, isLoading],
  ([newDocuments, loading]) => {
    const newIds = newDocuments.map((doc) => doc.id)
    const hasListChanged =
      newIds.length !== previousDocumentIds.value.length ||
      newIds.some((id, index) => id !== previousDocumentIds.value[index])

    if (!hasListChanged) {
      return
    }

    if (!newDocuments.length) {
      closeDetailPanel()
      return
    }

    previousDocumentIds.value = newIds

    const firstDocument = newDocuments[0]
    if (firstDocument) {
      selectDocument(firstDocument)
    } else if (!loading) {
      closeDetailPanel()
    }
  },
  { immediate: true },
)

const filtersStore = useWatchFileFiltersStore()

// Initialize from URL when facets are available
watch(facets, (newFacets) => {
  if (newFacets) {
    // Initialize search query
    if (route.query.search && typeof route.query.search === 'string') {
      searchQuery.value = route.query.search
      searchInput.value = route.query.search
    }

    // Initialize filters from URL
    filtersStore.initializeFromUrl(
      'documents',
      route.query,
      newFacets,
      () => watchFileDocumentsStore.isUrlSync,
    )
  }
})

// Sync URL with filter changes (excluding pagination and sort from URL sync)
watch(
  filterQuery,
  async (newQuery) => {
    if (!watchFileDocumentsStore.isUrlSync) {
      return
    }

    const urlQuery: Record<string, string | string[]> = { ...newQuery }

    await router.replace({
      query: urlQuery,
    })
  },
  { immediate: true },
)
</script>
