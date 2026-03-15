import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateDataSourceConfig } from '@/api/data-sources'
import { DATA_SOURCE_KEYS } from '@/queries/data-sources'
import { toast } from '@/utils/toast'

export const useUpdateDataSourceConfig = defineMutation(() => {
  const organizationId = ref<string>('')
  const source = ref<string>('')
  const apiKey = ref<string>('')
  const apiSecret = ref<string>('')
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      organizationId,
      source,
      apiKey,
      apiSecret,
    }: {
      organizationId: string
      source: string
      apiKey: string
      apiSecret?: string
    }) => updateDataSourceConfig(organizationId, source, apiKey, apiSecret),
    onSuccess: (_response, { organizationId, source }) => {
      toast.success('API key updated successfully')
      queryCache.invalidateQueries({
        key: DATA_SOURCE_KEYS.config(organizationId, source),
      })
    },
    onError: (error: unknown) => {
      const errorMessage = error instanceof Error ? error.message : 'Failed to update API key'
      toast.error(errorMessage)
    },
  })

  function updateConfig() {
    if (!organizationId.value) {
      throw new Error('Organization ID is required')
    }
    if (!source.value) {
      throw new Error('Source is required')
    }

    return mutate({
      organizationId: organizationId.value,
      source: source.value,
      apiKey: apiKey.value,
      apiSecret: apiSecret.value || undefined,
    })
  }

  return {
    ...mutation,
    organizationId,
    source,
    apiKey,
    apiSecret,
    updateConfig,
    mutate,
  }
})
