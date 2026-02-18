<template>
  <div class="actor-list-view p-2">
    <div class="mb-4 flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
      <div class="h-10 w-96">
        <Searchbar
          id="actor-list-search"
          v-model="searchTerm"
          :disabled="noActorsToDisplay && !debouncedSearchTerm.trim()"
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
          :key="JSON.stringify(actorTypesData?.types)"
          v-model="typeFilter"
          :options="typeOptions"
          :display-value="typeDisplayValue"
          :placeholder="$t('watch_files.actors.filters.type')"
          :disabled="noActorsToDisplay || !typeOptions.length"
          multiple
          class="h-full"
        >
          <template #items="{ options }">
            <SelectItem v-for="option in options" :key="option.type" :value="option.type">
              {{ displayLabelActorType(option) }}
            </SelectItem>
          </template>
        </Select>
      </div>
    </div>

    <div class="h-[620px] pb-16">
      <ActorsGrid
        v-model:current-page="currentPage"
        :actors="actors"
        :loading="loading"
        :error="error"
        :watch-file-id="watchFileId"
        :page-size="pageSize"
        :total-items="totalItems"
        :show-pagination="true"
        :in-modal="true"
        :fixed-height="true"
        :is-actor-selected="isActorSelected"
        :override-default-action="true"
        @retry="$emit('retry')"
        @toggle-status="$emit('toggle-status', $event)"
        @actor-clicked="$emit('actor-clicked', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon, Searchbar, Select, SelectItem } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { getActorTypesQuery } from '@target/api/queries/actor'
import { useActorStore } from '@target/stores/actor'
import type { ActorType } from '@target/types/actor'
import { ActorStatus } from '@target/types/actor'
import type { WatchFileActor } from '@target/types/watchFile'
import { useDebounceFn } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import ActorsGrid from './ActorsGrid.vue'

interface Props {
  actors: WatchFileActor[]
  loading?: boolean
  error?: string
  watchFileId?: string
  pageSize?: number
  totalItems?: number
  isActorSelected?: (actor: WatchFileActor) => boolean
}

interface Emits {
  retry: []
  'toggle-status': [actor: WatchFileActor]
  'actor-clicked': [actor: WatchFileActor]
}

const {
  actors,
  loading = false,
  error = '',
  watchFileId = undefined,
  pageSize = undefined,
  totalItems = undefined,
  isActorSelected = undefined,
} = defineProps<Props>()

defineEmits<Emits>()

const actorStore = useActorStore()
const { search, page: currentPage } = storeToRefs(actorStore)

const typeFilter = defineModel<string[]>('typeFilter', { required: true })

const searchTerm = ref(search.value || '')
const debouncedSearchTerm = ref(search.value || '')

const debouncedSearch = useDebounceFn((searchValue: string) => {
  search.value = searchValue
  debouncedSearchTerm.value = searchValue.trim()
}, 500)

watch(searchTerm, (newValue) => {
  debouncedSearch(newValue)
})

const noActorsToDisplay = computed(() => !loading && !actors.length)

watch(
  () => search.value,
  (newSearch) => {
    if (newSearch !== searchTerm.value) {
      searchTerm.value = newSearch || ''
    }
  },
)

const { data: actorTypesData } = useQuery(() =>
  getActorTypesQuery({
    watchFileId: watchFileId || '',
    status: ActorStatus.INACTIVE,
    name: debouncedSearchTerm.value || undefined,
  }),
)

const typeOptions = computed(() => actorTypesData.value?.types ?? [])

const displayLabelActorType = (option: ActorType) => {
  const capitalizedType = option.type.charAt(0).toUpperCase() + option.type.slice(1)
  return `${capitalizedType} (${option.count})`
}

const typeDisplayValue = computed(() => {
  const types = actorTypesData.value?.types

  if (!typeFilter.value || typeFilter.value.length === 0 || !types) {
    return ''
  }

  const labels = typeFilter.value.map((value) => {
    const option = types.find((t) => t.type === value)
    if (option) {
      return displayLabelActorType(option)
    }
    return value.charAt(0).toUpperCase() + value.slice(1)
  })

  return labels.join(', ')
})

watch(search, () => {
  currentPage.value = 1
})

watch(typeFilter, () => {
  currentPage.value = 1
})
</script>

<style>
div[data-reka-popper-content-wrapper] {
  z-index: 40 !important;
}
</style>
