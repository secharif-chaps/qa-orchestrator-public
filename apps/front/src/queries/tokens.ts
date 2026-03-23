/**
 * Pinia Colada queries for global token management.
 *
 * These queries fetch organization-level token balance and history.
 * Module token queries have been removed as tokens are now global.
 */

import { defineQueryOptions } from '@pinia/colada'
import { getOrganizationBalance, getTokenHistory, getOrganizationModules } from '@/api/tokens'
import type { TokenHistoryFilters } from '@/types/tokens'

// ============================================================================
// Query Keys
// ============================================================================

/**
 * Serialize filters to a stable string for use in query keys.
 * Ensures consistent key generation regardless of property order.
 */
function serializeFilters(filters: TokenHistoryFilters): string {
  return JSON.stringify(filters, Object.keys(filters).sort())
}

/**
 * Query keys for organization token operations.
 * Used for cache management and invalidation.
 */
export const ORGANIZATION_TOKEN_KEYS = {
  root: ['organization-tokens'] as const,
  balance: (organizationId: string) =>
    [...ORGANIZATION_TOKEN_KEYS.root, 'balance', organizationId] as const,
  history: (organizationId: string, filters: TokenHistoryFilters) =>
    [
      ...ORGANIZATION_TOKEN_KEYS.root,
      'history',
      organizationId,
      serializeFilters(filters),
    ] as const,
  modules: (organizationId: string) =>
    [...ORGANIZATION_TOKEN_KEYS.root, 'modules', organizationId] as const,
}

// ============================================================================
// Balance Query
// ============================================================================

/**
 * Query for organization's global token balance.
 *
 * @example
 * const { data: balance, isLoading } = useQuery(
 *   organizationBalanceQuery,
 *   () => ({ organizationId: orgContext.organizationId })
 * )
 */
export const organizationBalanceQuery = defineQueryOptions(
  ({ organizationId }: { organizationId: string }) => ({
    key: ORGANIZATION_TOKEN_KEYS.balance(organizationId),
    query: () => {
      if (!organizationId || organizationId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getOrganizationBalance(organizationId)
    },
  }),
)

// ============================================================================
// History Query
// ============================================================================

/**
 * Query for organization's token transaction history.
 * Supports filtering by transaction type, reference type, and date range.
 *
 * @example
 * const filters = ref<TokenHistoryFilters>({ page: 1, size: 10 })
 * const { data: history, isLoading } = useQuery(
 *   tokenHistoryQuery,
 *   () => ({ organizationId: orgContext.organizationId, filters: filters.value })
 * )
 */
export const tokenHistoryQuery = defineQueryOptions(
  ({ organizationId, filters }: { organizationId: string; filters: TokenHistoryFilters }) => ({
    key: ORGANIZATION_TOKEN_KEYS.history(organizationId, filters),
    query: () => {
      if (!organizationId || organizationId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getTokenHistory(organizationId, filters)
    },
  }),
)

// ============================================================================
// Modules Query (for enablement status only)
// ============================================================================

/**
 * Query for organization's module configurations.
 * Returns module enablement status only (no token counts).
 *
 * @example
 * const { data: modules, isLoading } = useQuery(
 *   organizationModulesQuery,
 *   () => ({ organizationId: orgContext.organizationId })
 * )
 */
export const organizationModulesQuery = defineQueryOptions(
  ({ organizationId }: { organizationId: string }) => ({
    key: ORGANIZATION_TOKEN_KEYS.modules(organizationId),
    query: () => {
      if (!organizationId || organizationId.trim() === '') {
        throw new Error('Invalid organization ID')
      }
      return getOrganizationModules(organizationId)
    },
    enabled: !!organizationId,
  }),
)
