import { defineQueryOptions } from '@pinia/colada'
import {
  getAllWorkspaces,
  getWorkspaceById,
  getWorkspaceDetails,
  getCurrentWorkspace,
  getCurrentWorkspaceWithMembers,
  getWorkspaceMembers,
  getWorkspaceActivities
} from '@/api/workspace'
import type { WorkspaceQueryParams } from '@/types/workspace'

// Define query keys for cache management
export const WORKSPACE_QUERY_KEYS = {
  root: ['workspaces'] as const,
  admin: ['workspaces', 'admin'] as const,
  adminAll: (params?: WorkspaceQueryParams) => ['workspaces', 'admin', 'all', params] as const,
  adminById: (id: number) => ['workspaces', 'admin', id] as const,
  adminDetails: (id: number) => ['workspaces', 'admin', id, 'details'] as const,
  adminMembers: (id: number) => ['workspaces', 'admin', id, 'members'] as const,
  current: ['workspaces', 'current'] as const,
  currentWithMembers: ['workspaces', 'current', 'with-members'] as const,
  activities: (workspaceId: number) => ['workspaces', workspaceId, 'activities'] as const,
}

// Admin queries
export const allWorkspacesQuery = defineQueryOptions((params: WorkspaceQueryParams = {}) => ({
  key: WORKSPACE_QUERY_KEYS.adminAll(params),
  query: () => getAllWorkspaces(params),
}))

export const workspaceByIdQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: WORKSPACE_QUERY_KEYS.adminById(id),
  query: () => getWorkspaceById(id),
}))

export const workspaceDetailsQuery = defineQueryOptions(({ id }: { id: number }) => ({
  key: WORKSPACE_QUERY_KEYS.adminDetails(id),
  query: () => getWorkspaceDetails(id),
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

// Workspace activities query
export const workspaceActivitiesQuery = defineQueryOptions(({ workspaceId }: { workspaceId: number }) => ({
  key: WORKSPACE_QUERY_KEYS.activities(workspaceId),
  query: () => getWorkspaceActivities(workspaceId),
}))