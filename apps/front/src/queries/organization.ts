import { defineQueryOptions } from '@pinia/colada'
import { getCurrentOrganization, getOrganizationActivities } from '@/api/organization'

/**
 * Query keys for organization-related queries
 */
export const ORGANIZATION_QUERY_KEYS = {
  root: ['organization'] as const,
  current: () => [...ORGANIZATION_QUERY_KEYS.root, 'current'] as const,
  activities: () => [...ORGANIZATION_QUERY_KEYS.root, 'activities'] as const,
}

/**
 * Query for current user's organization context.
 * Organization info is extracted from JWT token.
 */
export const currentOrganizationQuery = defineQueryOptions(() => ({
  key: ORGANIZATION_QUERY_KEYS.current(),
  query: () => getCurrentOrganization(),
}))

/**
 * Query for recent organization activities.
 * Shows last 10 companies and folders created by other users.
 */
export const organizationActivitiesQuery = defineQueryOptions(() => ({
  key: ORGANIZATION_QUERY_KEYS.activities(),
  query: () => getOrganizationActivities(),
  staleTime: 1000 * 60, // 1 minute - activities should be relatively fresh
}))
