import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { organizationFeatureFlagsQuery } from '@/queries/feature-flags'
import { currentOrganizationQuery } from '@/queries/organization'

/**
 * Composable to check if the Stream feature flag is enabled for the current organization.
 * Stream is a feature flag (not a core module), checked via organization feature flags.
 */
export function useStreamModule() {
  const { data: organization, isLoading: isOrgLoading } = useQuery(() => currentOrganizationQuery())

  const { data: featureFlagsData, isLoading: isFlagsLoading } = useQuery(() => ({
    ...organizationFeatureFlagsQuery({ organizationId: organization.value?.id ?? '' }),
    enabled: !!organization.value?.id,
  }))

  const isLoading = computed(() => isOrgLoading.value || isFlagsLoading.value)

  // Default to true while loading to avoid layout flash
  const isStreamEnabled = computed(() => {
    if (isLoading.value) return true
    if (!featureFlagsData.value?.feature_flags) return false
    const streamFlag = featureFlagsData.value.feature_flags.find((f) => f.flag === 'stream')
    return streamFlag?.enabled ?? false
  })

  return { isStreamEnabled, isLoading }
}
