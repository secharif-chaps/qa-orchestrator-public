import { defineQueryOptions } from '@pinia/colada'
import { getCurrentWorkspace } from '@/api/workspace'

export const WORKSPACE_QUERY_KEYS = {
  root: ['workspace'] as const,
  current: () => [...WORKSPACE_QUERY_KEYS.root, 'current'] as const,
}

export const currentWorkspaceQuery = defineQueryOptions(() => ({
  key: WORKSPACE_QUERY_KEYS.current(),
  query: () => getCurrentWorkspace(),
}))