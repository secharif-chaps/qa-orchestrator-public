import { defineQueryOptions } from '@pinia/colada'
import { 
  getAllWorkspaces, 
  getWorkspaceById, 
  getCurrentWorkspace,
  getCurrentWorkspaceWithMembers,
  getWorkspaceMembers
} from '@/api/workspace'

// Define query keys for cache management
export const WORKSPACE_QUERY_KEYS = {
  root: ['workspaces'] as const,
  admin: ['workspaces', 'admin'] as const,
  adminAll: ['workspaces', 'admin', 'all'] as const,
  adminById: (id: number) => ['workspaces', 'admin', id] as const,
  adminMembers: (id: number) => ['workspaces', 'admin', id, 'members'] as const,
  current: ['workspaces', 'current'] as const,
  currentWithMembers: ['workspaces', 'current', 'with-members'] as const,
}

// Admin queries
export const allWorkspacesQuery = defineQueryOptions(() => ({
  key: WORKSPACE_QUERY_KEYS.adminAll,
  query: () => getAllWorkspaces(),
}))

export const workspaceByIdQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: WORKSPACE_QUERY_KEYS.adminById(id),
  query: () => getWorkspaceById(id),
}))

export const workspaceMembersQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: WORKSPACE_QUERY_KEYS.adminMembers(id),
  query: () => getWorkspaceMembers(id),
}))

// Regular user queries
export const currentWorkspaceQuery = defineQueryOptions(() => ({
  key: WORKSPACE_QUERY_KEYS.current,
  query: () => getCurrentWorkspace(),
}))

export const currentWorkspaceWithMembersQuery = defineQueryOptions(() => ({
  key: WORKSPACE_QUERY_KEYS.currentWithMembers,
  query: () => getCurrentWorkspaceWithMembers(),
}))