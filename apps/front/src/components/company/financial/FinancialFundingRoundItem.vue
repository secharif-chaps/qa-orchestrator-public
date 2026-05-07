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
import { useDateTime } from '@/composables/useDateTime'
import type { FundingRound } from '@/types/company'
import { Tag } from '@owlint/feathers-vue'

const { t } = useI18n()
const { formatDate } = useDateTime()

interface Props {
  round: FundingRound
}

const { round } = defineProps<Props>()

const formattedDate = computed(() => {
  const dateStr = round.date
  if (!dateStr) return '—'

  if (/^\d{4}$/.test(dateStr)) return dateStr

  if (/^\d{4}-\d{2}$/.test(dateStr)) {
    const [year, month] = dateStr.split('-')
    return formatDate(new Date(parseInt(year), parseInt(month) - 1), 'short')
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    const [year, month, day] = dateStr.split('-').map(Number)
    return formatDate(new Date(year, month - 1, day), 'eventDate')
  }

  // Fallback for non-ISO formats (e.g. "March 2024", "Q1 2024", "May 5, 2024"):
  // try native Date parsing, otherwise display the raw string instead of "Invalid Date".
  const parsed = new Date(dateStr)
  return isNaN(parsed.getTime()) ? dateStr : formatDate(parsed, 'eventDate')
})
</script>
