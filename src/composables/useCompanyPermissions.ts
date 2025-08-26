import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Company permissions composable
 * Provides granular permission checks for company-related actions
 */
export const useCompanyPermissions = () => {
  const authStore = useAuthStore()

  // Core company permissions
  const canCreateCompany = computed(() => authStore.hasPermission('company.create'))
  const canDeleteCompany = computed(() => authStore.hasPermission('company.delete'))
  const canViewCompany = computed(() => authStore.hasPermission('company.view'))

  // Compound permissions for convenience
  const canManageCompanies = computed(() => canDeleteCompany.value)

  const hasAnyCompanyAccess = computed(
    () =>
      canCreateCompany.value ||
      canDeleteCompany.value ||
      canViewCompany.value,
  )

  return {
    // Individual permissions
    canCreateCompany,
    canDeleteCompany,
    canViewCompany,

    // Compound permissions
    canManageCompanies,
    hasAnyCompanyAccess,
  }
}
