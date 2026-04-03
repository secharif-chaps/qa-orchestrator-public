/**
 * Pinia Colada mutations for global token management.
 *
 * These mutations handle token additions and module toggling.
 * Module-specific token mutations have been removed as tokens are now global.
 */

import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { addOrganizationTokens, toggleModule } from '@/api/tokens'
import type { ModuleName } from '@/types/tokens'
import { ORGANIZATION_TOKEN_KEYS } from '@/queries/tokens'
import { toast } from '@/utils/toast'

// ============================================================================
// Add Global Tokens Mutation
// ============================================================================

/**
 * Mutation to add tokens to an organization's global balance.
 * Requires admin.organizations role.
 *
 * @example
 * const { addTokens, isPending, organizationId, amount } = useAddGlobalTokens()
 *
 * organizationId.value = 'org-uuid-123'
 * amount.value = 175 // 5 companies worth (5 * 35)
 * await addTokens()
 */
export const useAddGlobalTokens = defineMutation(() => {
  const organizationId = ref<string | null>(null)
  const amount = ref<number>(0)
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ organizationId, amount }: { organizationId: string; amount: number }) =>
      addOrganizationTokens(organizationId, amount),
    onSuccess: (response, { organizationId }) => {
      toast.success(`Added ${amount.value} tokens to organization`)

      // Invalidate balance and history queries for this organization
      queryCache.invalidateQueries({ key: ORGANIZATION_TOKEN_KEYS.balance(organizationId) })
      // Invalidate all history queries for this organization (regardless of filters)
      queryCache.invalidateQueries({
        key: ORGANIZATION_TOKEN_KEYS.root,
        predicate: (query) => {
          const key = query.key as readonly unknown[]
          return (
            key[0] === 'organization-tokens' && key[1] === 'history' && key[2] === organizationId
          )
        },
      })

      // Reset form
      amount.value = 0
    },
    onError: (error: unknown) => {
      const errorMessage = error instanceof Error ? error.message : 'Failed to add tokens'
      toast.error(errorMessage)
    },
  })

  function addTokens() {
    if (!organizationId.value) {
      throw new Error('Organization ID is required')
    }
    if (amount.value <= 0) {
      throw new Error('Token amount must be positive')
    }

    return mutate({
      organizationId: organizationId.value,
      amount: amount.value,
    })
  }

  return {
    ...mutation,
    organizationId,
    amount,
    addTokens,
    mutate,
  }
})

// ============================================================================
// Toggle Module Mutation
// ============================================================================

/**
 * Mutation to toggle a module's enabled status.
 * Tokens are no longer per-module, this only affects module enablement.
 *
 * @example
 * const { toggleModule, isLoading } = useToggleModule()
 * await toggleModule({
 *   organizationId: 'org-uuid-123',
 *   module: 'screen',
 *   enabled: true
 * })
 */
export const useToggleModule = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      organizationId,
      module,
      enabled,
    }: {
      organizationId: string
      module: ModuleName
      enabled: boolean
    }) => toggleModule(organizationId, module, enabled),
    onSuccess: (response, { organizationId, enabled }) => {
      const action = enabled ? 'enabled' : 'disabled'
      toast.success(`${response.module} module ${action} successfully`)

      // Invalidate modules query
      queryCache.invalidateQueries({ key: ORGANIZATION_TOKEN_KEYS.modules(organizationId) })
    },
    onError: (error: unknown) => {
      const errorMessage = error instanceof Error ? error.message : 'Failed to toggle module'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    toggleModule: mutate,
  }
})
