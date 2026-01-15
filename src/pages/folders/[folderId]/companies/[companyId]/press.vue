<template>
  <div>
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
    <NoData v-else-if="!hasAnyPressData">
      <p class="text-secondary text-lg font-medium">
        {{ $t('profile.sections.press.noData') }}
      </p>
    </NoData>

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
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-chart-line"></i>
              Financial News
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.financial_news"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Product Launches -->
          <div v-if="company?.press?.product_launches?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-rocket"></i>
              Product Launches
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.product_launches"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Executive Interviews -->
          <div
            v-if="company?.press?.executive_interviews?.length"
            class="bg-base-100 rounded-lg p-6"
          >
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-microphone"></i>
              Executive Interviews
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.executive_interviews"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Media Mentions -->
          <div v-if="company?.press?.media_mentions?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-newspaper"></i>
              Media Mentions
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.media_mentions"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Press Releases -->
          <div v-if="company?.press?.press_releases?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-file-alt"></i>
              Press Releases
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.press_releases"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Articles (backward compatibility) -->
          <div v-if="company?.press?.articles?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-newspaper"></i>
              Articles
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.articles"
                :key="index"
                class="bg-base-200 rounded-lg p-4 hover:bg-base-200/80 transition-colors"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
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
            <h2 class="text-lg font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-handshake"></i>
              Partnership Announcements
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.partnership_announcements"
                :key="index"
                class="bg-base-200 rounded-lg p-3"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Awards & Recognition -->
          <div v-if="company?.press?.awards_recognition?.length" class="bg-base-100 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-secondary flex items-center gap-2 mb-4">
              <i class="fa fa-trophy"></i>
              Awards & Recognition
            </h2>
            <div class="space-y-3">
              <div
                v-for="(item, index) in company.press.awards_recognition"
                :key="index"
                class="bg-base-200 rounded-lg p-3"
              >
                <span class="text-secondary mr-2">{{ item.value }}</span>
                <Source :source="item.source" />
              </div>
            </div>
          </div>

          <!-- Quick Stats -->
          <div class="bg-base-100 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-secondary mb-4">Press Coverage Stats</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-secondary text-sm">Financial News</span>
                <span class="text-secondary font-medium">{{
                  company?.press?.financial_news?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-secondary text-sm">Product Launches</span>
                <span class="text-secondary font-medium">{{
                  company?.press?.product_launches?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-secondary text-sm">Media Mentions</span>
                <span class="text-secondary font-medium">{{
                  company?.press?.media_mentions?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-secondary text-sm">Press Releases</span>
                <span class="text-secondary font-medium">{{
                  company?.press?.press_releases?.length || 0
                }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-secondary text-sm">Executive Interviews</span>
                <span class="text-secondary font-medium">{{
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
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import Source from '@/components/company/Source.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import NoData from '@/components/ui/NoData.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { useQuery } from '@pinia/colada'
import { computed, inject, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: tasks } = useQuery(companyTasksQuery, () => ({
  companyId: companyId.value,
}))

const task = computed(() => tasks.value?.find((t) => t.type === 'press'))
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))
const { data: company } = useQuery(
  companyByIdQuery,
  () => ({
    id: companyId.value,
    language: selectedLanguage.value,

  }),
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
)

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
