/**
 * Admin user management mutations
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { assignUserOrganization, updateUserPermissions, resetUserPassword, disableUser, enableUser } from '@/api/admin-users'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { ORGANIZATION_QUERY_KEYS } from '@/queries/organization-admin'
import { toast } from '@/utils/toast'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import type { ResetPasswordResponse } from '@/api/admin-users'

/**
 * Mutation to assign a user to an organization or change their organization
 */
export const useAssignUserOrganization = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, organizationId }: { userId: string; organizationId: string }) =>
      assignUserOrganization(userId, organizationId),
    onSuccess: (_, { userId }) => {
      toast.success(t('admin.users.assignOrganization.success'))

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
      const errorMessage = error?.message || t('admin.users.assignOrganization.error')
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
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, permissions }: { userId: string; permissions: string[] }) =>
      updateUserPermissions(userId, permissions),
    onSuccess: (_, { userId }) => {
      toast.success(t('admin.users.updatePermissions.success'))

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
      const errorMessage = error?.message || t('admin.users.updatePermissions.error')
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
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ userId, temporaryPassword }: { userId: string; temporaryPassword: string }) =>
      resetUserPassword(userId, temporaryPassword),
    onSuccess: () => {
      toast.success(t('admin.users.resetPassword.success'))
    },
    onError: (error: any) => {
      const errorMessage = error?.message || t('admin.users.resetPassword.error')
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
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId }: { userId: string }) => disableUser(userId),
    onSuccess: () => {
      toast.success(t('admin.users.disable.success'))

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate organization queries to refresh member counts
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.root })
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.admin })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || t('admin.users.disable.error')
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
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId }: { userId: string }) => enableUser(userId),
    onSuccess: () => {
      toast.success(t('admin.users.enable.success'))

      // Invalidate admin user queries to refresh the list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // Invalidate organization queries to refresh member counts
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.root })
      queryCache.invalidateQueries({ key: ORGANIZATION_QUERY_KEYS.admin })
    },
    onError: (error: any) => {
      const errorMessage = error?.message || t('admin.users.enable.error')
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    enableUser: mutate,
  }
})
