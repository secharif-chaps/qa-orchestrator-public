import { defineQueryOptions } from '@pinia/colada'
import { getOrganizationUsers, getOrganizationUser } from '@/api/user'

// Define query keys for cache management
export const USER_QUERY_KEYS = {
  root: ['users'] as const,
  organization: (organizationId: string) => ['users', 'organization', organizationId] as const,
  organizationUsers: (organizationId: string, page = 1, limit = 20) =>
    ['users', 'organization', organizationId, 'list', page, limit] as const,
  organizationUser: (organizationId: string, userId: string) =>
    ['users', 'organization', organizationId, 'user', userId] as const,
}

// organization user queries
export const organizationUsersQuery = defineQueryOptions(
  ({
    organizationId,
    page = 1,
    limit = 20,
  }: {
    organizationId: string
    page?: number
    limit?: number
  }) => ({
    key: USER_QUERY_KEYS.organizationUsers(organizationId, page, limit),
    query: () => getOrganizationUsers(organizationId, page, limit),
  }),
)

export const organizationUserQuery = defineQueryOptions(
  ({ organizationId, userId }: { organizationId: string; userId: string }) => ({
    key: USER_QUERY_KEYS.organizationUser(organizationId, userId),
    query: () => getOrganizationUser(organizationId, userId),
  }),
)
