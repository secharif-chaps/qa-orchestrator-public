<template>
  <div class="flex flex-col gap-4">
    <!-- Main content -->
    <div class="bg-bg1 p-4 rounded-lg" v-if="!hasTimelineData && !timelinePending">
      <div class="text-center py-8">
        <div class="text-5xl text-secondary mb-4">
          <i class="fa fa-calendar-days"></i>
        </div>
        <h3 class="text-xl font-semibold text-primary mb-2">{{ $t('timeline.noData.title') }}</h3>
        <p class="text-secondary mb-6">
          {{ $t('timeline.noData.description') }}
        </p>
      </div>
    </div>

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
        <div
          v-if="filteredEvents.length === 0 && searchQuery"
          class="text-center py-8 border-2 border-dashed border-border-2 rounded-lg"
        >
          <i class="fa fa-search text-3xl text-secondary mb-3"></i>
          <p class="text-secondary">
            {{ $t('timeline.search.noResults', { query: searchQuery }) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import CompanyCard from '@/components/company/CompanyCard.vue'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed, ref } from 'vue'
import Event from '@/components/company/timeline/Event.vue'
import { OInput } from '@owlint/feathers-vue'

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
