import { computed } from 'vue'
import { useQuery } from '@pinia/colada'
import { organizationModulesQuery } from '@/queries/tokens'
import { currentOrganizationQuery } from '@/queries/organization'

/**
 * Composable to check if the Screen module is enabled for the current organization.
 * Use this to conditionally show/hide Screen-related UI (create buttons, etc.).
 */
export function useScreenModule() {
  const { data: organization, isLoading: isOrgLoading } = useQuery(() => currentOrganizationQuery())

  const { data: modulesData, isLoading: isModulesLoading } = useQuery(() => ({
    ...organizationModulesQuery({ organizationId: organization.value?.id ?? '' }),
    enabled: !!organization.value?.id,
  }))

  const isLoading = computed(() => isOrgLoading.value || isModulesLoading.value)

  // Default to true while loading to avoid flash/layout shift (buttons hidden then shown)
  // Once loaded, if organization is unavailable, hide Screen buttons (unknown module state)
  const isScreenEnabled = computed(() => {
    if (isLoading.value) return true
    if (!organization.value?.id) return false
    if (!modulesData.value?.modules) return false
    const screenModule = modulesData.value.modules.find((m) => m.name === 'screen')
    return screenModule?.enabled ?? false
  })

  return {
    isScreenEnabled,
    isLoading,
  }
}
