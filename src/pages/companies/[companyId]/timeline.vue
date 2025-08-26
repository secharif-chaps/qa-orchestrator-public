<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <TimelineEmptyState v-if="timelinePending" type="loading" />
    
    <!-- No Data State -->
    <TimelineEmptyState v-else-if="!hasTimelineData" type="no-data" />

    <!-- Timeline visualization -->
    <div v-if="hasTimelineData" class="relative">
      <!-- Timeline events -->
      <div class="bg-bg1 p-4 rounded-lg">
        <div class="flex items-center justify-between mb-6">
          <div class="flex gap-2 items-center">
            <i class="fa fa-list"></i>
            <span class="text-lg font-semibold">{{ $t('timeline.title') }}</span>
          </div>
          <div class="w-64 relative">
            <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-secondary"></i>
            <input
              v-model="searchQuery"
              :placeholder="$t('timeline.search.placeholder')"
              class="w-full sm:w-64 bg-bg3 border border-border-2 rounded-md p-2 pl-8 focus:outline-none focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 ring-primary ring-offset-bg3"
            />
          </div>
        </div>
        <div>
          <Event v-for="(event, index) in filteredEvents" :key="index" :event="event" />
        </div>
        <TimelineEmptyState
          v-if="filteredEvents.length === 0 && searchQuery"
          type="no-results"
          :search-query="searchQuery"
        />
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
import TimelineEmptyState from '@/components/company/timeline/TimelineEmptyState.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

// Use the company data composable
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

const searchQuery = ref('')

const timelinePending = computed(() => {
  if (!companyId.value) {
    return false
  }
  return company.value?.tasks?.some(
    (task: { type: string; status: string }) =>
      task.type === 'timeline' && (task.status === 'pending' || task.status === 'running'),
  )
})

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
