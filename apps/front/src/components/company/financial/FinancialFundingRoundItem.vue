<template>
  <div class="relative flex items-start gap-6 pb-6">
    <!-- Date column -->
    <div class="w-20 pt-4 text-right">
      <div class="text-secondary text-sm font-medium">
        {{ formattedDate }}
      </div>
    </div>

    <!-- Timeline line -->
    <div v-if="!isLast" class="bg-base-300 absolute top-6 left-[101px] h-full w-0.5"></div>

    <!-- Timeline dot -->
    <div class="relative">
      <div
        class="bg-sage-50 dark:bg-base-100 dark:ring-base-100 border-base-300 dark:border-base-300 absolute top-4 -left-2.5 h-4 w-4 rounded-full border-2 ring-4 ring-white"
      ></div>
    </div>

    <!-- Round content card -->
    <div class="bg-base-100 border-primary-stroke flex-1 rounded-lg border p-5">
      <div class="flex flex-col gap-3">
        <!-- Header: round type + amount -->
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-2">
            <Tag v-if="round.roundType" variant="info" :label="round.roundType" size="sm" />
            <span v-if="round.amount" class="text-lg font-semibold">
              {{ round.amount }}
            </span>
          </div>
          <Source v-if="round.source" :source="round.source" />
        </div>

        <!-- Details -->
        <div class="flex flex-wrap gap-4">
          <div v-if="round.leadInvestor" class="flex flex-col">
            <span class="text-secondary/70 text-xs">
              {{ t('screen.profile.sections.financial.funding.leadInvestor') }}
            </span>
            <span class="text-sm font-medium">{{ round.leadInvestor }}</span>
          </div>
          <div v-if="round.valuation" class="flex flex-col">
            <span class="text-secondary/70 text-xs">
              {{ t('screen.profile.sections.financial.funding.valuation') }}
            </span>
            <span class="text-sm font-medium">{{ round.valuation }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '@/components/company/Source.vue'
import Tag from '@/components/ui/Tag.vue'
import type { FundingRound } from '@/types/company'

const { t } = useI18n()

interface Props {
  round: FundingRound
  isLast: boolean
}

const { round, isLast } = defineProps<Props>()

const formattedDate = computed(() => {
  const dateStr = round.date
  if (!dateStr) return '—'

  if (dateStr.length === 4) return dateStr
  if (dateStr.length === 7) {
    const [year, month] = dateStr.split('-')
    const date = new Date(parseInt(year), parseInt(month) - 1)
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short' })
  }

  const [year, month, day] = dateStr.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
})
</script>
