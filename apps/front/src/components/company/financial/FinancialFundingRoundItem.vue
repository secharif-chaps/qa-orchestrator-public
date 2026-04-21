<template>
  <TimelineItem :date="formattedDate">
    <TimelineCard>
      <!-- Header: round type + amount + source -->
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-2">
          <Tag v-if="round.roundType" color="indigo" :label="round.roundType" size="sm" />
          <span v-if="round.amount" class="text-lg font-semibold">
            {{ round.amount }}
          </span>
        </div>
        <Source v-if="round.source" :source="round.source" />
      </div>

      <!-- Details -->
      <div v-if="round.leadInvestor || round.valuation" class="flex flex-wrap gap-4">
        <div v-if="round.leadInvestor" class="gap-2xs flex flex-col">
          <span class="text-neutral-black-font text-xs">
            {{ t('screen.profile.sections.financial.funding.leadInvestor') }}
          </span>
          <span class="text-sm font-medium">{{ round.leadInvestor }}</span>
        </div>
        <div v-if="round.valuation" class="gap-2xs flex flex-col">
          <span class="text-neutral-black-font text-xs">
            {{ t('screen.profile.sections.financial.funding.valuation') }}
          </span>
          <span class="text-sm font-medium">{{ round.valuation }}</span>
        </div>
      </div>
    </TimelineCard>
  </TimelineItem>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Source from '@/components/company/Source.vue'

import TimelineCard from '@/components/ui/TimelineCard.vue'
import TimelineItem from '@/components/ui/TimelineItem.vue'
import type { FundingRound } from '@/types/company'
import { Tag } from '@owlint/feathers-vue'

const { t } = useI18n()

interface Props {
  round: FundingRound
}

const { round } = defineProps<Props>()

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
