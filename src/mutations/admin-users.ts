/**
 * Admin user management mutations
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { assignUserWorkspace } from '@/api/admin-users'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { WORKSPACE_QUERY_KEYS } from '@/queries/workspace'
import { toast } from '@/utils/toast'
import { useAuthStore } from '@/stores/auth'

/**
 * Mutation to assign a user to a workspace or change their workspace
 * This replaces the old usePickWorkspace mutation
 */
export const useAssignUserWorkspace = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, workspaceId }: { userId: string; workspaceId: number }) =>
      assignUserWorkspace(userId, workspaceId),
    onSuccess: (_, { userId }) => {
      toast.success('Workspace assigned successfully!')

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate workspace queries to refresh workspace data
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.current })
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.currentWithMembers })
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminAll })

      // If admin changed their own workspace, page needs to reload
      // to update the workspace context throughout the application
      const authStore = useAuthStore()
      if (userId === authStore.user?.sub) {
        setTimeout(() => {
          window.location.reload()
        }, 1000)
      }
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to assign workspace'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    assignWorkspace: mutate,
  }
})
