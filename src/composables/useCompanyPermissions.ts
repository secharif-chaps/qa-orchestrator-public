import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Company permissions composable
 * Provides granular permission checks for company-related actions
 *
 * Permission model:
 * - organization.read: Can read folders and companies shared with user
 * - organization.write: Can create folders, edit/share/delete owned folders
 * - company.create: Can add items (company screens) to folders
 *
 * Note: Company actions within folders also depend on folder share role.
 * Use useFolderPermissions for folder-context-aware permission checks.
 */
export function useCompanyPermissions() {
  const authStore = useAuthStore()

  /**
   * Can view companies
   * Requires organization.read
   */
  const canViewCompany = computed(() => authStore.hasPermission('organization.read'))

  /**
   * Can create companies (add items to folders)
   * Requires company.create permission
   * Note: In folder context, also requires owner or writer share role
   */
  const canCreateCompany = computed(() => authStore.hasPermission('company.create'))

  /**
   * Can delete companies
   * Requires organization.write (folder owners can delete items)
   * Note: In folder context, only folder owner can delete items
   */
  const canDeleteCompany = computed(() => authStore.hasPermission('organization.write'))

  /**
   * Can manage companies (create or delete)
   */
  const canManageCompanies = computed(() => canCreateCompany.value || canDeleteCompany.value)

  /**
   * Has any company access (at minimum view)
   */
  const hasAnyCompanyAccess = computed(
    () => canCreateCompany.value || canDeleteCompany.value || canViewCompany.value,
  )

  /**
   * Has write access to organization (can create folders)
   */
  const hasOrganizationWrite = computed(() => authStore.hasPermission('organization.write'))

  return {
    // Individual permissions
    canCreateCompany,
    canDeleteCompany,
    canViewCompany,

    // Compound permissions
    canManageCompanies,
    hasAnyCompanyAccess,

    // Organization-level permissions
    hasOrganizationWrite,
  }
}

// Export both named function and named export for backward compatibility
export const useCompanyPermissionsComposable = useCompanyPermissions
