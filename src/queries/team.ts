import { defineQueryOptions } from '@pinia/colada'
import { 
  getWorkspaceUsers,
  getWorkspaceUser
} from '@/api/team'
import type { WorkspaceUserQueryParams } from '@/types/team'

export const TEAM_QUERY_KEYS = {
  root: ['team'] as const,
  users: ['team', 'users'] as const,
  usersList: (params?: WorkspaceUserQueryParams) => ['team', 'users', 'list', params] as const,
  user: (id: number) => ['team', 'users', id] as const,
}

export const workspaceUsersQuery = defineQueryOptions((params: WorkspaceUserQueryParams = {}) => ({
  key: TEAM_QUERY_KEYS.usersList(params),
  query: () => getWorkspaceUsers(params),
}))

export const workspaceUserQuery = defineQueryOptions(({ userId }: { userId: number }) => ({
  key: TEAM_QUERY_KEYS.user(userId),
  query: () => getWorkspaceUser(userId),
}))