/**
 * AI Preferences and Quick Actions Types
 * Used by Chapse Assist feature for personalized AI-powered recommendations
 */

/**
 * User's AI preferences for personalized assistance
 */
export interface AiPreferences {
  role: string
  goals_text: string
  desired_output_text: string
  documentation_text?: string
}

/**
 * Create/Update AI preferences payload
 */
export interface AiPreferencesCreate {
  role: string
  goals_text: string
  desired_output_text: string
  documentation_text?: string
}

/**
 * Quick action recommendation
 */
export interface QuickAction {
  id: string
  label: string
  description: string
  icon: string
}

/**
 * Quick actions generation request
 */
export interface QuickActionsRequest {
  company_id: number
}

/**
 * Quick actions generation response
 */
export interface QuickActionsResponse {
  actions: QuickAction[]
}

/**
 * Cached quick actions data structure
 */
export interface CachedQuickActions {
  actions: QuickAction[]
  companyId: number
  timestamp: number
  preferencesHash: string
}
