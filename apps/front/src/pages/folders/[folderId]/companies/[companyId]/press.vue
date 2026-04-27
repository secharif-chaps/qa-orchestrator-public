<template>
  <div class="text-neutral-black-font gap-xl flex flex-col">
    <!-- Loading State -->
    <SectionLoadingState
      v-if="company && (task?.status === 'pending' || task?.status === 'running')"
    />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState v-else-if="!hasAnyPressData" :title="$t('screen.profile.sections.press.noData')" />

    <!-- Main Content -->
    <template v-else>
      <!-- Insights block -->
      <ChapseAlert
        v-if="company?.press?.insights"
        variant="mage"
        :title="$t('screen.profile.sections.press.insights.title')"
      >
        {{ company.press.insights }}
      </ChapseAlert>

      <!-- Activités et source -->
      <SectionCard>
        <template #header>
          <SectionTitle :title="$t('screen.profile.sections.press.activities.title')" />
        </template>
        <div class="gap-2xs flex flex-col">
          <PressItemCard
            v-for="(item, index) in allPressItems"
            :key="`${item.category}-${index}`"
            :item
          />
          <EmptyState
            v-if="!allPressItems.length"
            :title="$t('screen.profile.sections.press.activities.noItems')"
          />
        </div>
      </SectionCard>

      <!-- Statistiques de couverture presse -->
      <div class="gap-md flex flex-col">
        <SectionTitle :title="$t('screen.profile.sections.press.stats.title')" />
        <div class="gap-2xs grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <Alert
            v-for="stat in pressStats"
            :key="stat.key"
            :title="
              t('screen.profile.sections.press.categories.categoryLabel', { category: stat.key })
            "
          >
            <template #aside>
              <span class="text-sm">{{ stat.count }}</span>
            </template>
          </Alert>
        </div>
      </div>
    </template>
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
import PressItemCard from '@/components/company/press/PressItemCard.vue'
import Alert from '@/components/ui/Alert.vue'
import ChapseAlert from '@/components/ui/ChapseAlert.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import SectionTitle from '@/components/ui/SectionTitle.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { CATEGORY_CONFIG, type PressItemWithCategory } from '@/types/press'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute('/folders/[folderId]/companies/[companyId]/press')

const companyId = computed(() => route.params.companyId)

const { data: tasks } = useQuery(() => companyTasksQuery({ companyId: companyId.value }))

const task = computed(() => tasks.value?.find((task) => task.type === 'press'))
const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))
const { data: company } = useQuery(
  // Task data is kept fresh via SSE (Server-Sent Events) in useTaskEvents composable.
  // No polling needed - cache is invalidated automatically when tasks update.
  () => companyByIdQuery({ id: companyId.value, language: selectedLanguage.value }),
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

const allPressItems = computed<PressItemWithCategory[]>(() => {
  const press = company.value?.press
  if (!press) return []

  return Object.keys(CATEGORY_CONFIG).flatMap((key) =>
    ((press[key as keyof typeof press] as PressItemWithCategory[]) ?? []).map((item) => ({
      value: item.value,
      source: item.source,
      category: key,
    })),
  )
})

const pressStats = computed(() =>
  Object.keys(CATEGORY_CONFIG).map((key) => ({
    key,
    count: company.value?.press?.[key as keyof typeof company.value.press]?.length ?? 0,
  })),
)
</script>
