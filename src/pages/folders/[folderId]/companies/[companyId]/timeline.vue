<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />
    <!-- Error State -->
    <SectionErrorState
      v-else-if="company && task?.status === 'error'"
      :error-message="task.error"
      :task="task"
    />

    <!-- No Data State -->
    <NoData v-else-if="!hasTimelineData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.timeline.noData') }}
      </p>
    </NoData>

    <!-- Timeline visualization -->
    <div v-else class="relative">
      <!-- Timeline events -->
      <div class="bg-base-100 p-4 rounded-lg">
        <div class="flex items-center justify-between mb-6">
          <div class="flex gap-2 items-center">
            <i class="fa fa-list"></i>
            <span class="text-lg font-semibold">{{ $t('timeline.title') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="tertiary"
              :icon="sortAscending ? 'fa fa-arrow-up' : 'fa fa-arrow-down'"
              icon-only
              size="sm"
              :title="sortAscending ? 'Oldest first' : 'Newest first'"
              @click="toggleSortOrder"
            />
            <div class="w-64 relative">
              <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-secondary"></i>
              <Input
                v-model="searchQuery"
                :placeholder="$t('timeline.search.placeholder')"
                icon="fa fa-search"
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
import { computed, ref } from 'vue'
import Event from '@/components/company/timeline/Event.vue'
import { companyTasksQuery } from '@/queries/tasks'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import NoData from '@/components/ui/NoData.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: tasks, refetch: refetchTasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'timeline'))

// Use the company data composable with automatic refetching when task is running
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
  // Poll every 5 seconds when any task is running
  refetchInterval: () => {
    const hasRunningTasks = company.value?.tasks?.some(
      (t) => t.status === 'running' || t.status === 'pending',
    )
    return hasRunningTasks ? 5000 : false
  },
}))

const searchQuery = ref('')
const sortAscending = ref(false) // Default to newest first (descending)

const toggleSortOrder = () => {
  sortAscending.value = !sortAscending.value
}

const getTimelineEvents = computed(() => {
  if (!company.value?.timeline?.events) return []

  // Sort events by date
  return [...company.value.timeline.events].sort((a, b) => {
    // Extract just the year if it's the only format available
    const yearA = a.date.substring(0, 4)
    const yearB = b.date.substring(0, 4)
    const diff = parseInt(yearA) - parseInt(yearB)
    // Return based on sort order: ascending (oldest first) or descending (newest first)
    return sortAscending.value ? diff : -diff
  })
})

const filteredEvents = computed(() => {
  if (!searchQuery.value.trim()) {
    return getTimelineEvents.value
  }

  const query = searchQuery.value.toLowerCase().trim()

  return getTimelineEvents.value.filter((event) => {
    // Search in title, description, location, and category
    return (
      (event.title && event.title.toLowerCase().includes(query)) ||
      (event.description && event.description.toLowerCase().includes(query)) ||
      (event.location && event.location.toLowerCase().includes(query)) ||
      (event.category && event.category.toLowerCase().includes(query))
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
