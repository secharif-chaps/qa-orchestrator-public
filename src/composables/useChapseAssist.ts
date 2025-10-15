/**
 * Chapse Assist Composable
 *
 * Manages AI-powered quick actions and personalized assistance for company research.
 * Features:
 * - Fetches 3 quick action recommendations based on user preferences and company data
 * - Caches actions for 24 hours per company with preferences hash validation
 * - Integrates with Chapse sidebar to execute quick actions
 * - Handles errors and retries gracefully
 */

import { ref, computed } from 'vue'
import { getAiPreferences, generateQuickActions } from '@/api/ai-preferences'
import type {
  AiPreferences,
  QuickAction,
  CachedQuickActions,
} from '@/types/ai-preferences'

const CACHE_KEY_PREFIX = 'chapse_assist_actions_'
const CACHE_DURATION = 24 * 60 * 60 * 1000 // 24 hours in milliseconds

export function useChapseAssist() {
  // State for AI preferences
  const hasAiPreferences = ref<boolean>(false)
  const preferences = ref<AiPreferences | null>(null)

  // State for quick actions
  const quickActions = ref<QuickAction[]>([])
  const isLoadingActions = ref<boolean>(false)
  const actionsError = ref<string | null>(null)

  /**
   * Check if user has AI preferences configured
   */
  async function checkHasPreferences(): Promise<boolean> {
    try {
      const response = await getAiPreferences()
      preferences.value = response
      hasAiPreferences.value = true
      return true
    } catch (error: any) {
      if (error.status === 404) {
        hasAiPreferences.value = false
        preferences.value = null
        return false
      }
      throw error
    }
  }

  /**
   * Generate a hash of user preferences for cache validation
   */
  function hashPreferences(prefs: AiPreferences): string {
    const str = JSON.stringify({
      role: prefs.role,
      goals: prefs.goals_text,
      output: prefs.desired_output_text,
      doc: prefs.documentation_text,
    })
    // Simple hash function for cache validation
    let hash = 0
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i)
      hash = (hash << 5) - hash + char
      hash = hash & hash // Convert to 32bit integer
    }
    return hash.toString(36)
  }

  /**
   * Get cached quick actions for a company if still valid
   */
  function getCachedActions(companyId: number): QuickAction[] | null {
    if (!preferences.value) return null

    const cacheKey = `${CACHE_KEY_PREFIX}${companyId}`
    const cached = localStorage.getItem(cacheKey)

    if (!cached) return null

    try {
      const data: CachedQuickActions = JSON.parse(cached)

      // Validate cache age (24 hours)
      const now = Date.now()
      const age = now - data.timestamp
      if (age > CACHE_DURATION) {
        localStorage.removeItem(cacheKey)
        return null
      }

      // Validate preferences haven't changed
      const currentHash = hashPreferences(preferences.value)
      if (data.preferencesHash !== currentHash) {
        localStorage.removeItem(cacheKey)
        return null
      }

      return data.actions
    } catch (error) {
      // Invalid cache data, remove it
      localStorage.removeItem(cacheKey)
      return null
    }
  }

  /**
   * Cache quick actions for a company
   */
  function cacheActions(companyId: number, actions: QuickAction[]): void {
    if (!preferences.value) return

    const cacheKey = `${CACHE_KEY_PREFIX}${companyId}`
    const data: CachedQuickActions = {
      actions,
      companyId,
      timestamp: Date.now(),
      preferencesHash: hashPreferences(preferences.value),
    }

    try {
      localStorage.setItem(cacheKey, JSON.stringify(data))
    } catch (error) {
      console.error('Failed to cache quick actions:', error)
      // Continue execution even if caching fails
    }
  }

  /**
   * Fetch quick actions for a company from the backend
   */
  async function fetchQuickActions(companyId: number): Promise<QuickAction[]> {
    isLoadingActions.value = true
    actionsError.value = null

    try {
      // Ensure preferences are loaded (needed for caching and openQuickAction)
      if (!preferences.value) {
        await checkHasPreferences()
      }

      // Check cache first
      const cached = getCachedActions(companyId)
      if (cached) {
        quickActions.value = cached
        isLoadingActions.value = false
        return cached
      }

      // Fetch from backend
      const response = await generateQuickActions(companyId)
      const actions = response.actions

      // Validate response structure
      if (!Array.isArray(actions) || actions.length === 0) {
        throw new Error('Invalid quick actions response')
      }

      // Cache the results
      cacheActions(companyId, actions)

      quickActions.value = actions
      return actions
    } catch (error: any) {
      console.error('Failed to fetch quick actions:', error)

      if (error.status === 404) {
        // Check error message to determine if it's AI preferences or company not found
        const errorMessage = error.message?.toLowerCase() || ''
        if (errorMessage.includes('preferences')) {
          actionsError.value = 'Please configure your AI preferences first'
        } else {
          actionsError.value = 'Company not found'
        }
      } else if (error.status === 403) {
        actionsError.value = 'Access denied to this company'
      } else if (error.status === 500) {
        actionsError.value = 'Failed to generate actions. Please try again.'
      } else {
        actionsError.value = 'Failed to load quick actions'
      }

      throw error
    } finally {
      isLoadingActions.value = false
    }
  }

  /**
   * Retry fetching quick actions with exponential backoff
   */
  async function retryFetchActions(
    companyId: number,
    maxRetries: number = 3
  ): Promise<QuickAction[]> {
    let lastError: Error | null = null

    for (let attempt = 0; attempt < maxRetries; attempt++) {
      try {
        return await fetchQuickActions(companyId)
      } catch (error: any) {
        lastError = error

        // Don't retry on 403/404 errors
        if (error.status === 403 || error.status === 404) {
          throw error
        }

        // Wait before retrying (exponential backoff)
        if (attempt < maxRetries - 1) {
          const delay = Math.pow(2, attempt) * 1000 // 1s, 2s, 4s
          await new Promise((resolve) => setTimeout(resolve, delay))
        }
      }
    }

    throw lastError || new Error('Failed to fetch quick actions after retries')
  }

  /**
   * Clear cached actions for a company
   */
  function clearActionsCache(companyId: number): void {
    const cacheKey = `${CACHE_KEY_PREFIX}${companyId}`
    localStorage.removeItem(cacheKey)
  }

  /**
   * Clear all cached actions
   */
  function clearAllActionsCache(): void {
    const keys = Object.keys(localStorage)
    keys.forEach((key) => {
      if (key.startsWith(CACHE_KEY_PREFIX)) {
        localStorage.removeItem(key)
      }
    })
  }

  /**
   * Open Chapse sidebar and execute a quick action
   * This triggers the chatbot with assist_action context
   */
  function openQuickAction(action: QuickAction, companyId: number): void {
    if (!preferences.value) {
      console.error('Cannot execute action: AI preferences not loaded')
      return
    }

    // Emit custom event that ChapseSidebar listens to
    const event = new CustomEvent('chapse-assist-action', {
      detail: {
        action: {
          id: action.id,
          label: action.label,
          description: action.description,
        },
        user_preferences: {
          role: preferences.value.role,
          goals: preferences.value.goals_text,
          desired_output: preferences.value.desired_output_text,
          documentation: preferences.value.documentation_text,
        },
        companyId,
      },
    })

    console.log('🚀 Dispatching chapse-assist-action event:', event.detail)
    window.dispatchEvent(event)
    console.log('✅ Event dispatched successfully')
  }

  // Computed states
  const hasActions = computed(() => quickActions.value.length > 0)
  const hasError = computed(() => actionsError.value !== null)

  return {
    // State
    hasAiPreferences,
    preferences,
    quickActions,
    isLoadingActions,
    actionsError,

    // Computed
    hasActions,
    hasError,

    // Methods
    checkHasPreferences,
    fetchQuickActions,
    retryFetchActions,
    clearActionsCache,
    clearAllActionsCache,
    openQuickAction,
  }
}
