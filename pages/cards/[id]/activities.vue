<template>
  <div class="space-y-6">
    <div>
      <OButton
        type="secondary"
        icon="fa-arrow-left"
        @click="$router.push(`/cards/${companyName}`)"
      >
        Back
      </OButton>
    </div>
    <div class="flex items-center justify-between">
      <div class="flex space-x-4 items-center">
        <OIcon
          icon="fa-calendar-days"
          type="secondary"
        ></OIcon>
        <h1 class="text-3xl">Timeline & Key Milestones</h1>
      </div>
      <div class="flex gap-2">
        <OButton
          @click="refreshTimeline"
          :loading="pending"
          label="Refresh Timeline"
          type="secondary"
          icon="fa-sync"
        />
        <Export />
      </div>
    </div>

    <!-- Loading indicator when nothing is available yet -->
    <OAlert
      v-if="pending"
      message="Loading company timeline data..."
      title="Please wait"
      icon="fa-spinner fa-spin"
      color="blue"
    >
      <p>Fetching milestone events data from AI agent...</p>
    </OAlert>

    <Card v-if="!hasTimelineData && !pending">
      <div class="text-center py-8">
        <div class="text-5xl text-slate-300 mb-4">
          <i class="fa fa-calendar-days"></i>
        </div>
        <h3 class="text-xl font-semibold mb-2">No Timeline Data Available</h3>
        <p class="text-slate-500 mb-6">
          Fetch milestone events for this company to see its history
        </p>
        <OButton
          @click="refreshTimeline"
          :loading="pending"
          label="Generate Timeline"
        />
      </div>
    </Card>

    <!-- Timeline visualization -->
    <div
      v-if="hasTimelineData"
      class="relative"
    >
      <!-- Timeline events -->
      <div class="card flex flex-col gap-4">
        <div>
          <div class="flex items-center justify-between">
            <div class="flex gap-2 items-center text-primary">
              <i class="fa fa-list"></i>
              <span>Timeline</span>
            </div>
            <div>
              <OInput
                icon="fa-search"
                id="search"
                v-model="searchQuery"
                placeholder="Search..."
              />
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1">
          <TimelineEvent
            v-for="(event, index) in filteredEvents"
            :key="index"
            :event="event"
          />
        </div>
        <div
          v-if="filteredEvents.length === 0 && searchQuery"
          class="text-center py-4"
        >
          <p class="text-slate-500">
            No events found matching "{{ searchQuery }}"
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OAlert, OButton, OIcon, OInput } from '@owlint/feathers-vue'
import { useCompanyStore } from '~/stores/company'

// Set page metadata
useHead({
  title: 'Mint - Timeline & Key Milestones',
  meta: [
    {
      name: 'description',
      content: 'Company History Timeline & Key Milestones',
    },
  ],
})

const searchQuery = ref('')

const { companyName } = useCompanyData()
const { generateTimeline, pending } = useAgent()
const companyStore = useCompanyStore()

const company = computed(() => {
  return companyStore.getCompanyByName(companyName.value)
})

const hasTimelineData = computed(() => {
  return (
    !!company.value?.timeline_events && company.value.timeline_events.length > 0
  )
})

const getTimelineEvents = computed(() => {
  if (!company.value?.timeline_events) return []

  // Sort events by date (oldest to newest)
  return [...company.value.timeline_events].sort((a, b) => {
    // Extract just the year if it's the only format available
    const yearA = a.date.substring(0, 4)
    const yearB = b.date.substring(0, 4)
    return parseInt(yearA) - parseInt(yearB)
  })
})

// Generate or refresh timeline data
const refreshTimeline = async () => {
  await generateTimeline(companyName.value)
}

// Check if timeline data exists on mount, if not, generate it
onMounted(async () => {
  if (!hasTimelineData.value) {
    await generateTimeline(companyName.value)
  }
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
