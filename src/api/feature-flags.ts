/**
 * Feature flags API functions.
 *
 * Provides endpoints for managing organization feature flags.
 */

import { apiClient } from './client'
import type {
  FeatureFlagsResponse,
  FeatureFlagName,
  FeatureFlagToggleRequest,
  FeatureFlagToggleResponse,
} from '@/types/feature-flags'

/**
 * Get all feature flags for an organization.
 */
export const getOrganizationFeatureFlags = async (
  organizationId: string,
): Promise<FeatureFlagsResponse> => {
  return apiClient.get<FeatureFlagsResponse>(`/organizations/${organizationId}/feature-flags`)
}

/**
 * Toggle a feature flag for an organization.
 * Optionally accepts config data (e.g., URL for discover flag).
 */
export const toggleFeatureFlag = async (
  organizationId: string,
  flag: FeatureFlagName,
  data: FeatureFlagToggleRequest,
): Promise<FeatureFlagToggleResponse> => {
  return apiClient.patch<FeatureFlagToggleResponse>(
    `/organizations/${organizationId}/feature-flags/${flag}`,
    data,
  )
}
