<template>
  <div class="bg-base-100 rounded-lg border border-primary-stroke">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-primary-stroke">
      <h3 class="text-lg font-semibold">Organization Breakdown</h3>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="p-6">
      <div class="space-y-3">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4">
          <div class="h-4 bg-base-200 rounded animate-pulse flex-1"></div>
          <div class="h-4 bg-base-200 rounded animate-pulse w-20"></div>
          <div class="h-4 bg-base-200 rounded animate-pulse w-16"></div>
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
        <i class="fa fa-database text-2xl text-secondary mb-2"></i>
        <p class="text-sm text-secondary">No organization data for selected period</p>
      </div>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-base-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-secondary uppercase">
              Organization
            </th>
            <th
              class="px-6 py-3 text-right text-xs font-medium text-secondary uppercase cursor-pointer hover:bg-base-300 transition-colors"
              @click="toggleSort('companies_count')"
            >
              Companies Created
              <i
                class="fa ml-1 text-xs"
                :class="getSortIcon('companies_count')"
              ></i>
            </th>
            <th
              class="px-6 py-3 text-right text-xs font-medium text-secondary uppercase cursor-pointer hover:bg-base-300 transition-colors"
              @click="toggleSort('percentage')"
            >
              % of Total
              <i
                class="fa ml-1 text-xs"
                :class="getSortIcon('percentage')"
              ></i>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-primary-stroke">
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
import type { OrganizationBreakdown } from '@/types/usage'
import OrganizationUsageRow from './OrganizationUsageRow.vue'

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
