<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />
    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <NoData v-else-if="!hasTimelineData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.timeline.noData') }}
      </p>
    </NoData>

    <!-- Timeline visualization -->
    <div v-else class="relative">
      <!-- Timeline events -->
      <div class="bg-base-100 rounded-lg p-4">
        <div class="mb-6 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-lg font-semibold">{{ $t('timeline.title') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="tertiary"
              :icon="sortAscending ? 'fa fa-arrow-up' : 'fa fa-arrow-down'"
              size="sm"
              :title="
                sortAscending ? $t('timeline.sort.oldestFirst') : $t('timeline.sort.newestFirst')
              "
              @click="toggleSortOrder"
            >
              {{
                sortAscending
                  ? $t('timeline.sort.oldestFirst', 'Oldest first')
                  : $t('timeline.sort.newestFirst', 'Newest first')
              }}
            </Button>
            <div class="w-64">
              <Searchbar
                id="timeline-search"
                v-model="searchQuery"
                :placeholder="$t('timeline.search.placeholder')"
              />
            </div>
          </div>
        </div>
        <div>
          <Event v-for="(event, index) in filteredEvents" :key="index" :event="event" />
        </div>

        <!-- No results message -->
        <div v-if="filteredEvents.length === 0 && searchQuery">
          <NoData>
            <p class="text-secondary text-lg font-medium">
              {{ $t('timeline.search.noResults', { query: searchQuery }) }}
            </p>
          </NoData>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref, inject } from 'vue'
import type { Ref } from 'vue'
import Event from '@/components/company/timeline/Event.vue'
import { companyTasksQuery } from '@/queries/tasks'
import { Button, Searchbar } from '@owlint/feathers-vue'
import NoData from '@/components/ui/NoData.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import type { SourcedValue } from '@/types/company'

const route = useRoute('/folders/[folderId]/companies/[companyId]/timeline')

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'timeline'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const searchQuery = ref('')
const sortAscending = ref(false) // Default to newest first (descending)

const toggleSortOrder = () => {
  sortAscending.value = !sortAscending.value
}

// Helper to extract value from SourcedValue or return plain string
const extractDateValue = (field: SourcedValue<string> | string | undefined): string => {
  if (!field) return ''
  if (typeof field === 'string') return field
  if (typeof field === 'object' && field.value) return field.value
  return ''
}

const getTimelineEvents = computed(() => {
  if (!company.value?.timeline?.events) return []

  // Sort events by date
  return [...company.value.timeline.events].sort((a, b) => {
    // Extract date value (handle SourcedValue or plain string)
    const dateA = extractDateValue(a.date)
    const dateB = extractDateValue(b.date)
    const yearA = dateA.substring(0, 4) || '0'
    const yearB = dateB.substring(0, 4) || '0'
    const diff = parseInt(yearA) - parseInt(yearB)
    // Return based on sort order: ascending (oldest first) or descending (newest first)
    return sortAscending.value ? diff : -diff
  })
})

// Helper to extract string value for search
const extractStringValue = (field: SourcedValue<string> | string | undefined): string => {
  if (!field) return ''
  if (typeof field === 'string') return field
  if (typeof field === 'object' && field.value) return String(field.value)
  return ''
}

// Helper to convert string | SourcedValue<string> to SourcedValue<string>
const toSourcedValue = (field: string | SourcedValue<string> | undefined): SourcedValue<string> => {
  if (!field) return { value: '', source: '' }
  if (typeof field === 'string') return { value: field, source: '' }
  return field
}

// Normalize events to ensure all fields are SourcedValue<string>
const normalizedEvents = computed(() => {
  return getTimelineEvents.value.map((event) => ({
    date: toSourcedValue(event.date),
    title: toSourcedValue(event.title),
    description: toSourcedValue(event.description),
    category: toSourcedValue(event.category),
    location: event.location ? toSourcedValue(event.location) : undefined,
    impact: event.impact ? toSourcedValue(event.impact) : undefined,
    source: event.source,
  }))
})

const filteredEvents = computed(() => {
  if (!searchQuery.value.trim()) {
    return normalizedEvents.value
  }

  const query = searchQuery.value.toLowerCase().trim()

  return normalizedEvents.value.filter((event) => {
    // Search in title, description, location, and category (handle SourcedValue)
    const title = extractStringValue(event.title).toLowerCase()
    const description = extractStringValue(event.description).toLowerCase()
    const location = extractStringValue(event.location).toLowerCase()
    const category = extractStringValue(event.category).toLowerCase()

    return (
      title.includes(query) ||
      description.includes(query) ||
      location.includes(query) ||
      category.includes(query)
    )
  })
})

const hasTimelineData = computed(() => {
  const timelineData = company.value?.timeline
  if (!timelineData) return false

  // Check if there's any meaningful timeline data
  return !!(timelineData.events && timelineData.events.length > 0)
})
</script>
