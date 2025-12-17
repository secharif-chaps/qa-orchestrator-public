/**
 * Composable for global organization token management.
 *
 * Provides reactive access to the organization's global token balance
 * and cache invalidation utilities. Tokens are now organization-level,
 * not per-module.
 */

import { ref, computed } from 'vue'
import { useQuery, useQueryCache } from '@pinia/colada'
import { organizationBalanceQuery, ORGANIZATION_TOKEN_KEYS } from '@/queries/tokens'

// Token cost for company creation - consistent with backend
export const TOKENS_PER_COMPANY = 35

/**
 * Composable for accessing and managing global token balance.
 *
 * @param organizationId - The Keycloak organization UUID
 * @returns Token balance data and utility functions
 *
 * @example
 * const { balance, isLoading, refreshTokenData, companyEquivalent } = useGlobalTokens('org-123')
 *
 * // Display balance
 * <span>{{ balance }} tokens ({{ companyEquivalent }} companies)</span>
 *
 * // Refresh after mutation
 * await addTokensMutation()
 * refreshTokenData()
 */
export function useGlobalTokens(organizationId: string) {
  const queryCache = useQueryCache()

  // Query the global token balance using the spread pattern
  const { data, isLoading, error, refetch } = useQuery({
    ...organizationBalanceQuery({ organizationId }),
    enabled: () => !!organizationId && organizationId.trim() !== '',
  })

  // Computed balance value with fallback
  const balance = computed(() => data.value?.balance ?? 0)

  // Calculate how many companies can be created with current balance
  const companyEquivalent = computed(() => Math.floor(balance.value / TOKENS_PER_COMPANY))

  // Check if there are sufficient tokens for an action
  function hasSufficientTokens(required: number): boolean {
    return balance.value >= required
  }

  // Check if user can create a company (has at least TOKENS_PER_COMPANY tokens)
  const canCreateCompany = computed(() => hasSufficientTokens(TOKENS_PER_COMPANY))

  // Invalidate the balance query to force a refresh
  function refreshTokenData() {
    queryCache.invalidateQueries({
      key: ORGANIZATION_TOKEN_KEYS.balance(organizationId),
    })
  }

  // Invalidate all token-related queries for this organization
  function refreshAllTokenData() {
    // Invalidate balance
    queryCache.invalidateQueries({
      key: ORGANIZATION_TOKEN_KEYS.balance(organizationId),
    })

    // Invalidate history queries
    queryCache.invalidateQueries({
      key: ORGANIZATION_TOKEN_KEYS.root,
      predicate: (query) => {
        const key = query.key as readonly unknown[]
        return (
          key[0] === 'organization-tokens' &&
          key[1] === 'history' &&
          key[2] === organizationId
        )
      },
    })
  }

  // Optional polling for token updates (useful for long-running operations)
  function subscribeToTokenUpdates() {
    const refreshInterval = ref<ReturnType<typeof setInterval> | null>(null)

    function startSubscription(intervalMs = 30000) {
      if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
      }

      refreshInterval.value = setInterval(() => {
        refreshTokenData()
      }, intervalMs)
    }

    function stopSubscription() {
      if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
        refreshInterval.value = null
      }
    }

    return {
      startSubscription,
      stopSubscription,
    }
  }

  return {
    // Data
    balance,
    companyEquivalent,
    canCreateCompany,
    isLoading,
    error,

    // Methods
    hasSufficientTokens,
    refreshTokenData,
    refreshAllTokenData,
    refetch,
    subscribeToTokenUpdates,
  }
}
