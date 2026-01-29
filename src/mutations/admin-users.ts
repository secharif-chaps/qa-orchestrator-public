/**
 * Admin user management mutations
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { assignUserOrganization, updateUserPermissions, resetUserPassword, disableUser, enableUser } from '@/api/admin-users'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { ORGANIZATION_QUERY_KEYS } from '@/queries/organization-admin'
import { toast } from '@/utils/toast'
import { useAuthStore } from '@/stores/auth'
import type { ResetPasswordResponse } from '@/api/admin-users'

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
      if (userId === authStore.user?.profile?.sub) {
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

/**
 * Mutation to update user permissions (roles)
 */
export const useUpdateUserPermissions = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, permissions }: { userId: string; permissions: string[] }) =>
      updateUserPermissions(userId, permissions),
    onSuccess: (_, { userId }) => {
      toast.success('Permissions updated successfully!')

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // If admin changed their own permissions, page needs to reload
      // to update permissions throughout the application
      const authStore = useAuthStore()
      if (userId === authStore.user?.profile?.sub) {
        setTimeout(() => {
          window.location.reload()
        }, 1000)
      }
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to update permissions'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    updatePermissions: mutate,
  }
})

/**
 * Mutation to reset user password by setting a new temporary password
 */
export const useResetUserPassword = defineMutation(() => {
  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ userId, temporaryPassword }: { userId: string; temporaryPassword: string }) =>
      resetUserPassword(userId, temporaryPassword),
    onSuccess: () => {
      toast.success('Password reset successfully!')
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to reset password'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    resetPassword: mutate,
    resetPasswordAsync: mutateAsync,
  }
})

/**
 * Mutation to disable a user account (soft delete)
 */
export const useDisableUser = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId }: { userId: string }) => disableUser(userId),
    onSuccess: () => {
      toast.success('User disabled successfully!')

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate organization queries to refresh member counts
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.root })
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.admin })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to disable user'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    disableUser: mutate,
  }
})

/**
 * Mutation to enable a previously disabled user account
 */
export const useEnableUser = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId }: { userId: string }) => enableUser(userId),
    onSuccess: () => {
      toast.success('User enabled successfully!')

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate organization queries to refresh member counts
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.root })
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.admin })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || 'Failed to enable user'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    enableUser: mutate,
  }
})
