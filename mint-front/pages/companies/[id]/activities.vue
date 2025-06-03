<template>
  <LayoutsCompanyCard title="Timeline & Key Milestones" icon="fa-calendar-days">
    <div class="flex flex-col gap-4">
      <!-- Task state -->
      <TaskState
        v-if="companyId"
        :company-id="companyId"
        :required-task-types="['timeline']"
        loading-title="Loading company timeline data..."
        loading-description="Fetching milestone events data from AI agent..."
      />

      <!-- Main content -->
      <Card v-if="!hasTimelineData && !timelinePending">
        <div class="text-center py-8">
          <div class="text-5xl text-slate-300 mb-4">
            <i class="fa fa-calendar-days"></i>
          </div>
          <h3 class="text-xl font-semibold mb-2">No Timeline Data Available</h3>
          <p class="text-slate-500 mb-6">
            Fetch milestone events for this company to see its history
          </p>
        </div>
      </Card>

      <!-- Timeline visualization -->
      <div v-if="hasTimelineData" class="relative">
        <!-- Timeline events -->
        <div class="card flex flex-col gap-4">
          <div>
            <div class="flex items-center justify-between">
              <div class="flex gap-2 items-center text-primary">
                <i class="fa fa-list"></i>
                <span>Timeline</span>
              </div>
              <div>
                <OInput icon="fa-search" id="search" v-model="searchQuery" placeholder="Search..." />
              </div>
            </div>
          </div>
          <div class="grid grid-cols-1">
            <TimelineEvent v-for="(event, index) in filteredEvents" :key="index" :event="event" />
          </div>
          <div v-if="filteredEvents.length === 0 && searchQuery" class="text-center py-4">
            <p class="text-slate-500">No events found matching "{{ searchQuery }}"</p>
          </div>
        </div>
      </div>
    </div>

  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert, OButton, OInput } from '@owlint/feathers-vue'
import TaskState from '~/components/TaskState.vue'

// Set page metadata
useHead({
  title: 'Mint - Timeline & Key Milestones',
  meta: [
    {
      name: 'description',
      content: 'Company History Timeline & Key Milestones'
    }
  ]
})

const { company, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  await fetchCompany()
})

const searchQuery = ref('')

const timelinePending = computed(() => {
  if (!companyId.value) {
    return false
  }
  return company.value?.tasks?.some(
    (task: { type: string; status: string }) => task.type === 'timeline' && (task.status === 'pending' || task.status === 'running')
  )
})

const companyStore = useCompanyStore()

const refreshTimeline = () => {
  if (companyId.value) {
    // @ts-ignore - startQuery exists in the store but TypeScript doesn't know about it
    companyStore.startQuery(companyId.value, 'timeline')
  }
}

const hasTimelineData = computed(() => {
  return !!company.value?.timeline?.events && company.value.timeline.events.length > 0
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

  return getTimelineEvents.value.filter(event => {
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
