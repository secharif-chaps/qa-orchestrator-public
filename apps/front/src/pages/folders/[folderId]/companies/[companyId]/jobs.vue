<template>
  <div class="text-neutral-black-font flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState v-else-if="!hasJobsData" :title="$t('screen.profile.sections.jobs.noData')" />

    <!-- Main content -->
    <div v-if="hasJobsData" class="gap-xl flex flex-col">
      <SectionTitle :title="$t('screen.jobs.title')" />

      <!-- Total Openings -->
      <Alert
        v-if="jobOffersInsights"
        icon="fa-regular fa-suitcase"
        :title="$t('screen.jobs.insights.totalOpenings')"
      >
        <template #aside>
          <span class="text-sm">
            {{ getSourcedValue(company?.jobs?.insights?.total_openings) ?? jobOffers.length }}
          </span>
        </template>
      </Alert>

      <!-- Insights Section -->
      <SectionCard>
        <div class="gap-xl flex flex-col">
          <div class="flex flex-col gap-2">
            <SectionTitle :title="$t('screen.jobs.insights.topDepartments')" />
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="department in topDepartmentsList"
                :key="department"
                intent="neutral"
                :label="department"
                size="sm"
              />
            </div>
          </div>
          <div>
            <SectionTitle :title="$t('screen.jobs.insights.hiringFocus')" />
            <p class="text-justify text-base">
              {{ getSourcedValue(company?.jobs?.insights?.hiring_focus) }}
            </p>
          </div>
          <div>
            <SectionTitle :title="$t('screen.jobs.insights.growthIndicators')" />
            <p class="text-justify text-base">
              {{ getSourcedValue(company?.jobs?.insights?.growth_indicators) }}
            </p>
          </div>
        </div>
      </SectionCard>

      <!-- Job Listings with Search -->
      <SectionCard>
        <template #header>
          <div class="flex items-center justify-between">
            <SectionTitle :title="$t('screen.jobs.listings.title')" />
            <div class="flex items-center gap-3">
              <Searchbar
                id="jobs-search"
                v-model="searchQuery"
                :placeholder="$t('screen.jobs.listings.search.placeholder')"
              />
              <div class="flex items-center gap-1 text-sm">
                <Icon icon="fa-regular fa-building" />
                {{ filteredJobs.length }}
              </div>
              <Toggle v-model="viewMode" :options="viewModeOptions" variant="pill" />
            </div>
          </div>
        </template>

        <!-- Grid View -->
        <TransitionGroup
          v-if="viewMode === 'grid'"
          name="job-list"
          tag="div"
          class="grid grid-cols-1 gap-4 md:grid-cols-1 lg:grid-cols-2"
        >
          <JobCard v-for="job in filteredJobs" :key="extractStringValue(job.title)" :job="job" />
        </TransitionGroup>

        <!-- Table View -->
        <JobsTableView v-else :jobs="filteredJobs" />

        <!-- No results message -->
        <EmptyState
          v-if="filteredJobs.length === 0 && searchQuery"
          :title="$t('screen.jobs.listings.noResults', { query: searchQuery })"
        />
      </SectionCard>
    </div>
  </div>
</template>

<script lang="ts" setup>
import JobCard from '@/components/company/jobs/JobCard.vue'
import JobsTableView from '@/components/company/jobs/JobsTableView.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Alert from '@/components/ui/Alert.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import { Icon, Tag, Toggle } from '@owlint/feathers-vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import type { SourcedValue } from '@/types/company'
import { Searchbar } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { refDebounced } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute('/folders/[folderId]/companies/[companyId]/jobs')

const companyId = computed(() => route.params.companyId)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'jobs'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const searchQuery = ref('')
const debouncedSearchQuery = refDebounced(searchQuery, 300)
const viewMode = ref<'grid' | 'table'>('grid')

const viewModeOptions = computed(() => [
  {
    value: 'grid',
    label: t('screen.jobs.listings.view.grid'),
    icon: 'fa fa-th-large',
  },
  {
    value: 'table',
    label: t('screen.jobs.listings.view.table'),
    icon: 'fa fa-table',
  },
])

const jobOffers = computed(() => {
  return company.value?.jobs?.offers || []
})

const jobOffersInsights = computed(() => {
  return company.value?.jobs?.insights
})

// Handle top_departments which can be array or comma-separated string
const topDepartmentsList = computed((): string[] => {
  const rawValue = getSourcedValue(company.value?.jobs?.insights?.top_departments)
  if (!rawValue) return []
  // If it's already an array, return it
  if (Array.isArray(rawValue)) return rawValue
  // If it's a string, split by comma
  if (typeof rawValue === 'string') {
    return rawValue
      .split(',')
      .map((s) => s.trim())
      .filter((s) => s.length > 0)
  }
  return []
})

// Helper to extract string value from SourcedValue or plain string
const extractStringValue = (field: SourcedValue<string> | string | undefined): string => {
  if (!field) return ''
  if (typeof field === 'string') return field
  if (typeof field === 'object' && field.value) return String(field.value)
  return ''
}

// Filter jobs based on search query
const filteredJobs = computed(() => {
  if (!debouncedSearchQuery.value.trim()) {
    return jobOffers.value
  }

  const query = debouncedSearchQuery.value.toLowerCase().trim()

  return jobOffers.value.filter((job) => {
    const title = extractStringValue(job.title).toLowerCase()
    const department = extractStringValue(job.department).toLowerCase()
    const location = extractStringValue(job.location).toLowerCase()
    const description = extractStringValue(job.description).toLowerCase()
    const requirements = extractStringValue(job.requirements).toLowerCase()

    return (
      title.includes(query) ||
      department.includes(query) ||
      location.includes(query) ||
      description.includes(query) ||
      requirements.includes(query)
    )
  })
})

const hasJobsData = computed(() => {
  const jobsData = company.value?.jobs
  if (!jobsData) return false

  // Check if there's any meaningful content
  return !!(jobsData.insights || (jobsData.offers && jobsData.offers.length > 0))
})
</script>

<style>
.job-list-move, /* apply transition to moving elements */
.job-list-enter-active,
.job-list-leave-active {
  transition: all 0.5s ease;
}

.job-list-enter-from,
.job-list-leave-to {
  opacity: 0;
}
</style>
