/**
 * Feature flags types for organization capabilities.
 *
 * Feature flags are add-on capabilities that enhance core modules.
 * Unlike core modules (screen, target, explore), feature flags are
 * OFF by default and provide enhancements rather than standalone features.
 */

// Feature flag names - matches backend FeatureFlag enum
export type FeatureFlagName = 'translation'

/**
 * Feature flag configuration for an organization.
 */
export interface FeatureFlagConfig {
  flag: FeatureFlagName
  enabled: boolean
  enabled_at: string | null
  config: Record<string, unknown> | null
  created_at: string
  updated_at: string | null
}

/**
 * Response for organization feature flags query.
 */
export interface FeatureFlagsResponse {
  feature_flags: FeatureFlagConfig[]
}

/**
 * Request to toggle a feature flag.
 */
export interface FeatureFlagToggleRequest {
  enabled: boolean
}

/**
 * Response after toggling a feature flag.
 */
export interface FeatureFlagToggleResponse {
  flag: FeatureFlagName
  enabled: boolean
  enabled_at: string | null
}

/**
 * Display configuration for feature flags in UI.
 */
export interface FeatureFlagDisplayConfig {
  flag: FeatureFlagName
  labelKey: string
  descriptionKey: string
  icon: string
  enabled: boolean
}

/**
 * Feature flag display configurations.
 */
export const FEATURE_FLAG_CONFIG: Record<FeatureFlagName, Omit<FeatureFlagDisplayConfig, 'enabled' | 'flag'>> = {
  translation: {
    labelKey: 'featureFlags.translation.name',
    descriptionKey: 'featureFlags.translation.description',
    icon: 'fa fa-language',
  },
}

/**
 * Get display config for a feature flag.
 */
export const getFeatureFlagDisplayConfig = (
  flag: FeatureFlagName,
  enabled: boolean,
): FeatureFlagDisplayConfig => {
  const config = FEATURE_FLAG_CONFIG[flag]
  return {
    flag,
    ...config,
    enabled,
  }
}
