<template>
  <div class="min-h-screen">
    <!-- Loading State -->
    <PageState
      v-if="taskState.isLoading.value"
      state="loading"
      page-type="press"
      :task-progress="taskState.taskProgress.value"
    />

    <!-- Error State -->
    <PageState
      v-else-if="taskState.hasErrors.value"
      state="error"
      page-type="press"
      :error-message="taskState.errorMessages.value[0]"
      @retry="handleRetry"
    />

    <!-- No Data State -->
    <PageState v-else-if="!hasAnyPressData" state="no-data" page-type="press" />

    <!-- Main Content -->
    <div v-else class="mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div v-if="company?.press?.insights" class="lg:col-span-3">
          <ChapseAlert variant="mage" :title="$t('profile.sections.press.insights.title')">
            {{ company.press.insights }}
          </ChapseAlert>
        </div>
        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Press Insights -->

          <!-- Financial News -->
          <div v-if="company?.press?.financial_news?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-chart-line"></i>
              Financial News
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.financial_news"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Product Launches -->
          <div v-if="company?.press?.product_launches?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-rocket"></i>
              Product Launches
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.product_launches"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>

                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Executive Interviews -->
          <div v-if="company?.press?.executive_interviews?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-microphone"></i>
              Executive Interviews
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.executive_interviews"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Media Mentions -->
          <div v-if="company?.press?.media_mentions?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-newspaper"></i>
              Media Mentions
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.media_mentions"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Press Releases -->
          <div v-if="company?.press?.press_releases?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-file-alt"></i>
              Press Releases
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.press_releases"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Articles (backward compatibility) -->
          <div v-if="company?.press?.articles?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-newspaper"></i>
              Articles
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.articles"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Partnership Announcements -->
          <div
            v-if="company?.press?.partnership_announcements?.length"
            class="bg-base-100 rounded-lg p-6"
          >
            <h2 class="text-lg font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-handshake"></i>
              Partnership Announcements
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.partnership_announcements"
                :key="index"
                class="bg-base-200 rounded-lg p-3"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Awards & Recognition -->
          <div v-if="company?.press?.awards_recognition?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-primary flex items-center gap-2 mb-4">
              <i class="fa fa-trophy"></i>
              Awards & Recognition
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.awards_recognition"
                :key="index"
                class="bg-base-200 rounded-lg p-3"
              >
                <span class="text-primary-light-content mr-2">{{ item.value }}</span>
                <Source v-for="source in item.sources" :key="source" :source="source" />
              </div>
            </div>
          </div>

          <!-- Quick Stats -->
          <div class="bg-base-100 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-primary mb-4">Press Coverage Stats</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-primary-light-content text-sm">Financial News</span>
                <span class="text-primary font-medium">{{
                  company?.press?.financial_news?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-primary-light-content text-sm">Product Launches</span>
                <span class="text-primary font-medium">{{
                  company?.press?.product_launches?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-primary-light-content text-sm">Media Mentions</span>
                <span class="text-primary font-medium">{{
                  company?.press?.media_mentions?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-primary-light-content text-sm">Press Releases</span>
                <span class="text-primary font-medium">{{
                  company?.press?.press_releases?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-primary-light-content text-sm">Executive Interviews</span>
                <span class="text-primary font-medium">{{
                  company?.press?.executive_interviews?.length || 0
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - company.view
</route>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { useTaskState } from '@/composables/useTaskState'
import Source from '@/components/company/Source.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import PageState from '@/components/company/PageState.vue'
import { useRestartTask } from '@/mutations/tasks'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

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
const taskState = useTaskState(company, 'press')

// Restart task mutation
const { mutate: restartTaskMutation } = useRestartTask()

// Handle retry action
const handleRetry = async () => {
  const task = company.value?.tasks?.find((t) => t.type === 'press')
  if (task) {
    console.log('🔄 Retrying press task:', task.id)
    try {
      await restartTaskMutation(task.id)
      console.log('✅ Press task restarted successfully')
    } catch (error) {
      console.error('❌ Error restarting press task:', error)
    }
  } else {
    console.warn('⚠️ Press task not found')
  }
}

const hasAnyPressData = computed(() => {
  const press = company.value?.press
  if (!press) return false

  return !!(
    press.insights ||
    (press.articles && press.articles.length > 0) ||
    (press.press_releases && press.press_releases.length > 0) ||
    (press.media_mentions && press.media_mentions.length > 0) ||
    (press.awards_recognition && press.awards_recognition.length > 0) ||
    (press.product_launches && press.product_launches.length > 0) ||
    (press.executive_interviews && press.executive_interviews.length > 0) ||
    (press.financial_news && press.financial_news.length > 0) ||
    (press.partnership_announcements && press.partnership_announcements.length > 0)
  )
})
</script>
