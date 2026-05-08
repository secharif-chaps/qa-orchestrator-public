<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <h1 class="text-2xl font-bold text-gray-900">
          {{ $t('target.watchFiles.list.title') }}
        </h1>
        <RouterLink :to="{ name: RouteNames.WATCH_FILES_NEW }">
          <Button icon="fa-plus">
            {{ $t('target.watchFiles.new') }}
          </Button>
        </RouterLink>
      </div>
      <div class="relative flex items-center gap-2">
        <Searchbar
          id="watch-files-search"
          v-model="searchQuery"
          icon="fa-magnifying-glass"
          :placeholder="$t('target.watchFiles.list.search_placeholder')"
          class="w-64"
        >
          <Button
            v-if="searchQuery"
            variant="tertiary"
            size="sm"
            icon="fa-xmark"
            :title="$t('common.search.clear')"
            @click="searchQuery = ''"
          />
        </Searchbar>
        <Button
          ref="sortBtn"
          icon="fa-filter"
          :variant="showSortCard ? 'accent' : 'tertiary'"
          @click="showSortCard = !showSortCard"
        />
        <div
          v-if="showSortCard"
          ref="sortCard"
          class="shadow-1 rounded-2xs absolute top-full right-0 z-20 mt-2 flex w-max min-w-52 flex-col gap-2 border border-gray-100 bg-white"
        >
          <div>
            <div class="text-sage-800 p-2 text-xs">
              {{ $t('target.watchFiles.sort.sort_by') }}
            </div>
            <div class="flex flex-col items-start text-sm">
              <div
                v-for="column in columns.filter((c) => c.sortable)"
                :key="column.key"
                class="rounded-2xs flex w-full items-center p-2"
                :class="sortBy === column.key ? 'bg-sage-200' : 'hover:bg-sage-100'"
              >
                <ORadio
                  :id="`radio-sort-${column.key}`"
                  v-model="sortBy"
                  :value="column.key"
                  @click="changeSort(column.key)"
                >
                  <label class="ml-2" :for="`radio-sort-${column.key}`">
                    <span class="flex items-center gap-2">
                      <Icon
                        v-if="sortBy === column.key"
                        :icon="sortOrder === 'ASC' ? 'fa-arrow-up' : 'fa-arrow-down'"
                        class="text-primary-500 text-base"
                      />
                      {{ column.sortLabel || column.label }}
                    </span>
                  </label>
                </ORadio>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-100">
            <div class="text-sage-800 p-2 text-xs">
              {{ $t('target.watchFiles.sort.filter') }}
            </div>
            <div class="flex flex-col items-start text-sm">
              <div
                class="rounded-2xs flex w-full items-center p-2"
                :class="showFavorites ? 'bg-sage-200' : 'hover:bg-sage-100'"
              >
                <Checkbox
                  id="checkbox-favorite-sort"
                  v-model="showFavorites"
                  value
                  name="checkbox-sort"
                >
                  <label for="checkbox-favorite-sort" class="flex items-center gap-2 pl-2">
                    <Icon icon="fa-star" class="text-gray-700" />
                    <span>{{ $t('target.watchFiles.sort.favorites') }}</span>
                  </label>
                </Checkbox>
              </div>
              <div
                class="rounded-2xs flex w-full items-center p-2"
                :class="hideArchived ? 'bg-sage-200' : 'hover:bg-sage-100'"
              >
                <Checkbox
                  id="checkbox-archived-sort"
                  v-model="hideArchived"
                  value
                  name="checkbox-sort"
                >
                  <label for="checkbox-archived-sort" class="flex items-center gap-2 pl-2">
                    <Icon icon="fa-box-archive" class="text-gray-700" />
                    <span>{{ $t('target.watchFiles.sort.archived') }}</span>
                  </label>
                </Checkbox>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div
      v-if="!isLoading && !watchFiles.length"
      class="flex flex-col items-center justify-center py-16"
    >
      <Icon icon="fa-folder-open" class="mb-4 text-4xl text-gray-300" />
      <span class="mb-2 text-gray-500">{{ $t('target.watchFiles.empty') }}</span>
    </div>
    <div v-else class="space-y-6">
      <div class="shadow-2 border-sage-100 rounded-2xl border bg-white p-6 sm:px-6">
        <Table :loading="isLoading && status !== 'success'" :items="watchFiles" :fields="columns">
          <template
            v-for="column in columns"
            :key="column.key"
            #[`head(${column.key})`]="{ field }"
          >
            <HeaderCell
              v-if="column.sortable"
              :field="field"
              :sort-order="sortOrder"
              :sort-by="sortBy"
              @click="changeSort(field.key)"
            />
          </template>

          <template #cell(name)="{ value, item }">
            <td class="of-flex of-ml-2 px-4 py-3">
              <RouterLink
                :to="{ name: RouteNames.WATCH_FILES, params: { id: item.id } }"
                class="text-primary-600 hover:underline"
              >
                {{ value }}
              </RouterLink>
            </td>
          </template>
          <template #cell(updatedAt)="{ value }">
            <td class="px-4 py-3">
              <span class="text-sm text-gray-900">{{ formatDate(value, 'long') }}</span>
            </td>
          </template>
          <template #cell(countAccess)="{ item }">
            <td class="py-3">
              <WatchFileShareButton
                :watch-file="item"
                show-label
                show-count
                is-read-only
                @share-watch-file="shareWatchFile"
              />
            </td>
          </template>
          <template #cell(newContent)="{ item }">
            <td class="px-4 py-3">
              <RouterLink
                v-if="item.newContentCount > 0"
                :to="{
                  name: RouteNames.WATCH_FILES,
                  params: { id: item.id },
                  query: { tab: 'news' },
                }"
                class="text-primary-600 hover:text-primary-800 text-sm font-medium underline"
                @click.stop
              >
                {{ item.newContentCount }}
                {{ $t('target.watchFiles.new_content.documents') }}
              </RouterLink>
              <span v-else class="text-sm text-gray-400">
                {{ ZERO_COUNT }} {{ $t('target.watchFiles.new_content.documents') }}
              </span>
            </td>
          </template>
          <template #cell(status)="{ item }">
            <td class="px-2 py-3 text-sm text-gray-900">
              <div class="flex items-center gap-1">
                <Icon :icon="statusIcon(item.status)" />
                {{ watchFileStatusLabelMap[item.status] ?? item.status }}
              </div>
            </td>
          </template>
          <template #cell(actions)="{ item }">
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-2">
                <WatchFileFavoriteButton :watch-file="item" />
                <WatchFileShareButton
                  :watch-file="item"
                  icon="fa-share"
                  :class="{ invisible: !item.userEditable }"
                  @share-watch-file="shareWatchFile"
                />
                <WatchFileArchiveButton
                  :class="{ invisible: !item.userEditable }"
                  :watch-file="item"
                />
              </div>
            </td>
          </template>
        </Table>
      </div>

      <div class="shadow-2 border-sage-100 rounded-2xl border bg-white p-6 sm:px-6">
        <Pagination
          v-model:current-page="currentPage"
          :meta="paginationMeta"
          @updatePerPage="itemsPerPage = $event"
        />
      </div>
    </div>

    <WatchFileShareDialog
      v-model:is-open="shareDialogIsOpen"
      :selected-watch-file="currentWatchFile"
      @close="shareDialogIsOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime'
import { useToast } from '@/target/composables/useToast'
import { RouteNames } from '@/target/types/route-names'
import { Button, Checkbox, HeaderCell, Icon, ORadio, Searchbar, Table } from '@owlint/feathers-vue'
import Pagination from '@/components/ui/Pagination.vue'
import type { PaginationMeta } from '@/types/pagination'
import { useQuery } from '@pinia/colada'
import { getCollectionWatchFileQuery } from '@target/api/queries/watchFile'
import WatchFileArchiveButton from '@target/components/watchFiles/WatchFileArchiveButton.vue'
import WatchFileFavoriteButton from '@target/components/watchFiles/WatchFileFavoriteButton.vue'
import WatchFileShareButton from '@target/components/watchFiles/WatchFileShareButton.vue'
import WatchFileShareDialog from '@target/components/watchFiles/WatchFileShareDialog.vue'
import { useWatchFileStore } from '@target/stores/watchFile'
import { WATCH_FILE_STATUS, type WatchFile, type WatchFileStatus } from '@target/types/watchFile'
import { onClickOutside, refDebounced } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const watchFileStore = useWatchFileStore()

const {
  sortOrder,
  sortBy,
  currentPage,
  itemsPerPage,
  searchQuery: storeSearchQuery,
  showFavorites,
  hideArchived,
  filters,
} = storeToRefs(watchFileStore)

const { t } = useI18n()
const toast = useToast()

const watchFileStatusLabelMap = computed<Record<string, string>>(() => ({
  draft: t('target.watchFiles.status.draft'),
  enabled: t('target.watchFiles.status.enabled'),
  archived: t('target.watchFiles.status.archived'),
}))

const ZERO_COUNT = 0

interface TableColumn {
  key: string
  label: string
  sortable?: boolean
  sortLabel?: string
}

// Helper function to create columns with defaults
const createColumn = (
  key: string,
  label: string,
  sortable: boolean = false,
  sortLabel: string = '',
): TableColumn => ({
  key,
  label,
  sortable,
  sortLabel,
})

const searchQuery = ref(storeSearchQuery.value)
const debouncedSearchQuery = refDebounced(searchQuery, 500)
const currentWatchFile = ref<WatchFile | null>(null)
const showSortCard = ref(false)
const shareDialogIsOpen = ref(false)

watch(debouncedSearchQuery, (value) => {
  storeSearchQuery.value = value
})

const columns: TableColumn[] = [
  /*
  set third argument to true to make the column sortable
  set fourth argument if sort label not the same than column label
  */
  createColumn('name', t('target.watchFiles.list.columns.name'), true),
  createColumn('updatedAt', t('target.watchFiles.list.columns.updated_at'), true),
  createColumn(
    'countAccess',
    t('target.watchFiles.list.columns.access'),
    true,
    t('target.watchFiles.sort.access_count'),
  ),
  //createColumn('newContent', t('target.watchFiles.list.columns.new_content')),
  createColumn(
    'status',
    t('target.watchFiles.list.columns.status'),
    true,
    t('target.watchFiles.sort.status'),
  ),
  createColumn('actions', ''),
]

const { formatDate } = useDateTime()

const shareWatchFile = (watchFile: WatchFile) => {
  currentWatchFile.value = watchFile
  shareDialogIsOpen.value = true
}

const sortCardRef = useTemplateRef('sortCard')
const sortBtn = ref<HTMLElement | null>(null)

onClickOutside(sortCardRef, () => (showSortCard.value = false), {
  ignore: [sortBtn],
})

onMounted(() => {
  const savedSort = sessionStorage.getItem('watchFileSort')
  if (savedSort) {
    const { sortBy: savedSortBy, sortOrder: savedSortOrder } = JSON.parse(savedSort)
    sortBy.value = savedSortBy || 'name'
    sortOrder.value = savedSortOrder || 'ASC'
  }
})

const {
  data: watchFilesCollection,
  isLoading,
  status,
  error,
} = useQuery(() => getCollectionWatchFileQuery(filters.value))

watch(error, () => {
  if (error.value) {
    console.error('Error fetching watchfiles:', error)
    toast.error(t('target.watchFiles.toast.error.load'))
  }
})

watch([storeSearchQuery, showFavorites, hideArchived, itemsPerPage], () => {
  watchFileStore.resetPagination()
})

const watchFiles = computed(() => watchFilesCollection.value?.items ?? [])

const paginationMeta = computed<PaginationMeta | null>(() => {
  if (!watchFilesCollection.value) return null
  const total = watchFilesCollection.value.totalItems ?? 0
  return {
    total,
    per_page: itemsPerPage.value,
    current_page: currentPage.value,
    last_page: Math.ceil(total / itemsPerPage.value) || 1,
  }
})

function changeSort(fieldKey: string) {
  if (sortBy.value === fieldKey) {
    sortOrder.value = sortOrder.value === 'ASC' ? 'DESC' : 'ASC'
  } else {
    sortBy.value = fieldKey
    sortOrder.value = 'ASC'
  }
  sessionStorage.setItem(
    'watchFileSort',
    JSON.stringify({
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
    }),
  )
  watchFileStore.resetPagination()
}

function statusIcon(status: WatchFileStatus) {
  switch (status) {
    case WATCH_FILE_STATUS.DRAFT:
      return 'fa-file-lines'
    case WATCH_FILE_STATUS.ENABLED:
      return 'fa-play'
    case WATCH_FILE_STATUS.ARCHIVED:
      return 'fa-box-archive'
  }
}
</script>
