/**
 * Pinia Colada queries for organization credit statistics.
 *
 * These queries fetch credit usage data for the manager dashboard:
 * - Balance and usage breakdown
 * - Top credit-consuming users
 * - Daily credit usage time series
 */

import { defineQueryOptions } from '@pinia/colada'
import { getCreditStats, getTopCreditUsers, getDailyCreditUsage } from '@/api/credits'
import type { TopCreditUsersFilters, DailyCreditUsageFilters } from '@/types/credits'

// ============================================================================
// Query Keys
// ============================================================================

/**
 * Serialize filters to a stable string for use in query keys.
 * Ensures consistent key generation regardless of property order.
 */
const serializeFilters = <T extends object>(filters: T): string => {
  return JSON.stringify(filters, Object.keys(filters).sort())
}

/**
 * Query keys for credit statistics operations.
 * Used for cache management and invalidation.
 */
export const CREDIT_QUERY_KEYS = {
  root: ['credits'] as const,
  stats: (orgId: string) => [...CREDIT_QUERY_KEYS.root, 'stats', orgId] as const,
  topUsers: (orgId: string, filters: TopCreditUsersFilters) =>
    [...CREDIT_QUERY_KEYS.root, 'top-users', orgId, serializeFilters(filters)] as const,
  dailyUsage: (orgId: string, filters: DailyCreditUsageFilters) =>
    [...CREDIT_QUERY_KEYS.root, 'daily-usage', orgId, serializeFilters(filters)] as const,
}

// ============================================================================
// Credit Stats Query
// ============================================================================

/**
 * Query for organization's credit statistics.
 * Returns balance, usage breakdown by module, and remaining capacity.
 *
 * @example
 * const { data: stats, isLoading } = useQuery(
 *   creditStatsQuery,
 *   () => ({ orgId: organizationId })
 * )
 */
export const creditStatsQuery = defineQueryOptions(({ orgId }: { orgId: string }) => ({
  key: CREDIT_QUERY_KEYS.stats(orgId),
  query: () => {
    if (!orgId || orgId.trim() === '') {
      throw new Error('Invalid organization ID')
    }
    return getCreditStats(orgId)
  },
}))

// ============================================================================
// Top Credit Users Query
// ============================================================================

/**
 * Query for organization's top credit-consuming users.
 * Supports filtering by module, period, dates, and search.
 *
 * @example
 * const filters = ref<TopCreditUsersFilters>({ page: 1, size: 10, period: '30d' })
 * const { data: topUsers, isLoading } = useQuery(
 *   topCreditUsersQuery,
 *   () => ({ orgId: organizationId, filters: filters.value })
 * )
 */
export const topCreditUsersQuery = defineQueryOptions(
  ({ orgId, filters }: { orgId: string; filters: TopCreditUsersFilters }) => ({
    key: CREDIT_QUERY_KEYS.topUsers(orgId, filters),
    query: () => {
      if (!orgId || orgId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getTopCreditUsers(orgId, filters)
    },
  }),
)

// ============================================================================
// Daily Credit Usage Query
// ============================================================================

/**
 * Query for organization's daily credit usage time series.
 * Supports filtering by module and period.
 *
 * @example
 * const filters = ref<DailyCreditUsageFilters>({ period: '30d' })
 * const { data: dailyUsage, isLoading } = useQuery(
 *   dailyCreditUsageQuery,
 *   () => ({ orgId: organizationId, filters: filters.value })
 * )
 */
export const dailyCreditUsageQuery = defineQueryOptions(
  ({ orgId, filters }: { orgId: string; filters: DailyCreditUsageFilters }) => ({
    key: CREDIT_QUERY_KEYS.dailyUsage(orgId, filters),
    query: () => {
      if (!orgId || orgId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getDailyCreditUsage(orgId, filters)
    },
  }),
)
