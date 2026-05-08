<template>
  <div class="flex flex-col gap-3">
    <Searchbar
      v-if="eventTypes.length > 5"
      id="searchbar-filter-eventTypes"
      v-model="searchInput"
      :placeholder="t('target.watchFiles.filters.type.event_types.placeholder')"
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
          <span>{{ eventTypeLabelMap[eventType] ?? eventType }}</span>
          <span class="text-gray-800">{{ $t('common.countSuffix', { count }) }}</span>
        </label>
      </Checkbox>
    </div>
    <div v-else>
      <p class="text-sm text-gray-800">
        {{ t('target.watchFiles.filters.empty') }}
      </p>
    </div>
    <Button
      v-if="filteredEventTypes.length > 5"
      class="self-start"
      variant="tertiary"
      @click="displayAllEventTypes = !displayAllEventTypes"
    >
      {{
        displayAllEventTypes
          ? t('target.watchFiles.filters.type.event_types.see.less')
          : t('target.watchFiles.filters.type.event_types.see.more')
      }}
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
        t('target.watchFiles.filters.type.reset', {
          name: t('target.watchFiles.filters.type.event_types'),
        })
      }}
    </Button>
  </div>
</template>

<script lang="ts" setup>
import { Button, Checkbox, Searchbar } from '@owlint/feathers-vue'
import type { AnalysisEventFacet } from '@target/types/facet'
import { watchDebounced } from '@vueuse/core'
import { computed, ref, watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const eventTypeLabelMap = computed<Record<string, string>>(() => ({
  commercial_business: t('target.watchFiles.analysis.event.type.commercial_business'),
  financial: t('target.watchFiles.analysis.event.type.financial'),
  market_competitors: t('target.watchFiles.analysis.event.type.market_competitors'),
  organizational_hr: t('target.watchFiles.analysis.event.type.organizational_hr'),
  regulatory_political: t('target.watchFiles.analysis.event.type.regulatory_political'),
  societal_environmental: t('target.watchFiles.analysis.event.type.societal_environmental'),
  technological_rd: t('target.watchFiles.analysis.event.type.technological_rd'),
}))

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
