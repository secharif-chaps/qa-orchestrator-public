<template>
  <div class="flex h-full flex-col">
    <ErrorMessage v-if="error" :title="t('common.error.title.list')" width="full" />
    <SourcesListEmpty v-else-if="!isLoading && !hasSources" :watch-file-id="watchFileId" />
    <template v-else>
      <div class="mb-6 flex shrink-0 items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="text-medium text-gray-900">
            {{ t('watch_files.activity.sources.title') }}
          </div>
          <Badge
            v-if="nbSources && !isSearchActive"
            variant="secondary"
            size="sm"
            :number="String(nbSources)"
          />
        </div>
        <Searchbar
          id="source-search-input"
          v-model="searchQuery"
          :placeholder="t('watch_files.activity.sources.search_placeholder')"
          size="sm"
          class="w-64"
          :disabled="isLoading"
          @keydown.escape="clearSearch"
        >
          <button v-if="searchQuery" class="flex items-center justify-between" @click="clearSearch">
            <Icon icon="fa-xmark" />
          </button>
        </Searchbar>
      </div>

      <div class="scrollable min-h-0 flex-1">
        <Transition
          enter-active-class="transition-opacity duration-200 ease-in-out"
          leave-active-class="transition-opacity duration-200 ease-in-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
          mode="out-in"
        >
          <template v-if="isLoading">
            <SourcesListGroupedSkeleton />
          </template>
          <template v-else>
            <div :key="debouncedSearchQuery">
              <div v-if="isSearchActive" class="mb-6 flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900">
                  {{
                    t('watch_files.activity.sources.search.results', {
                      count: filteredSources.length,
                    })
                  }}
                </span>
                <Button variant="tertiary" icon="fa-xmark" size="sm" @click="clearSearch">
                  {{ t('watch_files.activity.sources.search.clear') }}
                </Button>
              </div>
              <SourcesAccordion :sources-data="sourcesData" :search-query="debouncedSearchQuery" />
            </div>
          </template>
        </Transition>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { Badge, Button, Icon, Searchbar } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getWatchFileSourcesGroupedQuery } from '@target/api/queries/sources'
import ErrorMessage from '@target/components/global/ErrorMessage.vue'
import SourcesListGroupedSkeleton from '@target/components/skeletons/SourcesListGroupedSkeleton.vue'
import SourcesAccordion from '@target/components/watchFiles/ActivitySection/SourceMonitoring/SourcesAccordion.vue'
import SourcesListEmpty from '@target/components/watchFiles/ActivitySection/SourceMonitoring/SourcesListGroupedEmpty.vue'
import type { Source, SourceGroup } from '@target/types/source'
import { watchDebounced } from '@vueuse/core'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  watchFileId?: string
}
const { watchFileId = undefined } = defineProps<Props>()

const {
  data: sourcesData,
  isLoading,
  error,
} = useQuery(() =>
  getWatchFileSourcesGroupedQuery({
    watchFileId: watchFileId!,
  }),
)

// Search functionality
const searchQuery = ref('')
const debouncedSearchQuery = ref('')

// Debounced search with 300ms delay
watchDebounced(
  searchQuery,
  (newQuery) => {
    debouncedSearchQuery.value = newQuery
  },
  { debounce: 300 },
)

const nbSources = computed(() => sourcesData.value?.summary?.total || 0)
const hasSources = computed(() => (sourcesData.value?.summary?.total || 0) > 0)

// Search state
const isSearchActive = computed(() => debouncedSearchQuery.value.trim().length > 0)

// Clear search function (immediate, no debounce)
const clearSearch = () => {
  searchQuery.value = ''
  debouncedSearchQuery.value = ''
}

// Get all sources for search results count
const allSources = computed(() => {
  if (!sourcesData.value?.groups || !Array.isArray(sourcesData.value.groups)) return []

  // Flatten all sources from all groups
  return sourcesData.value.groups.reduce((acc: Source[], group: SourceGroup) => {
    return acc.concat(group.sources || [])
  }, [])
})

// Filter sources by debounced search query for results count
const filteredSources = computed(() => {
  if (!debouncedSearchQuery.value.trim()) {
    return allSources.value
  }

  const query = debouncedSearchQuery.value.toLowerCase().trim()
  return allSources.value.filter(
    (source: Source) =>
      source.name.toLowerCase().includes(query) ||
      source.primaryDomain.toLowerCase().includes(query),
  )
})
</script>
