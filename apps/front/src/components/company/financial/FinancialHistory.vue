<template>
  <div
    v-if="metrics.length > 0"
    class="bg-primary-lightest rounded-card border-primary-lighter-stroke flex flex-col gap-4 border p-6"
  >
    <h3 class="text-base font-semibold">
      {{ t('screen.profile.sections.financial.history.title') }}
    </h3>

    <Table :fields="fields" :items="groupedRows" :row-key="(row: HistoryRow) => row.period">
      <template #cell(period)="{ item }">
        <td class="px-4 py-3 font-medium">{{ item.period }}</td>
      </template>
      <template #cell(revenue)="{ item }">
        <FinancialHistoryCell :cell="item.revenue" />
      </template>
      <template #cell(ebitda)="{ item }">
        <FinancialHistoryCell :cell="item.ebitda" />
      </template>
      <template #cell(netIncome)="{ item }">
        <FinancialHistoryCell :cell="item.netIncome" />
      </template>
      <template #cell(fcf)="{ item }">
        <FinancialHistoryCell :cell="item.fcf" />
      </template>
    </Table>
  </div>
</template>

<script lang="ts" setup>
import type { FinancialMetric } from '@/types/company'
import { Table } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FinancialHistoryCell from './FinancialHistoryCell.vue'

const { t } = useI18n()

interface Props {
  metrics: FinancialMetric[]
}

const { metrics } = defineProps<Props>()

interface MetricCell {
  value: string
  source: string | null
  context?: string | null
}

interface HistoryRow {
  period: string
  periodNormalized: string | null
  revenue: MetricCell | null
  ebitda: MetricCell | null
  netIncome: MetricCell | null
  fcf: MetricCell | null
}

const METRIC_MAP: Record<string, keyof Omit<HistoryRow, 'period' | 'periodNormalized'>> = {
  revenue: 'revenue',
  ebitda: 'ebitda',
  netIncome: 'netIncome',
  freeCashFlow: 'fcf',
}

const fields = computed(() => [
  { key: 'period', label: t('screen.profile.sections.financial.history.period') },
  { key: 'revenue', label: t('screen.profile.sections.financial.history.revenue') },
  { key: 'ebitda', label: t('screen.profile.sections.financial.history.ebitda') },
  { key: 'netIncome', label: t('screen.profile.sections.financial.history.netIncome') },
  { key: 'fcf', label: t('screen.profile.sections.financial.history.fcf') },
])

const groupedRows = computed<HistoryRow[]>(() => {
  const rowMap = new Map<string, HistoryRow>()

  for (const metric of metrics) {
    const key = METRIC_MAP[metric.metricName]
    if (!key || !metric.value) continue

    if (!rowMap.has(metric.period)) {
      rowMap.set(metric.period, {
        period: metric.period,
        periodNormalized: metric.periodNormalized,
        revenue: null,
        ebitda: null,
        netIncome: null,
        fcf: null,
      })
    }

    const row = rowMap.get(metric.period)!
    // Pick up a normalized date from any metric in the group if the first one lacked it
    if (!row.periodNormalized && metric.periodNormalized)
      row.periodNormalized = metric.periodNormalized
    row[key] = {
      value: metric.unit ? `${metric.value} ${metric.unit}` : metric.value,
      source: metric.source,
      context: metric.context,
    }
  }

  return Array.from(rowMap.values()).sort((a, b) => {
    if (!a.periodNormalized && !b.periodNormalized) return b.period.localeCompare(a.period)
    if (!a.periodNormalized) return 1
    if (!b.periodNormalized) return -1
    return b.periodNormalized.localeCompare(a.periodNormalized)
  })
})
</script>
