<template>
  <div class="mb-4 space-y-3">
    <div class="flex items-center justify-between">
      <div v-if="showSearch" class="w-96">
        <Searchbar
          id="sources-filter-bar-search"
          v-model="searchQuery"
          :placeholder="$t('common.search.placeholder')"
          class="w-full"
          size="sm"
        />
      </div>
      <div v-if="showTypeFilter" class="relative h-10 w-56">
        <Select
          v-model="selectedTypes"
          :options="typeOptions"
          :display-value="getTypeDisplayValue"
          :placeholder="$t('watch_files.actors.detail.sources.type_filter_placeholder')"
          :disabled="availableTypes.length === 0"
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

    <div v-if="hasActiveFilters" class="text-sm text-gray-600">
      {{
        $t('watch_files.actors.detail.sources.filter_results', {
          filtered: filteredCount,
          total: totalCount,
        })
      }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { Searchbar, Select, SelectItem } from '@owlint/feathers-vue';
import type { Source } from '@target/types/source';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
  sources: Source[]
  showSearch?: boolean
  showTypeFilter?: boolean
}>()

const emit = defineEmits<{
  handleFilteredSources: [sources: Source[]]
}>()

const { t } = useI18n()

const searchQuery = ref('')
const selectedTypes = ref<string[]>([])

const availableTypes = computed(() => {
  const types = new Set<string>()
  props.sources.forEach((source) => {
    if (source.type) {
      types.add(source.type)
    }
  })
  return Array.from(types).sort()
})

const typeOptions = computed(() => {
  return availableTypes.value.map((type) => ({
    value: type,
    label: getTypeLabel(type),
  }))
})

const getTypeLabel = (type: string) => {
  if (!type) return t('watch_files.actors.detail.sources.unknown_type')

  if (type.startsWith('social_media:')) {
    return t('source_types.social_media')
  }

  const translationKey = `source_types.${type}`
  const translation = t(translationKey)

  return translation !== translationKey ? translation : type
}

const getTypeDisplayValue = (selectedValues: string[]) => {
  if (!selectedValues || selectedValues.length === 0) {
    return ''
  }

  const labels = selectedValues.map((value) => {
    const option = typeOptions.value.find((opt) => opt.value === value)
    return option ? option.label : value
  })

  return Array.isArray(labels) ? labels.join(', ') : labels || ''
}

const filteredSources = computed(() => {
  let filtered = props.sources

  if (searchQuery.value.trim()) {
    const searchTerm = searchQuery.value.toLowerCase()
    filtered = filtered.filter(
      (source) =>
        source.name.toLowerCase().includes(searchTerm) ||
        (source.primaryDomain && source.primaryDomain.toLowerCase().includes(searchTerm)),
    )
  }

  if (selectedTypes.value.length > 0) {
    filtered = filtered.filter((source) => source.type && selectedTypes.value.includes(source.type))
  }

  return filtered
})

const filteredCount = computed(() => filteredSources.value.length)
const totalCount = computed(() => props.sources.length)

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || selectedTypes.value.length > 0
})

watch(
  filteredSources,
  (newFilteredSources) => {
    emit('handleFilteredSources', newFilteredSources)
  },
  { immediate: true },
)
</script>

<style>
div[data-reka-popper-content-wrapper] {
  z-index: 40 !important;
}
</style>
