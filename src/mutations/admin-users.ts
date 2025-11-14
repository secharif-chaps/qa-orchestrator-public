/**
 * Admin user management mutations
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { assignUserOrganization } from '@/api/admin-users'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { ORGANIZATION_QUERY_KEYS } from '@/queries/organization-admin'
import { toast } from '@/utils/toast'
import { useAuthStore } from '@/stores/auth'

/**
 * Mutation to assign a user to an organization or change their organization
 */
export const useAssignUserOrganization = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, organizationId }: { userId: string; organizationId: string }) =>
      assignUserOrganization(userId, organizationId),
    onSuccess: (_, { userId }) => {
      toast.success('Organization assigned successfully!')

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate organization queries to refresh organization data
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.root })
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.admin })

      // If admin changed their own organization, page needs to reload
      // to update the organization context throughout the application
      const authStore = useAuthStore()
      if (userId === authStore.user?.sub) {
        setTimeout(() => {
          window.location.reload()
        }, 1000)
      }
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to assign organization'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    assignOrganization: mutate,
  }
})
