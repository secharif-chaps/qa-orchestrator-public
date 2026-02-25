/**
 * AI Preferences API Client
 *
 * Handles all API calls related to AI preferences and quick actions for Chapse Assist
 */

import { apiClient } from './client'
import type {
  AiPreferences,
  AiPreferencesCreate,
  QuickActionsRequest,
  QuickActionsResponse,
} from '@/types/ai-preferences'

/**
 * Get current user's AI preferences
 * @returns AI preferences or throws 404 if not configured
 */
export async function getAiPreferences(): Promise<AiPreferences> {
  const response = await apiClient.get<AiPreferences>('/ai-preferences')
  return response
}

/**
 * Create or update current user's AI preferences
 * @param preferences - AI preferences data
 * @returns Updated AI preferences
 */
export async function saveAiPreferences(preferences: AiPreferencesCreate): Promise<AiPreferences> {
  const response = await apiClient.post<AiPreferences>('/ai-preferences', preferences)
  return response
}

/**
 * Generate quick actions for a company
 * @param companyId - ID of the company
 * @returns 3 quick action recommendations
 * @throws 404 if AI preferences not configured or company not found
 * @throws 403 if user doesn't have access to the company
 * @throws 500 if AI generation fails
 */
export async function generateQuickActions(companyId: number): Promise<QuickActionsResponse> {
  const request: QuickActionsRequest = { company_id: companyId }
  const response = await apiClient.post<QuickActionsResponse>(
    '/ai-preferences/quick-actions',
    request,
  )
  return response
}

// Export as a single API object for consistency with other API modules
export const aiPreferencesApi = {
  getAiPreferences,
  saveAiPreferences,
  generateQuickActions,
}
