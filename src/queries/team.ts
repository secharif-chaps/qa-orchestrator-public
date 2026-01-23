import { defineQueryOptions } from '@pinia/colada'
import { getTeamMembers, getMemberPermissions } from '@/api/team'
import type { TeamMembersParams } from '@/api/team'

export const TEAM_QUERY_KEYS = {
  root: ['team'] as const,
  members: (params: Omit<TeamMembersParams, 'search'> & { search?: string }) =>
    [...TEAM_QUERY_KEYS.root, 'members', params] as const,
  memberPermissions: (userId: string) => [...TEAM_QUERY_KEYS.root, 'permissions', userId] as const,
}

/**
 * Query for paginated team members list (without permissions)
 */
export const teamMembersQuery = defineQueryOptions((params: TeamMembersParams) => ({
  key: TEAM_QUERY_KEYS.members({ page: params.page, limit: params.limit, search: params.search }),
  query: () => getTeamMembers(params),
}))

/**
 * Query for single member's permissions (lazy-loaded)
 */
export const memberPermissionsQuery = defineQueryOptions(({ userId }: { userId: string }) => ({
  key: TEAM_QUERY_KEYS.memberPermissions(userId),
  query: () => getMemberPermissions(userId),
}))
