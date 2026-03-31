<template>
  <div :class="{ 'flex h-full flex-col': inModal }">
    <SectionListHeader
      v-if="showHeader"
      class="mb-4"
      :title="$t('target.watchFiles.sources.title')"
      :add-button-text="$t('target.watchFiles.sources.select_source')"
      :sub-title="subTitle"
      :readonly="readonly"
      :batch-selection="batchSelection"
      :loading="isLoading"
      :error="error ? error.message : undefined"
      :is-new
      @refresh="refetch()"
      @add="emit('add')"
    />

    <div
      v-if="withFilters"
      class="mb-4 flex items-center justify-between gap-4 border-b border-gray-200 px-1 pb-4"
    >
      <div class="h-10 w-96">
        <Searchbar
          id="source-list-search"
          v-model="searchTerm"
          :disabled="noSourcesToDisplay && !debouncedSearchTerm.trim()"
          :placeholder="$t('common.search.placeholder')"
          class="h-full"
          size="sm"
        >
          <button
            v-if="debouncedSearchTerm.trim().length"
            class="flex items-center justify-between"
            @click="searchTerm = ''"
          >
            <Icon icon="fa-xmark" />
          </button>
        </Searchbar>
      </div>

      <div class="modal-select-container relative h-10 w-56">
        <Select
          :key="JSON.stringify(sourceTypesData?.types)"
          v-model="typeFilter"
          :options="typeOptions"
          :display-value="typeDisplayValue"
          :placeholder="$t('target.watchFiles.actors.filters.type')"
          :disabled="isLoadingSourceTypes || noSourcesToDisplay || !typeOptions.length"
          multiple
          class="h-full"
        >
          <template #items="{ options }">
            <SelectItem v-for="option in options" :key="option.value" :value="option.value">
              {{ option.label }}
            </SelectItem>
          </template>
        </Select>
      </div>
    </div>

    <div v-if="error" class="flex flex-col items-center gap-4 py-10">
      <ErrorMessage :retry-button="true" @retry="refetch()" />
    </div>
    <template v-else>
      <EmptyState
        v-if="noSourcesToDisplay"
        :title="$t('target.watchFiles.actors.detail.sources.no_sources.title')"
        icon="fa-link-slash"
        vertical-align="center"
      />
      <template v-else>
        <div
          :class="{
            'min-h-0 flex-1 overflow-x-hidden overflow-y-auto': inModal,
          }"
        >
          <Table
            :loading="isLoading"
            :items="sources"
            :fields="columns"
            layout="fixed"
            class="m-1 rounded-lg bg-white"
          >
            <template #head(checkbox)>
              <th class="px-4 py-3.5">
                <Checkbox
                  id="select-all-sources"
                  v-model="selectAll"
                  :value="true"
                  :disabled="isLoading || !sources.length"
                />
              </th>
            </template>
            <template
              v-for="column in headerColumns"
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
              <th v-else class="px-3 py-3.5">
                <div class="flex">
                  <span class="text-base font-medium text-gray-700">
                    {{ field.label }}
                  </span>
                </div>
              </th>
            </template>
            <template #cell(checkbox)="{ item }">
              <td class="px-4 py-3">
                <Checkbox
                  :id="`select-source-${item.id}`"
                  v-model="selectedSources"
                  :value="item.id"
                />
              </td>
            </template>
            <template #cell(name)="{ item }">
              <td class="px-4 py-3">
                <SourceCard :source="item" variant="list" />
              </td>
            </template>
            <template #cell(type)="{ item }">
              <td class="px-4 py-3">
                <Tag intent="neutral" class="whitespace-nowrap">
                  {{ typeLabel(item.type) }}
                </Tag>
              </td>
            </template>
            <template #cell(advice)="{ item }">
              <td class="px-4 py-3">
                <span class="text-sm">
                  {{ ($i18n.locale === 'fr-FR' ? item.relevance?.fr : item.relevance?.en) || '' }}
                </span>
              </td>
            </template>
            <template #cell(active)="{ item }">
              <td class="px-4 py-3">
                <div class="flex justify-end">
                  <SourceListSwitch
                    v-if="!readonly"
                    :source="item"
                    :watch-file-id="watchFileId"
                    :batch-selection="batchSelection"
                    :selected-sources="selectedSources"
                    @toggle-selection="handleToggleSelection"
                  />
                </div>
              </td>
            </template>
          </Table>
        </div>

        <SectionListPaginator
          v-if="totalItems > itemsPerPage"
          v-model:current-page="page"
          :total-items="totalItems"
          :page-size="itemsPerPage"
          :result-text="$t('target.watchFiles.sources.pagination.result')"
        />
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import {
  Checkbox,
  HeaderCell,
  Icon,
  Searchbar,
  Select,
  SelectItem,
  Table,
  Tag,
} from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getActorSourcesQuery } from '@target/api/queries/actor'
import { getCollectionSourceQuery, getSourceTypesQuery } from '@target/api/queries/sources'
import EmptyState from '@target/components/global/EmptyState.vue'
import ErrorMessage from '@target/components/global/ErrorMessage.vue'
import SourceCard from '@target/components/sources/SourceCard.vue'
import SectionListHeader from '@target/components/watchFiles/EditSection/SectionListHeader.vue'
import SectionListPaginator from '@target/components/watchFiles/EditSection/SectionListPaginator.vue'
import { useSourcesStore } from '@target/stores/source'
import { SourceStatus } from '@target/types/source'
import { watchDebounced } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouteNames } from '@target/types/route-names'
import { useRoute } from 'vue-router'
import SourceListSwitch from './SourceListSwitch.vue'

interface Props {
  watchFileId: string
  readonly?: boolean
  inModal?: boolean
  withFilters?: boolean
  actorId?: string
  showHeader?: boolean
  batchSelection?: boolean
  active?: boolean
  selectable?: boolean
}

const {
  watchFileId,
  readonly = true,
  inModal = false,
  withFilters = false,
  actorId = undefined,
  showHeader = true,
  batchSelection = false,
  active = true,
  selectable = false,
} = defineProps<Props>()

const selectedSources = defineModel<string[]>('selectedSources', {
  default: () => [],
})

const emit = defineEmits<{
  add: []
}>()
const route = useRoute(RouteNames.WATCH_FILES)

const { t } = useI18n()
const sourcesStore = useSourcesStore()

const { itemsPerPage, page, sortBy, sortOrder } = storeToRefs(sourcesStore)

const searchTerm = ref('')
const debouncedSearchTerm = ref('')
const typeFilter = ref<string[]>([])

const isNew = computed(() => !route.params.id)

onMounted(() => {
  const savedSort = sessionStorage.getItem('watchFileSourcesSort')
  if (savedSort) {
    const { sortBy: savedSortBy, sortOrder: savedSortOrder } = JSON.parse(savedSort)
    sortBy.value = savedSortBy ?? ''
    sortOrder.value = savedSortOrder ?? 'ASC'
  }
})

watchDebounced(
  searchTerm,
  (newValue) => {
    debouncedSearchTerm.value = newValue.trim()
    page.value = 1
  },
  { debounce: 500, maxWait: 1000 },
)

const {
  data,
  isLoading: isLoadingSources,
  refetch,
  error,
} = useQuery(() => {
  if (actorId) {
    return getActorSourcesQuery({
      watchFileId,
      actorId,
      ...sourcesStore.filters,
    })
  }
  return getCollectionSourceQuery({
    watchFileId,
    ...sourcesStore.filters,
    name: debouncedSearchTerm.value || undefined,
    active: active,
    type: typeFilter.value.length > 0 ? typeFilter.value : undefined,
  })
})

const { data: sourceTypesData, isLoading: isLoadingSourceTypes } = useQuery(() =>
  getSourceTypesQuery({
    watchFileId,
    status: active ? SourceStatus.ACTIVE : SourceStatus.INACTIVE,
    name: debouncedSearchTerm.value || undefined,
  }),
)

const allSources = computed(() => data.value?.items ?? [])

const noSourcesToDisplay = computed(() => !isLoading.value && !allSources.value.length)
const sources = computed(() => allSources.value)

const totalItems = computed(() => {
  const apiTotal = data.value?.totalItems

  // If API returns totalItems and it's valid, use it
  if (apiTotal !== undefined && apiTotal !== null && apiTotal > 0) {
    return apiTotal
  }

  // Fallback: if no totalItems from API or it's 0, use the count of all sources loaded
  return allSources.value.length
})
const isLoading = computed(() => isLoadingSources.value && !allSources.value.length)

const typeOptions = computed(() => {
  if (!sourceTypesData.value?.types) {
    return []
  }
  return Object.entries(sourceTypesData.value.types).map(([type, count]) => ({
    value: type,
    label: `${typeLabel(type)} (${count})`,
  }))
})

const subTitle = computed(() => {
  if (!sources.value.length) {
    return ''
  }
  return t('target.watchFiles.sources.sub_title', {
    count: sources.value.length,
    total: totalItems.value,
  })
})

interface TableColumn {
  key: string
  label: string
  sortable?: boolean
  class?: string
}

const createColumn = (
  key: string,
  label: string,
  sortable: boolean = false,
  tableClass?: string,
): TableColumn => ({
  key,
  label,
  sortable,
  class: tableClass,
})

const columns = computed(() => {
  const cols = []

  if (selectable) {
    cols.push(createColumn('checkbox', '', false, 'w-12'))
  }

  cols.push(
    createColumn(
      'name',
      t('target.watchFiles.sources.name'),
      true,
      selectable ? 'w-1/3 lg:w-2/5 xl:w-1/3' : 'w-2/5 lg:w-1/2 xl:w-2/5',
    ),
    createColumn('type', t('target.watchFiles.sources.type'), true, 'w-1/5'),
    createColumn(
      'advice',
      t('target.watchFiles.sources.justification'),
      false,
      'w-1/4 xl:w-1/5 2xl:w-2/5',
    ),
    createColumn('active', '', false, 'w-18'),
  )

  return cols
})

const headerColumns = computed(() => columns.value.filter((c) => c.key !== 'checkbox'))

const selectAll = ref<boolean | 'indeterminate'>(false)

const toggleSelectAll = (value: boolean | 'indeterminate') => {
  if (isLoading.value || !sources.value.length) return

  const sourceIds = sources.value.map((source) => source.id)

  if (value === true) {
    const uniqueIds = new Set([...selectedSources.value, ...sourceIds])
    selectedSources.value = [...uniqueIds]
  } else if (value === false) {
    selectedSources.value = selectedSources.value.filter((id) => !sourceIds.includes(id))
  }
}

watch(selectAll, (newValue) => {
  toggleSelectAll(newValue)
})

watch(
  [sources, selectedSources],
  () => {
    if (isLoading.value || !sources.value.length) {
      selectAll.value = false
      return
    }

    const allSelected = sources.value.every((source) => selectedSources.value.includes(source.id))
    const someSelected = sources.value.some((source) => selectedSources.value.includes(source.id))

    if (allSelected) {
      selectAll.value = true
    } else if (someSelected) {
      selectAll.value = 'indeterminate'
    } else {
      selectAll.value = false
    }
  },
  { deep: true },
)

function changeSort(fieldKey: string) {
  if (sortBy.value === fieldKey) {
    if (sortOrder.value === 'ASC') {
      sortOrder.value = 'DESC'
    } else {
      // Third click: cancel sort
      sortBy.value = ''
      sortOrder.value = 'ASC'
    }
  } else {
    sortBy.value = fieldKey
    sortOrder.value = 'ASC'
  }
  sessionStorage.setItem(
    'watchFileSourcesSort',
    JSON.stringify({
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
    }),
  )
  sourcesStore.resetPagination()
}

const typeLabel = (type: string) => {
  if (!type) return t('target.watchFiles.actors.detail.sources.unknown_type')

  if (type.startsWith('social_media:')) {
    return t('target.sourceTypes.social_media')
  }

  const translationKey = `target.sourceTypes.${type}`
  const translation = t(translationKey)

  return translation !== translationKey ? translation : type
}

const typeDisplayValue = computed(() => {
  const types = sourceTypesData.value?.types

  if (!typeFilter.value || typeFilter.value.length === 0 || !types) {
    return ''
  }

  const labels = typeFilter.value.map((value) => {
    const count = types[value]
    if (count !== undefined) {
      return `${typeLabel(value)} (${count})`
    }
    return typeLabel(value)
  })

  return labels.join(', ')
})

watch(
  () => watchFileId,
  () => {
    refetch()
    searchTerm.value = ''
    debouncedSearchTerm.value = ''
    typeFilter.value = []
  },
)

watch(typeFilter, () => {
  page.value = 1
})

const handleToggleSelection = (sourceId: string, isSelected: boolean) => {
  if (!selectedSources.value) {
    selectedSources.value = []
  }
  if (isSelected) {
    if (!selectedSources.value.includes(sourceId)) {
      selectedSources.value = [...selectedSources.value, sourceId]
    }
  } else {
    selectedSources.value = selectedSources.value.filter((id) => id !== sourceId)
  }
}

defineExpose({
  refetch,
  isLoading,
})
</script>

<style>
div[data-reka-popper-content-wrapper] {
  z-index: 40 !important;
}
</style>
