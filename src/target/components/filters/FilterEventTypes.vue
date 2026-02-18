<template>
  <div class="flex flex-col gap-3">
    <Searchbar
      v-if="eventTypes.length > 5"
      id="searchbar-filter-eventTypes"
      v-model="searchInput"
      :placeholder="t('watch_files.filters.type.event_types.placeholder')"
      size="sm"
    />
    <div v-if="eventTypes.length" class="flex flex-col items-start gap-1.5">
      <Checkbox
        v-for="{ type: eventType, count } in displayedEventTypes"
        :id="eventType"
        :key="eventType"
        v-model="selectedEventTypes"
        :value="eventType"
        name="filter-eventTypes"
      >
        <label v-if="eventType" :for="eventType" class="flex items-center gap-2 pl-2">
          <span>{{ $t(`watch_files.analysis.event.type.${eventType}`) }}</span>
          <span class="text-gray-800"> ({{ count }}) </span>
        </label>
      </Checkbox>
    </div>
    <div v-else>
      <p class="text-sm text-gray-800">
        {{ t('watch_files.filters.empty') }}
      </p>
    </div>
    <Button
      v-if="filteredEventTypes.length > 5"
      class="self-start"
      variant="tertiary"
      @click="displayAllEventTypes = !displayAllEventTypes"
    >
      {{ t(`watch_files.filters.type.event_types.see.${displayAllEventTypes ? 'less' : 'more'}`) }}
    </Button>
    <Button
      v-if="selectedEventTypes.length"
      class="w-fit"
      variant="tertiary"
      size="sm"
      icon="fa-rotate-left"
      @click="handleReset"
    >
      {{
        t('watch_files.filters.type.reset', {
          name: t('watch_files.filters.type.event_types'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import { Button, Checkbox, Searchbar } from '@owlint/feathers-vue'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AnalysisEventFacet } from '~/types/facet'

const { t } = useI18n()

interface Props {
  eventTypes: AnalysisEventFacet[]
}

const { eventTypes } = defineProps<Props>()

const selectedEventTypes = defineModel<string[]>({ required: true })

const displayAllEventTypes = ref(false)
const searchEventType = ref('')
const searchInput = ref(searchEventType.value)

const filteredEventTypes = computed(() =>
  eventTypes.filter(({ type: eventType }) => {
    if (!eventType) return false
    const isSelected = selectedEventTypes.value.some(
      (storedEventType) => storedEventType === eventType,
    )
    return (
      eventType.toLocaleLowerCase().includes(searchEventType.value.toLocaleLowerCase()) ||
      isSelected
    )
  }),
)

const slicedEventTypes = computed(() => filteredEventTypes.value.slice(0, 5))

const displayedEventTypes = computed(() =>
  displayAllEventTypes.value ? filteredEventTypes.value : slicedEventTypes.value,
)

const handleReset = () => {
  selectedEventTypes.value = []
}

watchEffect(() => {
  if (searchEventType.value === '') {
    searchInput.value = ''
  }
})

watchDebounced(
  searchInput,
  (newval) => {
    searchEventType.value = newval
  },
  { debounce: 500, maxWait: 1000 },
)
</script>
