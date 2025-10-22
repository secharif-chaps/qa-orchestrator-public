import { ref, computed } from 'vue'
import { useQuery, useQueryCache } from '@pinia/colada'
import { moduleTokensQuery, TOKEN_QUERY_KEYS } from '@/queries/tokens'
import type { ModuleName } from '@/types/tokens'

/**
 * Composable for managing module tokens
 */
export const useModuleTokens = (workspaceId: number) => {
  const queryCache = useQueryCache()

  const getTokenCount = (module: ModuleName) => {
    const { data, isLoading, error } = useQuery(
      moduleTokensQuery,
      () => ({ workspaceId, module })
    )

    return {
      tokenCount: computed(() => data.value?.token_count ?? 0),
      isEnabled: computed(() => data.value?.enabled ?? false),
      isLoading,
      error,
    }
  }

  const checkTokenAvailability = (module: ModuleName, required: number = 1) => {
    const { tokenCount, isEnabled } = getTokenCount(module)
    
    return computed(() => {
      if (!isEnabled.value) return false
      return tokenCount.value >= required
    })
  }

  const refreshTokenData = (module?: ModuleName) => {
    if (module) {
      queryCache.invalidateQueries({ 
        key: TOKEN_QUERY_KEYS.moduleTokens(workspaceId, module) 
      })
    } else {
      queryCache.invalidateQueries({ 
        key: TOKEN_QUERY_KEYS.root 
      })
    }
  }

  const subscribeToTokenUpdates = () => {
    // This could be extended with WebSocket integration in the future
    const refreshInterval = ref<ReturnType<typeof setInterval> | null>(null)

    const startSubscription = (intervalMs = 30000) => {
      if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
      }
      
      refreshInterval.value = setInterval(() => {
        refreshTokenData()
      }, intervalMs)
    }

    const stopSubscription = () => {
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
    getTokenCount,
    checkTokenAvailability,
    refreshTokenData,
    subscribeToTokenUpdates,
  }
}