import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateOrganizationModules, addModuleTokens, toggleModule } from '@/api/tokens'
import type { ModuleName, TokenUpdateRequest, AddTokensRequest } from '@/types/tokens'
import { TOKEN_QUERY_KEYS } from '@/queries/tokens'
import { toast } from '@/utils/toast'

// Update organization modules configuration
export const useUpdateOrganizationModules = defineMutation(() => {
  const organizationId = ref<string | null>(null)
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ organizationId, updates }: { organizationId: string; updates: Record<ModuleName, TokenUpdateRequest> }) =>
      updateOrganizationModules(organizationId, updates),
    onSuccess: (response, { organizationId }) => {
      toast.success('Module configuration updated successfully!')

      // Invalidate all token queries for this organization
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.organizationModules(organizationId) })
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.root })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to update module configuration'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    organizationId,
    updateModules: mutate,
  }
})

// Add tokens to a specific module
export const useAddModuleTokens = defineMutation(() => {
  const organizationId = ref<string | null>(null)
  const module = ref<ModuleName | null>(null)
  const tokensToAdd = ref<number>(0)
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ organizationId, module, data }: { organizationId: string; module: ModuleName; data: AddTokensRequest }) =>
      addModuleTokens(organizationId, module, data),
    onSuccess: (response, { organizationId, module }) => {
      toast.success(`Added ${response.token_count} tokens to ${module} module`)

      // Invalidate token queries
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.moduleTokens(organizationId, module) })
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.organizationModules(organizationId) })

      // Reset form
      tokensToAdd.value = 0
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to add tokens'
      toast.error(errorMessage)
    },
  })

  const addTokens = () => {
    if (!organizationId.value || !module.value || tokensToAdd.value <= 0) {
      throw new Error('Organization ID, module, and token amount are required')
    }

    return mutate({
      organizationId: organizationId.value,
      module: module.value,
      data: { tokens: tokensToAdd.value },
    })
  }

  return {
    ...mutation,
    organizationId,
    module,
    tokensToAdd,
    addTokens,
    mutate,
  }
})

// Toggle module enabled status
export const useToggleModule = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ organizationId, module, enabled }: { organizationId: string; module: ModuleName; enabled: boolean }) =>
      toggleModule(organizationId, module, enabled),
    onSuccess: (response, { organizationId, module, enabled }) => {
      const action = enabled ? 'enabled' : 'disabled'
      toast.success(`${module} module ${action} successfully`)

      // Invalidate token queries
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.moduleTokens(organizationId, module) })
      queryCache.invalidateQueries({ key: TOKEN_QUERY_KEYS.organizationModules(organizationId) })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to toggle module'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    toggleModule: mutate,
  }
})