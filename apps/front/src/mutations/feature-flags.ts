/**
 * Feature flags mutations for Pinia Colada.
 */

import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { toggleFeatureFlag } from '@/api/feature-flags'
import { FEATURE_FLAGS_QUERY_KEYS } from '@/queries/feature-flags'
import type { FeatureFlagName } from '@/types/feature-flags'

/**
 * Mutation to toggle a feature flag for an organization.
 * Optionally accepts config data (e.g., URL for discover flag).
 */
export const useToggleFeatureFlag = defineMutation(() => {
  const queryCache = useQueryCache()
  const organizationId = ref<string>('')
  const flag = ref<FeatureFlagName>('translation')
  const enabled = ref<boolean>(false)
  const config = ref<Record<string, unknown> | null>(null)

  const { mutate, ...mutation } = useMutation({
    mutation: () =>
      toggleFeatureFlag(organizationId.value, flag.value, {
        enabled: enabled.value,
        config: config.value,
      }),
    onSettled: () => {
      // Invalidate the feature flags query to refetch
      queryCache.invalidateQueries({
        key: FEATURE_FLAGS_QUERY_KEYS.byOrganization(organizationId.value),
      })
    },
  })

  const toggle = async (params: {
    organizationId: string
    flag: FeatureFlagName
    enabled: boolean
    config?: Record<string, unknown> | null
  }) => {
    organizationId.value = params.organizationId
    flag.value = params.flag
    enabled.value = params.enabled
    config.value = params.config ?? null
    return mutate()
  }

  return {
    ...mutation,
    toggleFeatureFlag: toggle,
  }
})
