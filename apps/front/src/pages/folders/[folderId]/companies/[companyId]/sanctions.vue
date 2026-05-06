<template>
  <div class="flex flex-col gap-4">
    <!-- Loading State -->
    <SectionLoadingState v-if="isLoading" />

    <!-- Error State -->
    <SectionErrorState v-else-if="company && task?.status === 'error'" :task="task" />

    <!-- No Data State -->
    <EmptyState v-else-if="!hasSanctionsData">
      <div class="flex flex-col items-center gap-2">
        <span
          class="bg-success-light text-success-light-content inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium"
        >
          <Icon icon="fa-shield-check" />
          {{ $t('screen.profile.sections.sanctions.clean') }}
        </span>
        <p class="text-neutral-black-font text-sm">
          {{ $t('screen.profile.sections.sanctions.noData') }}
        </p>
      </div>
    </EmptyState>

    <!-- Main Content -->
    <div v-else class="flex flex-col gap-6">
      <!-- Section Header with Overall Risk -->
      <div class="flex items-center justify-between">
        <h3 class="text-neutral-black-font flex items-center gap-2 font-bold">
          <Icon icon="fa-shield-halved" />
          <span>{{ $t('screen.profile.sections.sanctions.title') }}</span>
        </h3>
        <span
          v-if="sanctionsData?.overall_risk_level"
          class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
          :class="getRiskBadgeClass(sanctionsData.overall_risk_level)"
        >
          <Icon icon="fa-circle" class="text-[6px]" />
          {{ getRiskLabel(sanctionsData.overall_risk_level) }}
        </span>
      </div>

      <!-- Insights -->
      <div v-if="sanctionsData?.insights" class="rounded-sm bg-white p-4">
        <p class="text-neutral-black-font text-sm leading-relaxed">
          {{ sanctionsData.insights }}
        </p>
      </div>

      <!-- Overall Risk Justification -->
      <div
        v-if="sanctionsData?.overall_risk_justification"
        class="border-primary-lighter-stroke rounded-sm border p-4"
      >
        <p class="text-neutral-black-font text-xs font-medium tracking-wider uppercase">
          {{ $t('screen.profile.sections.sanctions.riskAssessment') }}
        </p>
        <p class="text-neutral-black-font mt-1 text-sm">
          {{ sanctionsData.overall_risk_justification }}
        </p>
      </div>

      <!-- Sanctions Items Table -->
      <div v-if="sanctionsData?.items?.length" class="flex flex-col gap-3">
        <p class="text-neutral-black-font text-sm font-semibold">
          {{
            $t('screen.profile.sections.sanctions.itemsCount', {
              count: sanctionsData.items.length,
            })
          }}
        </p>

        <div class="flex flex-col gap-3">
          <div
            v-for="(item, index) in sortedItems"
            :key="index"
            class="border-primary-lighter-stroke rounded-sm border bg-white"
          >
            <!-- Item Header -->
            <button
              class="flex w-full cursor-pointer items-center justify-between p-4 text-left"
              @click="toggleItem(index)"
            >
              <div class="flex min-w-0 flex-1 items-center gap-3">
                <!-- Risk Level Badge -->
                <span
                  v-if="item.risk_level"
                  class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold"
                  :class="getRiskBadgeClass(item.risk_level)"
                >
                  {{ getRiskInitial(item.risk_level) }}
                </span>

                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold">{{ item.entity_name }}</span>
                    <span v-if="item.country" class="text-neutral-black-font text-xs">
                      ({{ item.country }})
                    </span>
                    <span
                      v-if="item.is_onu_eu_ofac"
                      class="bg-error-light text-error-light-content rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                    >
                      ONU/EU/OFAC
                    </span>
                  </div>
                  <span v-if="item.sanction_type" class="text-neutral-black-font text-xs">
                    {{ formatSanctionType(item.sanction_type) }}
                  </span>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <span v-if="item.date" class="text-neutral-black-font text-xs">
                  {{ item.date }}
                </span>
                <Icon
                  :icon="expandedItems.has(index) ? 'fa-chevron-up' : 'fa-chevron-down'"
                  class="text-neutral-black-font text-xs transition-transform duration-200"
                />
              </div>
            </button>

            <!-- Expanded Details -->
            <div
              v-if="expandedItems.has(index)"
              class="border-primary-lighter-stroke flex flex-col gap-3 border-t px-4 py-3"
            >
              <p v-if="item.description" class="text-neutral-black-font text-sm">
                {{ item.description }}
              </p>

              <div v-if="item.sanction_nature" class="flex items-start gap-2">
                <span class="text-neutral-black-font text-xs font-medium">
                  {{ $t('screen.profile.sections.sanctions.nature') }}:
                </span>
                <span class="text-neutral-black-font text-xs">{{ item.sanction_nature }}</span>
              </div>

              <div v-if="item.source_code" class="flex items-start gap-2">
                <span class="text-neutral-black-font text-xs font-medium">
                  {{ $t('screen.profile.sections.sanctions.sourceCode') }}:
                </span>
                <span class="text-neutral-black-font font-mono text-xs">{{
                  item.source_code
                }}</span>
              </div>

              <div v-if="item.risk_justification" class="flex items-start gap-2">
                <span class="text-neutral-black-font text-xs font-medium">
                  {{ $t('screen.profile.sections.sanctions.riskJustification') }}:
                </span>
                <span class="text-neutral-black-font text-xs">{{ item.risk_justification }}</span>
              </div>

              <div v-if="item.weblinks?.length" class="flex flex-col gap-1">
                <span class="text-neutral-black-font text-xs font-medium">
                  {{ $t('screen.profile.sections.sanctions.sources') }}:
                </span>
                <div class="flex flex-col gap-1">
                  <a
                    v-for="(link, linkIndex) in item.weblinks"
                    :key="linkIndex"
                    :href="link.uri"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-info truncate text-xs underline"
                  >
                    {{ link.caption || link.uri }}
                  </a>
                </div>
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
    - organization.read
</route>

<script lang="ts" setup>
import SectionErrorState from '@/components/company/SectionErrorState.vue'
import SectionLoadingState from '@/components/company/SectionLoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { companyByIdQuery } from '@/queries/companies'
import { companyTasksQuery } from '@/queries/tasks'
import { Icon } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import type { Ref } from 'vue'
import { computed, inject, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const route = useRoute('/folders/[folderId]/companies/[companyId]/sanctions')
const { t } = useI18n()

const companyId = computed(() => route.params.companyId)

const selectedLanguage = inject<Ref<string | undefined>>('selectedLanguage', ref(undefined))

const { data: tasks, isLoading: isLoadingTasks } = useQuery(() =>
  companyTasksQuery({
    companyId: companyId.value,
  }),
)

const task = computed(() => tasks.value?.find((t) => t.type === 'sanctions'))

const { data: company, isLoading: isLoadingCompany } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
    language: selectedLanguage.value,
  }),
)

const isLoading = computed(
  () =>
    isLoadingTasks.value ||
    isLoadingCompany.value ||
    task.value?.status === 'pending' ||
    task.value?.status === 'running',
)

const sanctionsData = computed(() => company.value?.sanctions)

const parseDate = (date: string | undefined): number => {
  if (!date) return 0
  // Handle dates like "2019-00-00" or "2020-12-00" by replacing 00 with 01
  const normalized = date.replace(/-00/g, '-01')
  const ts = new Date(normalized).getTime()
  return isNaN(ts) ? 0 : ts
}

const sortedItems = computed(() => {
  const items = sanctionsData.value?.items
  if (!items) return []
  return [...items].sort((a, b) => parseDate(b.date) - parseDate(a.date))
})

const hasSanctionsData = computed(() => {
  const data = sanctionsData.value
  if (!data) return false
  // Has data if there are items or a non-low risk level
  return !!(
    (data.items && data.items.length > 0) ||
    (data.overall_risk_level && data.overall_risk_level !== 'low')
  )
})

// Track which items are expanded
const expandedItems = reactive(new Set<number>())

const toggleItem = (index: number) => {
  if (expandedItems.has(index)) {
    expandedItems.delete(index)
  } else {
    expandedItems.add(index)
  }
}

// Risk level styling helpers
const getRiskBadgeClass = (level: string): string => {
  switch (level) {
    case 'low':
      return 'bg-success-light text-success-light-content'
    case 'medium':
      return 'bg-warning-light text-warning-light-content'
    case 'high':
      return 'bg-error-light text-error-light-content'
    case 'critical':
      return 'bg-error text-error-content'
    default:
      return 'bg-primary-lightest text-neutral-black-font'
  }
}

const getRiskLabel = (level: string): string => {
  switch (level) {
    case 'low':
      return t('screen.profile.sections.sanctions.riskLevels.low')
    case 'medium':
      return t('screen.profile.sections.sanctions.riskLevels.medium')
    case 'high':
      return t('screen.profile.sections.sanctions.riskLevels.high')
    case 'critical':
      return t('screen.profile.sections.sanctions.riskLevels.critical')
    default:
      return level
  }
}

const getRiskInitial = (level: string): string => {
  return level.charAt(0).toUpperCase()
}

// Format sanction type for display (snake_case to Title Case)
const formatSanctionType = (type: string): string => {
  return type
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}
</script>
