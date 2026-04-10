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
      <p class="text-neutral-black-font text-lg font-medium">
        {{ t('screen.profile.sections.timeline.noData') }}
      </p>
    </NoData>

    <!-- Timeline visualization -->
    <div v-else class="space-y-xl relative">
      <div class="gap-xl flex items-center justify-between">
        <div v-if="company" class="text-neutral-black-font flex items-center gap-2">
          <Badge icon="fa-pen" variant="secondary" size="xs" />
          <span class="text-sm">
            {{
              t('screen.company.footer.createdBy', {
                username: company.owner_username,
                date: formatFullDate(company.created_at),
              })
            }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <Searchbar
            v-model="searchQuery"
            class="w-64"
            id="timeline-search"
            :placeholder="t('screen.timeline.search.placeholder')"
          />
          <Button
            variant="tertiary"
            :icon="sortAscending ? 'fa-arrow-up' : 'fa-arrow-down'"
            size="sm"
            @click="toggleSortOrder"
          >
            {{
              sortAscending
                ? t('screen.timeline.sort.oldestFirst')
                : t('screen.timeline.sort.newestFirst')
            }}
          </Button>
        </div>
      </div>
      <!-- Timeline events -->
      <div>
        <TimelineEvent v-for="(event, index) in filteredEvents" :key="index" :event="event" />

        <!-- No results message -->
        <div v-if="filteredEvents.length === 0 && searchQuery">
          <NoData>
            <p class="text-neutral-black-font text-lg font-medium">
              {{ t('screen.timeline.search.noResults', { query: searchQuery }) }}
            </p>
          </NoData>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import TimelineEvent from '@/components/company/timeline/TimelineEvent.vue'
import NoData from '@/components/ui/NoData.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { SourcedValue } from '@/types/company'
import { formatFullDate } from '@/utils/time'
import { Badge, Button, Searchbar } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/timeline')
const { t } = useI18n()

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
