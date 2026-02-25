/**
 * Pinia Colada query definitions for admin usage statistics.
 *
 * This module provides queries for the usage dashboard that displays
 * company creation trends, task success rates, and organization breakdowns.
 */

import { defineQueryOptions } from '@pinia/colada'
import { getUsageStats } from '@/api/admin'

/**
 * Query keys for admin usage statistics.
 *
 * Keys include date parameters to ensure proper cache invalidation
 * when the selected time range changes.
 */
export const ADMIN_USAGE_QUERY_KEYS = {
  root: ['admin'] as const,
  usageStats: (startDate: string, endDate: string) =>
    [...ADMIN_USAGE_QUERY_KEYS.root, 'usage-stats', startDate, endDate] as const,
}

/**
 * Query for usage statistics within a date range.
 *
 * Returns aggregated data for:
 * - KPI cards (companies_count, task_success_rate, active_users_count)
 * - Line chart (companies_over_time)
 * - Stacked bar chart and table (companies_by_organization)
 *
 * The query automatically refetches when startDate or endDate change
 * since they are included in the query key.
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * import { useQuery } from '@pinia/colada'
 * import { usageStatsQuery } from '@/queries/admin-usage'
 *
 * const startDate = ref('2025-01-01')
 * const endDate = ref('2025-01-07')
 *
 * const { data, isLoading, error } = useQuery(
 *   usageStatsQuery,
 *   () => ({ startDate: startDate.value, endDate: endDate.value })
 * )
 * </script>
 * ```
 */
export const usageStatsQuery = defineQueryOptions(
  ({ startDate, endDate }: { startDate: string; endDate: string }) => ({
    key: ADMIN_USAGE_QUERY_KEYS.usageStats(startDate, endDate),
    query: () => getUsageStats(startDate, endDate),
  }),
)
