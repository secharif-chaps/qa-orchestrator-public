<template>
  <div class="bg-bg1 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="col-span-2">
        <h3 class="space-x-2 font-bold text-primary">
          <i class="fa fa-bullhorn"></i>
          <span>{{ $t('profile.sections.news.title') }}</span>
        </h3>
      </div>

      <!-- Press Insights -->
      <div v-if="company?.press?.insights">
        <Alert
          variant="info"
          icon="fa fa-robot"
          decoration-icon="fa fa-sparkles"
          :title="$t('profile.sections.press.insights.title')"
          :message="company.press.insights"
          :dismissible="false"
        >
          <template #status>
            <div class="flex items-center space-x-1 text-xs text-info">
              <i class="fa fa-brain"></i>
              <span>AI Generated</span>
            </div>
          </template>
        </Alert>
      </div>

      <!-- Financial News -->
      <div v-if="company?.press?.financial_news?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-chart-line"></i>
          Financial News
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.financial_news"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Product Launches -->
      <div v-if="company?.press?.product_launches?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-rocket"></i>
          Product Launches
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.product_launches"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Executive Interviews -->
      <div v-if="company?.press?.executive_interviews?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-microphone"></i>
          Executive Interviews
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.executive_interviews"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Media Mentions -->
      <div v-if="company?.press?.media_mentions?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-newspaper"></i>
          Media Mentions
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.media_mentions"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Press Releases -->
      <div v-if="company?.press?.press_releases?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-file-alt"></i>
          Press Releases
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.press_releases"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Partnership Announcements -->
      <div v-if="company?.press?.partnership_announcements?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-handshake"></i>
          Partnership Announcements
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.partnership_announcements"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Awards & Recognition -->
      <div v-if="company?.press?.awards_recognition?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-trophy"></i>
          Awards & Recognition
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.awards_recognition"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- Articles (keeping for backward compatibility) -->
      <div v-if="company?.press?.articles?.length" class="space-y-2">
        <h4 class="font-medium text-primary flex items-center gap-2">
          <i class="fa fa-newspaper"></i>
          Articles
        </h4>
        <div class="text-sm space-y-2">
          <div
            v-for="(item, index) in company.press.articles"
            :key="index"
            class="bg-bg2 rounded p-3"
          >
            <p class="text-secondary">{{ item.value }}</p>
            <div v-if="item.sources?.length" class="flex flex-wrap gap-1 mt-2">
              <Source v-for="source in item.sources" :key="source" :source="source" />
            </div>
          </div>
        </div>
      </div>

      <!-- No data message -->
      <div v-if="!hasAnyPressData" class="text-secondary text-center py-4">
        {{ $t('common.noData') }}
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { getSourcedValue } from '@/components/helpers/sourcedValues'
import Source from '../Source.vue'
import Alert from '@/components/ui/Alert.vue'

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value,
}))

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
