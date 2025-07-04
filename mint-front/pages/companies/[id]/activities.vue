<template>
  <LayoutsCompanyCard :title="$t('timeline.title')" icon="fa-calendar-days">
    <div class="flex flex-col gap-4">
      <!-- Task state -->
      <TaskState
        v-if="companyId"
        :company-id="companyId"
        :required-task-types="['timeline']"
        :loading-title="$t('timeline.loading.title')"
        :loading-description="$t('timeline.loading.description')"
      />

      <!-- Main content -->
      <Card v-if="!hasTimelineData && !timelinePending">
        <div class="text-center py-8">
          <div class="text-5xl text-slate-300 dark:text-slate-600 mb-4">
            <i class="fa fa-calendar-days"></i>
          </div>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ $t('timeline.noData.title') }}</h3>
          <p class="text-slate-500 dark:text-slate-400 mb-6">
            {{ $t('timeline.noData.description') }}
          </p>
        </div>
      </Card>

      <!-- Timeline visualization -->
      <div v-if="hasTimelineData" class="relative">
        <!-- Timeline events -->
        <Card>
          <div class="flex items-center justify-between mb-6">
            <div class="flex gap-2 items-center text-primary">
              <i class="fa fa-list"></i>
              <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('timeline.title') }}</span>
            </div>
            <div class="w-64">
              <OInput icon="fa-search" id="search" v-model="searchQuery" :placeholder="$t('timeline.search.placeholder')" />
            </div>
          </div>
          <div>
            <TimelineEvent v-for="(event, index) in filteredEvents" :key="index" :event="event" />
          </div>
          <div v-if="filteredEvents.length === 0 && searchQuery" class="text-center py-8">
            <i class="fa fa-search text-3xl text-slate-300 dark:text-slate-600 mb-3"></i>
            <p class="text-slate-500 dark:text-slate-400">{{ $t('timeline.search.noResults', { query: searchQuery }) }}</p>
          </div>
        </Card>
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OAlert, OButton, OInput } from '@owlint/feathers-vue'
import TaskState from '~/components/TaskState.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: t('timeline.title'),
  meta: [
    {
      name: 'description',
      content: t('timeline.title')
    }
  ]
})

const { company, companyId, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
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
