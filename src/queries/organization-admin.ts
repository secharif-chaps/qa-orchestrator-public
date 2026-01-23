import { defineQueryOptions } from '@pinia/colada'
import {
  getAllOrganizations,
  getOrganizationById,
  getOrganizationActivities,
  getOrganizationMembers
} from '@/api/organization'
import type { OrganizationQueryParams } from '@/types/organization'
import type { OrganizationMembersParams } from '@/api/organization'

// Define query keys for cache management
export const ORGANIZATION_QUERY_KEYS = {
  root: ['organizations'] as const,
  admin: ['organizations', 'admin'] as const,
  adminAll: (params?: OrganizationQueryParams) => ['organizations', 'admin', 'all', params] as const,
  adminById: (id: string) => ['organizations', 'admin', id] as const,
  activities: (organizationId: string) => ['organizations', organizationId, 'activities'] as const,
  members: (organizationId: string, params: Omit<OrganizationMembersParams, 'organizationId'>) =>
    ['organizations', organizationId, 'members', params] as const,
}

// Admin queries for managing all organizations
export const allOrganizationsQuery = defineQueryOptions((params: OrganizationQueryParams = {}) => ({
  key: ORGANIZATION_QUERY_KEYS.adminAll(params),
  query: () => getAllOrganizations(params),
}))

export const organizationByIdQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: ORGANIZATION_QUERY_KEYS.adminById(id),
  query: () => getOrganizationById(id),
}))

// Organization activities query
export const organizationActivitiesQuery = defineQueryOptions(({ organizationId }: { organizationId: string }) => ({
  key: ORGANIZATION_QUERY_KEYS.activities(organizationId),
  query: () => getOrganizationActivities(organizationId),
}))

// Organization members query (for admin org detail page)
export const organizationMembersQuery = defineQueryOptions((params: OrganizationMembersParams) => ({
  key: ORGANIZATION_QUERY_KEYS.members(params.organizationId, {
    page: params.page,
    limit: params.limit,
    search: params.search,
  }),
  query: () => getOrganizationMembers(params),
}))
