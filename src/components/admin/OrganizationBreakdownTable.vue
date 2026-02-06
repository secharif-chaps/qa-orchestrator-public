<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke">
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h3 class="text-lg font-semibold">{{ t('admin.organizationBreakdown.title') }}</h3>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-spinner animate-spin text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">{{ t('admin.organizationBreakdown.loading') }}</p>
      </div>
    </div>

    <div v-else-if="error" class="flex justify-center py-12">
      <div class="text-center">
        <i class="fa fa-exclamation-triangle text-2xl text-error mb-2"></i>
        <p class="text-sm text-error">{{ t('admin.organizationBreakdown.error') }}</p>
      </div>
    </div>

    <div v-else-if="!data?.organizations.length" class="text-center py-12">
      <i class="fa fa-database text-4xl text-secondary mb-4"></i>
      <p class="text-lg font-medium text-secondary">{{ t('admin.organizationBreakdown.noData') }}</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('organization_name')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.organization') }}
                <i class="fa text-xs" :class="getSortIcon('organization_name')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('total_cost')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.totalCost') }}
                <i class="fa text-xs" :class="getSortIcon('total_cost')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('task_count')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.tasks') }}
                <i class="fa text-xs" :class="getSortIcon('task_count')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('company_count')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.companies') }}
                <i class="fa text-xs" :class="getSortIcon('company_count')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('avg_cost_per_task')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.avgCostPerTask') }}
                <i class="fa text-xs" :class="getSortIcon('avg_cost_per_task')"></i>
              </div>
            </th>
            <th
              class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase tracking-wider cursor-pointer hover:bg-base-300"
              @click="sort('avg_cost_per_company')"
            >
              <div class="flex items-center gap-1">
                {{ t('admin.organizationBreakdown.columns.avgCostPerCompany') }}
                <i class="fa text-xs" :class="getSortIcon('avg_cost_per_company')"></i>
              </div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-primary-stroke">
          <tr
            v-for="organization in sortedorganizations"
            :key="organization.organization_id"
            class="hover:bg-base-200 transition-colors"
          >
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mr-3"></div>
                <div>
                  <div class="text-sm font-medium">{{ organization.organization_name }}</div>
                  <div class="text-xs text-secondary">{{ t('admin.organizationBreakdown.id') }} {{ organization.organization_id }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-semibold text-secondary">
                {{ formatCurrency(organization.total_cost) }}
              </div>
              <div class="text-xs text-secondary">
                {{ t('admin.organizationBreakdown.percentOfTotal', { percent: getPercentage(organization.total_cost) }) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium">{{ formatNumber(organization.task_count) }}</div>
              <div class="text-xs text-secondary">
                {{
                  organization.total_input_tokens + organization.total_output_tokens > 0
                    ? t('admin.organizationBreakdown.tokens', { count: formatNumber(organization.total_input_tokens + organization.total_output_tokens) })
                    : t('admin.organizationBreakdown.noTokens')
                }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium">{{ formatNumber(organization.company_count) }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-info">
                {{ formatCurrency(organization.avg_cost_per_task) }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-warning">
                {{ formatCurrency(organization.avg_cost_per_company) }}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { organizationCostResponse, organizationCostData } from '@/api/cost-analysis'

const { t } = useI18n()

interface Props {
  data?: organizationCostResponse
  loading?: boolean
  error?: boolean
}

const props = defineProps<Props>()

const sortBy = ref<keyof organizationCostData>('total_cost')
const sortOrder = ref<'asc' | 'desc'>('desc')

const sort = (field: keyof organizationCostData) => {
  if (sortBy.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortOrder.value = field === 'organization_name' ? 'asc' : 'desc'
  }
}

const getSortIcon = (field: keyof organizationCostData) => {
  if (sortBy.value !== field) {
    return 'fa-sort text-border-2'
  }
  return sortOrder.value === 'asc'
    ? 'fa-sort-up text-sage-content'
    : 'fa-sort-down text-sage-content'
}

const sortedorganizations = computed(() => {
  if (!props.data?.organizations) return []

  return [...props.data.organizations].sort((a, b) => {
    let aVal = a[sortBy.value]
    let bVal = b[sortBy.value]

    if (typeof aVal === 'string') {
      aVal = aVal.toLowerCase()
      bVal = (bVal as string).toLowerCase()
    }

    if (aVal < bVal) return sortOrder.value === 'asc' ? -1 : 1
    if (aVal > bVal) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })
})

const formatNumber = (num: number) => {
  return new Intl.NumberFormat().format(num)
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(amount)
}

const getPercentage = (amount: number) => {
  const total = props.data?.summary.total_cost || 0
  return total > 0 ? ((amount / total) * 100).toFixed(1) : '0'
}
</script>
