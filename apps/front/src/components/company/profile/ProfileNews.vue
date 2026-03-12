<template>
  <div class="bg-base-100 rounded-lg p-4">
    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <h3 class="text-secondary space-x-2 font-bold">
          <i class="fa fa-bullhorn"></i>
          <span>{{ $t('profile.sections.news.title') }}</span>
        </h3>
        <RouterLink :to="`/companies/${companyId}/press`">
          <Button
            variant="tertiary"
            :label="$t('profile.sections.news.viewAll', 'View All')"
            icon="fa fa-arrow-right"
            icon-position="right"
            size="sm"
          />
        </RouterLink>
      </div>

      <!-- Press Summary Stats -->
      <div v-if="hasAnyPressData" class="grid grid-cols-2 gap-2">
        <div v-if="totalPressItems > 0" class="bg-base-200 rounded p-3">
          <div class="text-secondary text-2xl font-bold">{{ totalPressItems }}</div>
          <div class="text-secondary text-xs">
            {{ $t('profile.sections.news.stats.totalPressItems', 'Total Press Items') }}
          </div>
        </div>
        <div v-if="company?.press?.financial_news?.length" class="bg-base-200 rounded p-3">
          <div class="text-secondary text-2xl font-bold">
            {{ company.press.financial_news.length }}
          </div>
          <div class="text-secondary text-xs">
            {{ $t('profile.sections.news.stats.financialNews', 'Financial News') }}
          </div>
        </div>
        <div v-if="company?.press?.media_mentions?.length" class="bg-base-200 rounded p-3">
          <div class="text-secondary text-2xl font-bold">
            {{ company.press.media_mentions.length }}
          </div>
          <div class="text-secondary text-xs">
            {{ $t('profile.sections.news.stats.mediaMentions', 'Media Mentions') }}
          </div>
        </div>
        <div v-if="company?.press?.product_launches?.length" class="bg-base-200 rounded p-3">
          <div class="text-secondary text-2xl font-bold">
            {{ company.press.product_launches.length }}
          </div>
          <div class="text-secondary text-xs">
            {{ $t('profile.sections.news.stats.productLaunches', 'Product Launches') }}
          </div>
        </div>
      </div>

      <!-- Latest Press Items Preview -->
      <div v-if="latestPressItems.length > 0" class="space-y-2">
        <h4 class="text-secondary text-sm font-medium">
          {{ $t('profile.sections.news.latestUpdates', 'Latest Updates') }}
        </h4>
        <div class="space-y-2">
          <div
            v-for="item in latestPressItems"
            :key="item.value"
            class="bg-base-200 rounded p-3 text-sm"
          >
            <div class="flex items-start justify-between gap-2">
              <p class="text-secondary line-clamp-2">{{ item.value }}</p>
              <i :class="[item.icon]" class="text-secondary/50 mt-1 text-xs"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- No data message -->
      <div v-if="!hasAnyPressData" class="text-secondary py-4 text-center">
        {{ $t('common.noData') }}
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute, RouterLink } from 'vue-router'
import { computed } from 'vue'
import { Button } from '@owlint/feathers-vue'

const route = useRoute()

const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
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

const totalPressItems = computed(() => {
  const press = company.value?.press
  if (!press) return 0

  return (
    (press.articles?.length || 0) +
    (press.press_releases?.length || 0) +
    (press.media_mentions?.length || 0) +
    (press.awards_recognition?.length || 0) +
    (press.product_launches?.length || 0) +
    (press.executive_interviews?.length || 0) +
    (press.financial_news?.length || 0) +
    (press.partnership_announcements?.length || 0)
  )
})

const latestPressItems = computed(() => {
  const press = company.value?.press
  if (!press) return []

  const allItems = []

  // Add items with their category icons
  if (press.financial_news?.length) {
    allItems.push(
      ...press.financial_news.slice(0, 1).map((item) => ({ ...item, icon: 'fa fa-chart-line' })),
    )
  }
  if (press.product_launches?.length) {
    allItems.push(
      ...press.product_launches.slice(0, 1).map((item) => ({ ...item, icon: 'fa fa-rocket' })),
    )
  }
  if (press.media_mentions?.length) {
    allItems.push(
      ...press.media_mentions.slice(0, 1).map((item) => ({ ...item, icon: 'fa fa-newspaper' })),
    )
  }

  // Return max 3 items
  return allItems.slice(0, 3)
})
</script>
