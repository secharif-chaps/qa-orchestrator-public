/**
 * Composable for token validation and action authorization.
 *
 * Validates whether the organization has sufficient global tokens
 * for various actions. Token balance is now global per organization,
 * not per-module.
 */

import { computed } from 'vue'
import { useGlobalTokens, useTokenConfig } from './useGlobalTokens'
import { useCompanyPermissions } from './useCompanyPermissions'
import { useQuery } from '@pinia/colada'
import { organizationModulesQuery } from '@/queries/tokens'
import type { ModuleName, InsufficientTokensError } from '@/types/tokens'

/**
 * Composable for validating token availability for actions.
 *
 * @param organizationId - The Keycloak organization UUID
 * @returns Validation functions and token state
 *
 * @example
 * const { canPerformScreenAction, getInsufficientTokensMessage } = useTokenValidation('org-123')
 *
 * // Check if user can create a company
 * if (canPerformScreenAction().value) {
 *   // Allow action
 * } else {
 *   const error = getInsufficientTokensMessage()
 *   // Show error to user
 * }
 */
export function useTokenValidation(organizationId: string) {
  const globalTokens = useGlobalTokens(organizationId)
  const companyPermissions = useCompanyPermissions()
  const { tokensPerCompany } = useTokenConfig()

  // Token costs for different actions — derived from backend config
  const getActionCost = (action: string): number => {
    if (action === 'bulk_search') return tokensPerCompany.value * 5
    return tokensPerCompany.value
  }

  // Query modules to check enablement status
  const { data: modulesData, isLoading: isLoadingModules } = useQuery(() =>
    organizationModulesQuery({ organizationId }),
  )

  // Check if a module is enabled
  function isModuleEnabled(module: ModuleName): boolean {
    if (!modulesData.value?.modules) return false
    const moduleConfig = modulesData.value.modules.find((m) => m.name === module)
    return moduleConfig?.enabled ?? false
  }

  /**
   * Validate whether an action can be performed.
   * Checks both module enablement AND global token balance.
   */
  function validateAction(module: ModuleName, action: string) {
    const tokenCost = getActionCost(action)
    const hasTokens = computed(() => globalTokens.hasSufficientTokens(tokenCost))
    const moduleEnabled = computed(() => isModuleEnabled(module))

    return {
      canPerform: computed(() => moduleEnabled.value && hasTokens.value),
      tokenCost,
      currentBalance: globalTokens.balance,
      isModuleEnabled: moduleEnabled,
      reason: computed(() => {
        if (!moduleEnabled.value) return `${module} module is disabled`
        if (!hasTokens.value)
          return `Insufficient tokens (${globalTokens.balance.value}/${tokenCost} required)`
        return null
      }),
    }
  }

  /**
   * Check if an action can be performed (simple boolean check).
   */
  function canPerformAction(module: ModuleName, action: string = 'create_company') {
    const { canPerform } = validateAction(module, action)
    return canPerform
  }

  /**
   * Get error message if tokens are insufficient.
   * Returns null if there are enough tokens.
   */
  function getInsufficientTokensMessage(
    module: ModuleName,
    action: string = 'create_company',
  ): InsufficientTokensError | null {
    const tokenCost = getActionCost(action)
    const moduleEnabled = isModuleEnabled(module)

    // If module is disabled, no token error (different error type)
    if (!moduleEnabled) return null

    if (globalTokens.balance.value < tokenCost) {
      return {
        error: 'insufficient_tokens',
        message: `Not enough tokens for this operation. Required: ${tokenCost}, Available: ${globalTokens.balance.value}`,
        current_balance: globalTokens.balance.value,
        required_tokens: tokenCost,
      }
    }

    return null
  }

  /**
   * Combined validation for screen module actions (company creation).
   * Checks permission AND module enablement AND token balance.
   */
  function canPerformScreenAction(action: string = 'create_company') {
    const hasPermission = companyPermissions.canCreateCompany
    const { canPerform: hasTokensAndModule } = validateAction('screen', action)

    return computed(() => hasPermission.value && hasTokensAndModule.value)
  }

  /**
   * Get all validation info for a module.
   */
  function getModuleValidation(module: ModuleName) {
    const moduleEnabled = computed(() => isModuleEnabled(module))

    return {
      balance: globalTokens.balance,
      isModuleEnabled: moduleEnabled,
      isLoading: computed(() => globalTokens.isLoading.value || isLoadingModules.value),
      error: globalTokens.error,
      hasAnyTokens: computed(() => globalTokens.balance.value > 0),
      canUseModule: computed(
        () => moduleEnabled.value && globalTokens.balance.value >= tokensPerCompany.value,
      ),
      companyEquivalent: globalTokens.companyEquivalent,
    }
  }

  return {
    // Validation methods
    validateAction,
    canPerformAction,
    canPerformScreenAction,
    getInsufficientTokensMessage,
    getModuleValidation,
    isModuleEnabled,

    // Token data (convenience re-exports)
    balance: globalTokens.balance,
    companyEquivalent: globalTokens.companyEquivalent,
    canCreateCompany: globalTokens.canCreateCompany,
    isLoading: computed(() => globalTokens.isLoading.value || isLoadingModules.value),

    // Refresh methods
    refreshTokenData: globalTokens.refreshTokenData,
    subscribeToTokenUpdates: globalTokens.subscribeToTokenUpdates,
  }
}
