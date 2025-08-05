import { defineQueryOptions } from '@pinia/colada'
import { 
  getWorkspaceUsers,
  getWorkspaceUser,
} from '@/api/user'

// Define query keys for cache management
export const USER_QUERY_KEYS = {
  root: ['users'] as const,
  workspace: (workspaceId: number) => ['users', 'workspace', workspaceId] as const,
  workspaceUsers: (workspaceId: number, page = 1, limit = 20) => ['users', 'workspace', workspaceId, 'list', page, limit] as const,
  workspaceUser: (workspaceId: number, userId: string) => ['users', 'workspace', workspaceId, 'user', userId] as const,
}

// Workspace user queries
export const workspaceUsersQuery = defineQueryOptions(({ workspaceId, page = 1, limit = 20 }: { workspaceId: number, page?: number, limit?: number }) => ({
  key: USER_QUERY_KEYS.workspaceUsers(workspaceId, page, limit),
  query: () => getWorkspaceUsers(workspaceId, page, limit),
}))

export const workspaceUserQuery = defineQueryOptions(({ workspaceId, userId }: { workspaceId: number, userId: string }) => ({
  key: USER_QUERY_KEYS.workspaceUser(workspaceId, userId),
  query: () => getWorkspaceUser(workspaceId, userId),
}))