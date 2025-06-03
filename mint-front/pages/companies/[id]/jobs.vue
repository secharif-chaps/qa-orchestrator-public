<template>
  <LayoutsCompanyCard title="Job Offers" icon="fa-briefcase">
    <div class="flex flex-col gap-4">
      <!-- Task state -->
      <TaskState
        v-if="companyId"
      :company-id="companyId"
      :required-task-types="['jobs']"
      loading-title="Loading job offers..."
      loading-description="Fetching current job opportunities..."
    />

    <!-- Empty state -->
    <Card v-if="!hasJobOffersData && !jobOffersPending">
      <div class="text-center py-8">
        <div class="text-5xl text-slate-300 mb-4">
          <i class="fa fa-briefcase"></i>
        </div>
        <h3 class="text-xl font-semibold mb-2">No Job Offers Available</h3>
        <p class="text-slate-500 mb-6">
          Fetch job offers for this company to see current opportunities
        </p>
      </div>
    </Card>

    <!-- Main content -->
    <div v-if="hasJobOffersData" class="space-y-6">
      <!-- Insights Section -->
      <Card v-if="jobOffersInsights">
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
          <i class="fa fa-chart-line text-primary"></i>
          <span>Hiring Insights</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="p-4 bg-slate-50 rounded-lg">
            <div class="text-sm text-slate-600">Total Openings</div>
            <div class="text-2xl font-bold text-primary">
              {{ getSourcedValue(company?.jobs?.insights?.total_openings) }}
            </div>
          </div>
          <div class="p-4 bg-slate-50 rounded-lg">
            <div class="text-sm text-slate-600">Top Departments</div>
            <div class="text-sm">
              <ul class="list-disc list-inside">
                {{
                  getSourcedValue(company?.jobs?.insights?.top_departments)
                }}
                <div>
                  {{ getSourcedSource(company?.jobs?.insights?.top_departments) }}
                </div>
              </ul>
            </div>
          </div>
          <div class="p-4 bg-slate-50 rounded-lg">
            <div class="text-sm text-slate-600">Hiring Focus</div>
            <div class="text-sm">
              {{ getSourcedValue(company?.jobs?.insights?.hiring_focus) }}
            </div>
          </div>
          <div class="p-4 bg-slate-50 rounded-lg">
            <div class="text-sm text-slate-600">Growth Indicators</div>
            <div class="text-sm">
              {{ getSourcedValue(company?.jobs?.insights?.growth_indicators) }}
            </div>
          </div>
        </div>
      </Card>

      <!-- Job Listings with Search -->
      <Card>
        <div class="flex items-center justify-between mb-4">
          <div class="flex gap-2 items-center text-primary">
            <i class="fa fa-list"></i>
            <span>Current Openings</span>
          </div>
          <div>
            <OInput
              icon="fa-search"
              id="search"
              v-model="searchQuery"
              placeholder="Search jobs..."
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
            class="text-center py-4"
          >
            <p class="text-slate-500">No job offers found matching "{{ searchQuery }}"</p>
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

// Set page metadata
useHead({
  title: 'Mint - Job Offers',
  meta: [{ name: 'description', content: 'Company Job Opportunities' }]
})

const searchQuery = ref('')
const { company, companyId, getSourcedValue, getSourcedSource, fetchCompany } = useCompanyData()

onMounted(async () => {
  await fetchCompany()
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
