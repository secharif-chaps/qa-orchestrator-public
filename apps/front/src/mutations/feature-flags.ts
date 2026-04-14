/**
 * Feature flags mutations for Pinia Colada.
 */

import { computed, ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { toggleFeatureFlag } from '@/api/feature-flags'
import { FEATURE_FLAGS_QUERY_KEYS } from '@/queries/feature-flags'
import type { FeatureFlagName } from '@/types/feature-flags'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'

/**
 * Mutation to toggle a feature flag for an organization.
 * Optionally accepts config data (e.g., URL for discover flag).
 */
export const useToggleFeatureFlag = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()
  const organizationId = ref<string>('')
  const flag = ref<FeatureFlagName>('translation')
  const enabled = ref<boolean>(false)
  const config = ref<Record<string, unknown> | null>(null)

  const { mutate, status, ...mutation } = useMutation({
    mutation: () =>
      toggleFeatureFlag(organizationId.value, flag.value, {
        enabled: enabled.value,
        config: config.value,
      }),
    onSuccess: () => {
      const action = enabled.value
        ? t('settings.featureFlags.toggle.enabled')
        : t('settings.featureFlags.toggle.disabled')
      toast.success(t('settings.featureFlags.toggle.success', { flag: flag.value, action }))

      queryCache.invalidateQueries({
        key: FEATURE_FLAGS_QUERY_KEYS.byOrganization(organizationId.value),
      })
    },
    onError: (error: unknown) => {
      const errorMessage =
        error instanceof Error ? error.message : t('settings.featureFlags.toggle.error')
      toast.error(errorMessage)
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
    status,
    isPending: computed(() => status.value === 'pending'),
    toggleFeatureFlag: toggle,
  }
})
