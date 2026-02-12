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
    <NoData v-else-if="!hasJobsData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.jobs.noData') }}
      </p>
    </NoData>

    <!-- Main content -->
    <div v-if="hasJobsData" class="space-y-6">
      <!-- Insights Section -->
      <div class="rounded-lg p-4" v-if="jobOffersInsights">
        <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold">
          <span>{{ $t('jobs.insights.title') }}</span>
        </h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <div class="bg-sage-light border-base-300 rounded-lg border p-4">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.totalOpenings') }}</h4>
            <p class="text-secondary text-2xl font-bold">
              {{ getSourcedValue(company?.jobs?.insights?.total_openings) }}
            </p>
          </div>
          <div class="bg-sage-light border-base-300 rounded-lg border p-4">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.topDepartments') }}</h4>
            <div class="text-secondary text-sm">
              <ul class="list-inside list-disc space-y-1">
                <li v-for="department in topDepartmentsList" :key="department">
                  {{ department }}
                </li>
              </ul>
              <div class="mt-2"></div>
            </div>
          </div>
          <div class="bg-sage-light border-base-300 rounded-lg border p-4">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.hiringFocus') }}</h4>
            <p class="text-secondary text-sm">
              {{ getSourcedValue(company?.jobs?.insights?.hiring_focus) }}
            </p>
          </div>
          <div class="bg-sage-light border-base-300 rounded-lg border p-4">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.growthIndicators') }}</h4>
            <p class="text-secondary text-sm">
              {{ getSourcedValue(company?.jobs?.insights?.growth_indicators) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Job Listings with Search -->
      <div class="rounded-lg p-4">
        <div class="mb-6 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-lg font-semibold">{{ $t('jobs.listings.title') }}</span>
          </div>
          <div class="w-64">
            <Searchbar
              id="jobs-search"
              v-model="searchQuery"
              :placeholder="$t('jobs.listings.search.placeholder')"
            />
          </div>
        </div>

        <!-- Job listings with transition group -->
        <TransitionGroup
          name="job-list"
          tag="div"
          class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
          <JobCard v-for="job in filteredJobs" :key="job.title" :job="job" />

          <!-- No results message -->
          <div
            v-if="filteredJobs.length === 0 && searchQuery"
            key="no-results"
            class="col-span-full"
          >
            <NoData>
              <p class="text-secondary text-lg font-medium">
                {{ $t('jobs.listings.noResults', { query: searchQuery }) }}
              </p>
            </NoData>
          </div>
        </TransitionGroup>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref, inject } from 'vue'
import type { Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import JobCard from '@/components/company/jobs/JobCard.vue'
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import { Input, Searchbar } from '@owlint/feathers-vue'
import NoData from '@/components/ui/NoData.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

// Inject selected language from parent [companyId].vue
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'jobs'))

// Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
// No polling needed - cache is invalidated automatically when tasks update.
const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
  language: selectedLanguage.value,
}))

const searchQuery = ref('')

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
const extractStringValue = (field: any): string => {
  if (!field) return ''
  if (typeof field === 'string') return field
  if (typeof field === 'object' && field.value) return String(field.value)
  return ''
}

// Filter jobs based on search query
const filteredJobs = computed(() => {
  if (!searchQuery.value.trim()) {
    return jobOffers.value
  }

  const query = searchQuery.value.toLowerCase().trim()

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
  transform: translateX(-30px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.job-list-leave-active {
  position: absolute;
}
</style>
