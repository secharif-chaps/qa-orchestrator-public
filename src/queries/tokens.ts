import { defineQueryOptions } from '@pinia/colada'
import { getModuleTokens, getWorkspaceModules } from '@/api/tokens'
import type { ModuleName } from '@/types/tokens'

// Export query keys for cache management
export const TOKEN_QUERY_KEYS = {
  root: ['tokens'] as const,
  moduleTokens: (workspaceId: number, module: ModuleName) => 
    [...TOKEN_QUERY_KEYS.root, 'module', workspaceId, module] as const,
  workspaceModules: (workspaceId: number) => 
    [...TOKEN_QUERY_KEYS.root, 'workspace', workspaceId] as const,
}

// Query for single module token count
export const moduleTokensQuery = defineQueryOptions(({ workspaceId, module }: { workspaceId: number; module: ModuleName }) => ({
  key: TOKEN_QUERY_KEYS.moduleTokens(workspaceId, module),
  query: () => getModuleTokens(workspaceId, module),
}))

// Query for all workspace modules (workspace members can view their own)
export const workspaceModulesQuery = defineQueryOptions(({ workspaceId }: { workspaceId: number }) => ({
  key: TOKEN_QUERY_KEYS.workspaceModules(workspaceId),
  query: () => getWorkspaceModules(workspaceId),
}))