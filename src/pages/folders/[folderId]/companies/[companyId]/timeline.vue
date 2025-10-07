<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />
    <!-- Error State -->
    <SectionErrorState
      v-if="company && task?.status === 'error'"
      :error-message="task.error"
      :task="task"
    />

    <!-- Timeline visualization -->
    <div v-if="hasTimelineData" class="relative">
      <!-- Timeline events -->
      <div class="bg-base-100 p-4 rounded-lg">
        <div class="flex items-center justify-between mb-6">
          <div class="flex gap-2 items-center">
            <i class="fa fa-list"></i>
            <span class="text-lg font-semibold">{{ $t('timeline.title') }}</span>
          </div>
          <div class="w-64 relative">
            <i
              class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-primary-light-content"
            ></i>
            <Input
              v-model="searchQuery"
              :placeholder="$t('timeline.search.placeholder')"
              icon="fa fa-search"
            />
          </div>
        </div>
        <div>
          <Event v-for="(event, index) in filteredEvents" :key="index" :event="event" />
        </div>

        <div v-if="filteredEvents.length === 0 && searchQuery">todo empty state</div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref } from 'vue'
import { hasDataForSection } from '@/composables/useTaskState'
import Event from '@/components/company/timeline/Event.vue'
import { companyTasksQuery } from '@/queries/tasks'
import Input from '@/components/ui/Input.vue'
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

const hasTimelineData = computed(() => {
  return hasDataForSection(company.value, 'timeline', 'events')
})

const getTimelineEvents = computed(() => {
  if (!company.value?.timeline?.events) return []

  // Sort events by date (oldest to newest)
  return [...company.value.timeline.events].sort((a, b) => {
    // Extract just the year if it's the only format available
    const yearA = a.date.substring(0, 4)
    const yearB = b.date.substring(0, 4)
    return parseInt(yearA) - parseInt(yearB)
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
</script>
