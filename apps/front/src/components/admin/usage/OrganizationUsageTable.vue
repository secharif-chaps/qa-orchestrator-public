<template>
  <div class="bg-base-100 border-primary-stroke rounded-lg border">
    <!-- Header -->
    <div class="border-primary-stroke border-b px-6 py-4">
      <h3 class="text-lg font-semibold">{{ t('admin.usage.table.title') }}</h3>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4">
          <div class="bg-base-200 h-4 flex-1 animate-pulse rounded"></div>
          <div class="bg-base-200 h-4 w-20 animate-pulse rounded"></div>
          <div class="bg-base-200 h-4 w-16 animate-pulse rounded"></div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!data || data.length === 0"
      data-testid="empty-state"
      class="flex justify-center py-12"
    >
      <div class="text-center">
        <i class="fa fa-database text-secondary mb-2 text-2xl"></i>
        <p class="text-secondary text-sm">
          {{ t('admin.usage.table.noData') }}
        </p>
      </div>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th class="text-secondary px-6 py-3 text-left text-xs font-medium uppercase">
              {{ t('admin.usage.table.organization') }}
            </th>
            <th
              class="text-secondary hover:bg-base-300 cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase transition-colors"
              @click="toggleSort('companies_count')"
            >
              {{ t('admin.usage.table.companiesCreated') }}
              <i class="fa ml-1 text-xs" :class="getSortIcon('companies_count')"></i>
            </th>
            <th
              class="text-secondary hover:bg-base-300 cursor-pointer px-6 py-3 text-right text-xs font-medium uppercase transition-colors"
              @click="toggleSort('percentage')"
            >
              {{ t('admin.usage.table.percentOfTotal') }}
              <i class="fa ml-1 text-xs" :class="getSortIcon('percentage')"></i>
            </th>
          </tr>
        </thead>
        <tbody class="divide-primary-stroke divide-y">
          <OrganizationUsageRow
            v-for="(org, index) in sortedData"
            :key="org.organization_id"
            :organization="org"
            :class="index % 2 === 0 ? 'bg-base-100' : 'bg-base-200'"
          />
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Organization breakdown table component for the usage dashboard.
 *
 * Displays a sortable table of organizations with their company counts
 * and percentage of total. Uses the OrganizationUsageRow component
 * for rendering individual rows.
 */
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { OrganizationBreakdown } from '@/types/usage'
import OrganizationUsageRow from './OrganizationUsageRow.vue'

const { t } = useI18n()

interface Props {
  /** Organization breakdown data */
  data: OrganizationBreakdown[]
  /** Whether data is currently loading */
  loading: boolean
}

const props = defineProps<Props>()

/** Sort configuration */
type SortKey = 'companies_count' | 'percentage'
type SortDirection = 'asc' | 'desc'

const sortKey = ref<SortKey>('companies_count')
const sortDirection = ref<SortDirection>('desc')

/**
 * Toggle sort on a column. If already sorting by this column,
 * toggle direction. Otherwise, sort by this column descending.
 */
const toggleSort = (key: SortKey) => {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDirection.value = 'desc'
  }
}

/**
 * Get the sort icon class for a column.
 */
const getSortIcon = (key: SortKey): string => {
  if (sortKey.value !== key) return 'fa-sort'
  return sortDirection.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
}

/**
 * Sort the data based on current sort configuration.
 */
const sortedData = computed(() => {
  if (!props.data) return []

  return [...props.data].sort((a, b) => {
    const aVal = a[sortKey.value]
    const bVal = b[sortKey.value]
    const modifier = sortDirection.value === 'asc' ? 1 : -1
    return (aVal - bVal) * modifier
  })
})
</script>
