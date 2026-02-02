/**
 * Feature flags type tests.
 *
 * Compile-time type tests that verify the frontend interfaces
 * match the expected API response structure from the backend.
 *
 * If this file compiles without errors, the interfaces are correct.
 */

import type {
  FeatureFlagName,
  FeatureFlagConfig,
  FeatureFlagToggleRequest,
} from './feature-flags'
import { FEATURE_FLAG_CONFIG, getFeatureFlagDisplayConfig } from './feature-flags'

// =============================================================================
// Test 1: 'discover' is valid in FeatureFlagName type
// =============================================================================

/**
 * Test that 'discover' is a valid FeatureFlagName value.
 * This will cause a compile error if 'discover' is not included in the type union.
 */
const discoverFlag: FeatureFlagName = 'discover'
const translationFlag: FeatureFlagName = 'translation'
const pappersFlag: FeatureFlagName = 'pappers'

// Verify both flags are distinct valid values
const validFlags: FeatureFlagName[] = ['discover', 'translation', 'pappers']

// =============================================================================
// Test 2: Config serialization works correctly in toggle request
// =============================================================================

/**
 * Test that FeatureFlagToggleRequest accepts optional config.
 */
const toggleRequestWithoutConfig: FeatureFlagToggleRequest = {
  enabled: true,
}

const toggleRequestWithConfig: FeatureFlagToggleRequest = {
  enabled: true,
  config: { url: 'https://discover.example.com' },
}

const toggleRequestWithNullConfig: FeatureFlagToggleRequest = {
  enabled: false,
  config: null,
}

// =============================================================================
// Test 3: FEATURE_FLAG_CONFIG includes discover entry
// =============================================================================

/**
 * Test that FEATURE_FLAG_CONFIG has entry for discover flag.
 */
const discoverConfig = FEATURE_FLAG_CONFIG['discover']
const hasDiscoverLabelKey: string = discoverConfig.labelKey
const hasDiscoverDescriptionKey: string = discoverConfig.descriptionKey
const hasDiscoverIcon: string = discoverConfig.icon

// =============================================================================
// Test 4: FeatureFlagConfig includes config field
// =============================================================================

/**
 * Test that FeatureFlagConfig has config field for storing URL.
 */
const featureFlagWithConfig: FeatureFlagConfig = {
  flag: 'discover',
  enabled: true,
  enabled_at: '2024-01-01T00:00:00Z',
  config: { url: 'https://discover.example.com' },
  created_at: '2024-01-01T00:00:00Z',
  updated_at: null,
}

const featureFlagWithoutConfig: FeatureFlagConfig = {
  flag: 'translation',
  enabled: true,
  enabled_at: '2024-01-01T00:00:00Z',
  config: null,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: null,
}

// =============================================================================
// Test 5: getFeatureFlagDisplayConfig works for discover
// =============================================================================

const discoverDisplayConfig = getFeatureFlagDisplayConfig('discover', true)
const hasFlag: FeatureFlagName = discoverDisplayConfig.flag
const hasEnabled: boolean = discoverDisplayConfig.enabled

// =============================================================================
// Export to prevent "unused variable" warnings
// =============================================================================
export {
  discoverFlag,
  translationFlag,
  pappersFlag,
  validFlags,
  toggleRequestWithoutConfig,
  toggleRequestWithConfig,
  toggleRequestWithNullConfig,
  discoverConfig,
  hasDiscoverLabelKey,
  hasDiscoverDescriptionKey,
  hasDiscoverIcon,
  featureFlagWithConfig,
  featureFlagWithoutConfig,
  discoverDisplayConfig,
  hasFlag,
  hasEnabled,
}
