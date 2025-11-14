import { defineQueryOptions } from '@pinia/colada'
import { getOrganizationUsers, getOrganizationUser } from '@/api/team'
import type { OrganizationUserQueryParams } from '@/types/team'

export const TEAM_QUERY_KEYS = {
  root: ['team'] as const,
  users: ['team', 'users'] as const,
  usersList: (params?: OrganizationUserQueryParams) => ['team', 'users', 'list', params] as const,
  user: (id: number) => ['team', 'users', id] as const,
}

export const organizationUsersQuery = defineQueryOptions(
  (params: OrganizationUserQueryParams = {}) => ({
    key: TEAM_QUERY_KEYS.usersList(params),
    query: () => getOrganizationUsers(params),
  }),
)

export const organizationUserQuery = defineQueryOptions(({ userId }: { userId: number }) => ({
  key: TEAM_QUERY_KEYS.user(userId),
  query: () => getOrganizationUser(userId),
}))
