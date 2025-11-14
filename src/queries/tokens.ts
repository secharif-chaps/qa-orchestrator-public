import { defineQueryOptions } from '@pinia/colada'
import { getModuleTokens, getOrganizationModules } from '@/api/tokens'
import type { ModuleName } from '@/types/tokens'

// Export query keys for cache management
export const TOKEN_QUERY_KEYS = {
  root: ['tokens'] as const,
  moduleTokens: (organizationId: string, module: ModuleName) =>
    [...TOKEN_QUERY_KEYS.root, 'module', organizationId, module] as const,
  organizationModules: (organizationId: string) =>
    [...TOKEN_QUERY_KEYS.root, 'organization', organizationId] as const,
}

// Query for single module token count
export const moduleTokensQuery = defineQueryOptions(({ organizationId, module }: { organizationId: string; module: ModuleName }) => ({
  key: TOKEN_QUERY_KEYS.moduleTokens(organizationId, module),
  query: () => getModuleTokens(organizationId, module),
}))

// Query for all organization modules (organization members can view their own)
export const organizationModulesQuery = defineQueryOptions(({ organizationId }: { organizationId: string }) => ({
  key: TOKEN_QUERY_KEYS.organizationModules(organizationId),
  query: () => getOrganizationModules(organizationId),
}))