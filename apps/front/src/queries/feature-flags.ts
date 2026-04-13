/**
 * Feature flags queries for Pinia Colada.
 */

import { defineQueryOptions } from '@pinia/colada'
import { getOrganizationFeatureFlags } from '@/api/feature-flags'

// Query keys
export const FEATURE_FLAGS_QUERY_KEYS = {
  root: ['feature-flags'] as const,
  byOrganization: (organizationId: string) =>
    [...FEATURE_FLAGS_QUERY_KEYS.root, organizationId] as const,
}

/**
 * Query for organization feature flags.
 */
export const organizationFeatureFlagsQuery = defineQueryOptions(
  ({ organizationId }: { organizationId: string }) => ({
    key: FEATURE_FLAGS_QUERY_KEYS.byOrganization(organizationId),
    enabled: !!organizationId && organizationId.trim() !== '',
    query: () => getOrganizationFeatureFlags(organizationId),
  }),
)
