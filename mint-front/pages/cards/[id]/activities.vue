<template>
  <LayoutsCompanyCard title="Timeline & Key Milestones" icon="fa-calendar-days">
    <!-- Actions slot -->
    <template #actions>
      <OButton @click="refreshTimeline" type="secondary" icon="fa-refresh">Refresh</OButton>
    </template>

    <!-- Loading slot -->
    <template #loading>
      <div class="flex flex-col gap-2">
        <OAlert
          v-if="timelinePending"
          message="Loading company timeline data..."
          title="Please wait"
          icon="fa-spinner fa-spin"
          color="blue"
        >
          <p>Fetching milestone events data from AI agent...</p>
        </OAlert>

        <OAlert
          v-if="company?.timeline?.insights"
          title="Timeline Insights"
          :description="company?.timeline?.insights"
          icon="fa-magic"
          color="blue"
        >
        </OAlert>

        <OAlert
          v-if="company?.pending_states?.timeline?.error"
          title="Oops, something went wrong"
          description="Please try again later or contact support"
          icon="fa-exclamation-triangle"
          color="red"
        >
        </OAlert>
      </div>
    </template>

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
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert, OButton, OInput } from '@owlint/feathers-vue'

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
  return company.value?.pending_states?.timeline?.pending
})

const companyStore = useCompanyStore()

const refreshTimeline = () => {
  if (companyId.value) {
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
