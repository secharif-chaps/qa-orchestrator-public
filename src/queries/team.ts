import { defineQueryOptions } from '@pinia/colada'
import { getTeamMembers } from '@/api/team'

export const TEAM_QUERY_KEYS = {
  root: ['team'] as const,
  members: (search?: string) => [...TEAM_QUERY_KEYS.root, 'members', { search }] as const,
}

/**
 * Query for team members list
 */
export const teamMembersQuery = defineQueryOptions(({ search }: { search?: string }) => ({
  key: TEAM_QUERY_KEYS.members(search),
  query: () => getTeamMembers(search),
}))
