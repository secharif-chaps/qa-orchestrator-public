<template>
  <LayoutsCompanyCard :title="$t('jobs.title')" icon="fa-briefcase">
    <div class="flex flex-col gap-4">
      <!-- Task state -->
      <TaskState
        v-if="companyId"
        :company-id="companyId"
        :required-task-types="['jobs']"
        :loading-title="$t('jobs.loading.title')"
        :loading-description="$t('jobs.loading.description')"
      />

      <!-- Empty state -->
      <Card v-if="!hasJobOffersData && !jobOffersPending">
        <div class="text-center py-8">
          <div class="text-5xl text-slate-300 dark:text-slate-600 mb-4">
            <i class="fa fa-briefcase"></i>
          </div>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ $t('jobs.noData.title') }}</h3>
          <p class="text-slate-500 dark:text-slate-400 mb-6">
            {{ $t('jobs.noData.description') }}
          </p>
        </div>
      </Card>

      <!-- Main content -->
      <div v-if="hasJobOffersData" class="space-y-6">
        <!-- Insights Section -->
        <Card v-if="jobOffersInsights">
          <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-4 flex items-center gap-2">
            <i class="fa fa-chart-line text-primary"></i>
            <span>{{ $t('jobs.insights.title') }}</span>
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg">
              <div class="text-sm text-slate-600 dark:text-slate-400">{{ $t('jobs.insights.totalOpenings') }}</div>
              <div class="text-2xl font-bold text-primary">
                {{ getSourcedValue(company?.jobs?.insights?.total_openings) }}
              </div>
            </div>
            <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg">
              <div class="text-sm text-slate-600 dark:text-slate-400">{{ $t('jobs.insights.topDepartments') }}</div>
              <div class="text-sm text-slate-700 dark:text-slate-300">
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="department in getSourcedValue(company?.jobs?.insights?.top_departments)" :key="department">
                    {{ department }}
                  </li>
                </ul>
                <div class="mt-2">
                  <span class="text-xs italic text-slate-500 dark:text-slate-400">
                    Source: {{getSourcedSource(company?.jobs?.insights?.top_departments)}}
                  </span>
                </div>
              </div>
            </div>
            <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg">
              <div class="text-sm text-slate-600 dark:text-slate-400">{{ $t('jobs.insights.hiringFocus') }}</div>
              <div class="text-sm text-slate-700 dark:text-slate-300">
                {{ getSourcedValue(company?.jobs?.insights?.hiring_focus) }}
              </div>
            </div>
            <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg">
              <div class="text-sm text-slate-600 dark:text-slate-400">{{ $t('jobs.insights.growthIndicators') }}</div>
              <div class="text-sm text-slate-700 dark:text-slate-300">
                {{ getSourcedValue(company?.jobs?.insights?.growth_indicators) }}
              </div>
            </div>
          </div>
        </Card>

        <!-- Job Listings with Search -->
        <Card>
          <div class="flex items-center justify-between mb-6">
            <div class="flex gap-2 items-center text-primary">
              <i class="fa fa-list"></i>
              <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('jobs.listings.title') }}</span>
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
              class="col-span-full text-center py-8"
            >
              <i class="fa fa-search text-3xl text-slate-300 dark:text-slate-600 mb-3"></i>
              <p class="text-slate-500 dark:text-slate-400">{{ $t('jobs.listings.noResults', { query: searchQuery }) }}</p>
            </div>
          </TransitionGroup>
        </Card>
      </div>
    </div>
  </LayoutsCompanyCard>
</template>

<script lang="ts" setup>
import { OInput } from '@owlint/feathers-vue'
import JobCard from '~/components/JobCard.vue'
import TaskState from '~/components/TaskState.vue'
import Source from '~/components/global/Source.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Set page metadata
useHead({
  title: `Mint - ${t('jobs.title')}`,
  meta: [{ name: 'description', content: t('jobs.title') }]
})

const searchQuery = ref('')
const { company, companyId, getSourcedValue, getSourcedSource, fetchCompany } = useCompanyData()

onMounted(async () => {
  if (!company.value) {
    await fetchCompany()
  }
})


const jobOffersPending = computed(() => {
  if (!companyId.value) {
    return false
  }
  return company.value?.tasks?.some(
    (task: { type: string; status: string }) => task.type === 'jobs' && (task.status === 'pending' || task.status === 'running')
  )
})

const hasJobOffersData = computed(() => {
  return !!company.value?.jobs?.offers && company.value.jobs.offers.length > 0
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

  return jobOffers.value.filter((job: any) => {
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
