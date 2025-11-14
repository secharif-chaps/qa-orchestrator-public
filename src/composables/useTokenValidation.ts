import { computed } from 'vue'
import { useModuleTokens } from './useModuleTokens'
import { useCompanyPermissions } from './useCompanyPermissions'
import type { ModuleName, InsufficientTokensError } from '@/types/tokens'

// Define token costs for different actions
const ACTION_TOKEN_COSTS: Record<string, Record<string, number>> = {
  screen: {
    search_company: 1,
    bulk_search: 5,
    advanced_search: 2,
  },
  target: {
    basic_targeting: 1,
    advanced_targeting: 3,
  },
  explore: {
    basic_exploration: 1,
    deep_dive: 5,
  },
  stream: {
    basic_streaming: 1,
    premium_streaming: 3,
  },
}

/**
 * Composable for token validation and action authorization
 */
export const useTokenValidation = (organizationId: number) => {
  const moduleTokens = useModuleTokens(organizationId)
  const companyPermissions = useCompanyPermissions()

  const validateAction = (module: ModuleName, action: string) => {
    const tokenCost = ACTION_TOKEN_COSTS[module]?.[action] ?? 1
    const hasTokens = moduleTokens.checkTokenAvailability(module, tokenCost)
    const { tokenCount, isEnabled } = moduleTokens.getTokenCount(module)

    return {
      canPerform: hasTokens,
      tokenCost,
      currentTokens: tokenCount,
      isEnabled,
      reason: computed(() => {
        if (!isEnabled.value) return `${module} module is disabled`
        if (!hasTokens.value) return `Insufficient tokens (${tokenCount.value}/${tokenCost} required)`
        return null
      }),
    }
  }

  const canPerformAction = (module: ModuleName, action: string = 'default') => {
    const { canPerform } = validateAction(module, action)
    return canPerform
  }

  const getInsufficientTokensMessage = (
    module: ModuleName, 
    action: string = 'default'
  ): InsufficientTokensError | null => {
    const tokenCost = ACTION_TOKEN_COSTS[module]?.[action] ?? 1
    const { tokenCount, isEnabled } = moduleTokens.getTokenCount(module)

    if (!isEnabled.value) return null
    
    if (tokenCount.value < tokenCost) {
      return {
        error: 'insufficient_tokens',
        message: `Not enough tokens for this operation. Required: ${tokenCost}, Available: ${tokenCount.value}`,
        current_tokens: tokenCount.value,
        required_tokens: tokenCost,
        module,
      }
    }

    return null
  }

  // Combined validation for screen module with company permissions
  const canPerformScreenAction = (action: string = 'search_company') => {
    const hasPermission = companyPermissions.canCreateCompany
    const hasTokens = canPerformAction('screen', action)

    return computed(() => hasPermission.value && hasTokens.value)
  }

  // Get all validation info for a module
  const getModuleValidation = (module: ModuleName) => {
    const { tokenCount, isEnabled, isLoading, error } = moduleTokens.getTokenCount(module)

    return {
      tokenCount,
      isEnabled,
      isLoading,
      error,
      hasAnyTokens: computed(() => tokenCount.value > 0),
      canUseModule: computed(() => isEnabled.value && tokenCount.value > 0),
    }
  }

  return {
    validateAction,
    canPerformAction,
    canPerformScreenAction,
    getInsufficientTokensMessage,
    getModuleValidation,
    
    // Re-export module tokens methods for convenience
    refreshTokenData: moduleTokens.refreshTokenData,
    subscribeToTokenUpdates: moduleTokens.subscribeToTokenUpdates,
  }
}