<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <PageState 
      v-if="taskState.isLoading.value" 
      state="loading" 
      page-type="jobs"
      :task-progress="taskState.taskProgress.value"
    />
    
    <!-- Error State -->
    <PageState
      v-else-if="taskState.hasErrors.value"
      state="error"
      page-type="jobs"
      :error-message="taskState.errorMessages.value[0]"
      @retry="handleRetry"
    />
    
    <!-- No Data State -->
    <PageState 
      v-else-if="!hasJobOffersData" 
      state="no-data" 
      page-type="jobs"
    />

    <!-- Main content -->
    <div v-if="hasJobOffersData" class="space-y-6">
      <!-- Insights Section -->
      <div class="bg-base-100 rounded-lg p-4" v-if="jobOffersInsights">
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
          <i class="fa fa-chart-line text-primary"></i>
          <span>{{ $t('jobs.insights.title') }}</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="p-4 bg-base-300 border border-slate-200 dark:border-slate-700 rounded-lg">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.totalOpenings') }}</h4>
            <p class="text-2xl font-bold text-primary">
              {{ getSourcedValue(company?.jobs?.insights?.total_openings) }}
            </p>
          </div>
          <div class="p-4 bg-base-300 border border-slate-200 dark:border-slate-700 rounded-lg">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.topDepartments') }}</h4>
            <div class="text-sm text-primary-light-content">
              <ul class="list-disc list-inside space-y-1">
                <li
                  v-for="department in getSourcedValue(company?.jobs?.insights?.top_departments)"
                  :key="department"
                >
                  {{ department }}
                </li>
              </ul>
              <div class="mt-2">
                <span class="text-xs italic text-primary-light-content">
                  Source: {{ getSourcedSource(company?.jobs?.insights?.top_departments) }}
                </span>
              </div>
            </div>
          </div>
          <div class="p-4 bg-base-300 border border-slate-200 dark:border-slate-700 rounded-lg">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.hiringFocus') }}</h4>
            <p class="text-sm text-primary-light-content">
              {{ getSourcedValue(company?.jobs?.insights?.hiring_focus) }}
            </p>
          </div>
          <div class="p-4 bg-base-300 border border-slate-200 dark:border-slate-700 rounded-lg">
            <h4 class="text-sm font-semibold">{{ $t('jobs.insights.growthIndicators') }}</h4>
            <p class="text-sm text-primary-light-content">
              {{ getSourcedValue(company?.jobs?.insights?.growth_indicators) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Job Listings with Search -->
      <div class="bg-base-100 rounded-lg p-4">
        <div class="flex items-center justify-between mb-6">
          <div class="flex gap-2 items-center">
            <i class="fa fa-list text-primary"></i>
            <span class="text-lg font-semibold">{{ $t('jobs.listings.title') }}</span>
          </div>
          <div class="w-64">
            <OInput
              icon="fa-search"
              id="search"
              v-model="searchQuery"
              :placeholder="$t('jobs.listings.search.placeholder')"
            />
          </div>
        </div>

        <!-- Job listings with transition group -->
        <TransitionGroup
          name="job-list"
          tag="div"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
        >
          <JobCard v-for="job in filteredJobs" :key="job.title" :job="job" />

          <!-- No results message -->
          <div
            v-if="filteredJobs.length === 0 && searchQuery"
            key="no-results"
            class="col-span-full"
          >
            <PageState
              state="no-results"
              :search-query="searchQuery"
              @clear-search="searchQuery = ''"
            />
          </div>
        </TransitionGroup>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { getSourcedSource, getSourcedValue } from '@/components/helpers/sourcedValues'
import { useTaskState, hasDataForSection } from '@/composables/useTaskState'
import JobCard from '@/components/company/jobs/JobCard.vue'
import PageState from '@/components/company/PageState.vue'
import { OInput } from '@owlint/feathers-vue'
import { useRestartTask } from '@/mutations/tasks'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

// Use the company data composable
const { data: company } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
  }),
  {
    // Poll every 5 seconds when any task is running
    refetchInterval: () => {
      const hasRunningTasks = company.value?.tasks?.some(
        (t) => t.status === 'running' || t.status === 'pending'
      )
      return hasRunningTasks ? 5000 : false
    },
  }
)

// Task state management
const taskState = useTaskState(company, 'jobs')

const searchQuery = ref('')

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

// Handle retry action
const handleRetry = async () => {
  const task = company.value?.tasks?.find((t) => t.type === 'jobs')
  if (task) {
    console.log('🔄 Retrying jobs task:', task.id)
    try {
      await restartTaskMutation(task.id)
      console.log('✅ Jobs task restarted successfully')
    } catch (error) {
      console.error('❌ Error restarting jobs task:', error)
    }
  } else {
    console.warn('⚠️ Jobs task not found')
  }
}

const hasJobOffersData = computed(() => {
  return hasDataForSection(company.value, 'jobs', 'offers')
})

const jobOffers = computed(() => {
  return company.value?.jobs?.offers || []
})

const jobOffersInsights = computed(() => {
  return company.value?.jobs?.insights
})

// Filter jobs based on search query
const filteredJobs = computed(() => {
  if (!searchQuery.value.trim()) {
    return jobOffers.value
  }

  const query = searchQuery.value.toLowerCase().trim()

  return jobOffers.value.filter((job) => {
    return (
      job.title?.toLowerCase().includes(query) ||
      job.department?.toLowerCase().includes(query) ||
      job.location?.toLowerCase().includes(query) ||
      job.description?.toLowerCase().includes(query) ||
      job.requirements?.toLowerCase().includes(query)
    )
  })
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
