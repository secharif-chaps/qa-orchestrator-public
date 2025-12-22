import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Team permissions composable
 * Checks if current user can manage team members
 *
 * Similar pattern to useFolderPermissions and useCompanyPermissions
 */
export const useTeamPermissions = () => {
  const authStore = useAuthStore()

  /**
   * Can view team members list
   * Requires: organization.read
   */
  const canViewTeam = computed(() => authStore.hasPermission('organization.read'))

  /**
   * Can manage team members (change permissions, reset passwords)
   * Requires: organization.manage OR admin.organizations role
   */
  const canManageTeam = computed(() => {
    const hasOrgManage = authStore.hasPermission('organization.manage')
    const hasAdminOrg = authStore.hasRole('admin.organizations')

    return hasOrgManage || hasAdminOrg
  })

  return {
    canViewTeam,
    canManageTeam,
  }
}
