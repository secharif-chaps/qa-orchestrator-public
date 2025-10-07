<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <PageState
      v-if="taskState.isLoading.value"
      state="loading"
      page-type="timeline"
      :task-progress="taskState.taskProgress.value"
    />

    <!-- Error State -->
    <PageState
      v-else-if="taskState.hasErrors.value"
      state="error"
      page-type="timeline"
      :error-message="taskState.errorMessages.value[0]"
      @retry="handleRetry"
    />

    <!-- No Data State -->
    <PageState v-else-if="!hasTimelineData" state="no-data" page-type="timeline" />

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
            <input
              v-model="searchQuery"
              :placeholder="$t('timeline.search.placeholder')"
              class="w-full sm:w-64 bg-base-300 border border-primary-stroke rounded-md p-2 pl-8 focus:outline-none focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 ring-primary ring-offset-bg3"
            />
          </div>
        </div>
        <div>
          <Event v-for="(event, index) in filteredEvents" :key="index" :event="event" />
        </div>
        <PageState
          v-if="filteredEvents.length === 0 && searchQuery"
          state="no-results"
          :search-query="searchQuery"
          @clear-search="searchQuery = ''"
        />
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref, watch } from 'vue'
import { useTaskState, hasDataForSection } from '@/composables/useTaskState'
import Event from '@/components/company/timeline/Event.vue'
import PageState from '@/components/company/PageState.vue'
import { useRestartTask } from '@/mutations/tasks'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

// Use the company data composable with automatic refetching when task is running
const { data: company, refetch } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
  }),
  {
    // Poll every 5 seconds when any task is running
    refetchInterval: () => {
      const hasRunningTasks = company.value?.tasks?.some(
        (t) => t.status === 'running' || t.status === 'pending',
      )
      return hasRunningTasks ? 5000 : false
    },
  },
)

// Task state management
const taskState = useTaskState(company, 'timeline')

const searchQuery = ref('')

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

// Handle retry action
const handleRetry = async () => {
  const task = company.value?.tasks?.find((t) => t.type === 'timeline')
  if (task) {
    console.log('🔄 Retrying timeline task:', task.id)
    try {
      await restartTaskMutation(task.id)
      console.log('✅ Timeline task restarted successfully')
    } catch (error) {
      console.error('❌ Error restarting timeline task:', error)
    }
  } else {
    console.warn('⚠️ Timeline task not found')
  }
}

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
