import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation, useQueryCache } from '@pinia/colada'
import {
  createOrganizationUser,
  updateOrganizationUser,
  deleteOrganizationUser,
  toggleUserStatus,
  resendPasswordReset,
} from '@/api/user'
import { USER_QUERY_KEYS } from '@/queries/user'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { toast } from '@/utils/toast'
import type { OrganizationUserCreate, OrganizationUserUpdate } from '@/types/user'

export const useCreateOrganizationUser = (organizationId: string) => {
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (user: OrganizationUserCreate) => createOrganizationUser(organizationId, user),
    onSuccess: () => {
      // Invalidate organization users query to refresh the list
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      // Also invalidate admin users queries to refresh admin pages
      queryCache.invalidateQueries({
        key: ADMIN_USER_QUERY_KEYS.root,
      })
      // Invalidate organization members query used on the admin members page
      queryCache.invalidateQueries({
        key: ['organizations', organizationId, 'members'],
      })
      toast.success(t('admin.users.create.success'))
    },
    onError: () => {
      toast.error(t('admin.users.create.error'))
    },
  })

  const createUser = async (user: OrganizationUserCreate) => {
    isLoading.value = true
    try {
      const result = await mutateAsync(user)
      return result
    } finally {
      isLoading.value = false
    }
  }

  return {
    createUser,
    isLoading,
  }
}

export const useUpdateOrganizationUser = (organizationId: string) => {
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, user }: { userId: string; user: OrganizationUserUpdate }) =>
      updateOrganizationUser(organizationId, userId, user),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      // Also invalidate admin users queries to refresh admin pages
      queryCache.invalidateQueries({
        key: ADMIN_USER_QUERY_KEYS.root,
      })
      toast.success(t('admin.users.update.success'))
    },
    onError: () => {
      toast.error(t('admin.users.update.error'))
    },
  })

  const updateUser = async (userId: string, user: OrganizationUserUpdate) => {
    isLoading.value = true
    try {
      const result = await mutateAsync({ userId, user })
      return result
    } finally {
      isLoading.value = false
    }
  }

  return {
    updateUser,
    isLoading,
  }
}

export const useDeleteOrganizationUser = (organizationId: string) => {
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => deleteOrganizationUser(organizationId, userId),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      // Also invalidate admin users queries to refresh admin pages
      queryCache.invalidateQueries({
        key: ADMIN_USER_QUERY_KEYS.root,
      })
      toast.success(t('admin.users.delete.success'))
    },
    onError: () => {
      toast.error(t('admin.users.delete.error'))
    },
  })

  const deleteUser = async (userId: string) => {
    isLoading.value = true
    try {
      await mutateAsync(userId)
    } finally {
      isLoading.value = false
    }
  }

  return {
    deleteUser,
    isLoading,
  }
}

export const useToggleUserStatus = (organizationId: string) => {
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, enabled }: { userId: string; enabled: boolean }) =>
      toggleUserStatus(organizationId, userId, enabled),
    onSuccess: (_: unknown, { enabled }) => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      // Also invalidate admin users queries to refresh admin pages
      queryCache.invalidateQueries({
        key: ADMIN_USER_QUERY_KEYS.root,
      })
      toast.success(t(enabled ? 'admin.users.enable.success' : 'admin.users.disable.success'))
    },
    onError: () => {
      toast.error(t('admin.users.statusUpdate.error'))
    },
  })

  const toggleStatus = async (userId: string, enabled: boolean) => {
    isLoading.value = true
    try {
      const result = await mutateAsync({ userId, enabled })
      return result
    } finally {
      isLoading.value = false
    }
  }

  return {
    toggleStatus,
    isLoading,
  }
}

export const useResendPasswordReset = (organizationId: string) => {
  const { t } = useI18n()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => resendPasswordReset(organizationId, userId),
    onSuccess: () => {
      toast.success(t('admin.users.resetPassword.sent'))
    },
    onError: () => {
      toast.error(t('admin.users.resetPassword.sentError'))
    },
  })

  const resendReset = async (userId: string) => {
    isLoading.value = true
    try {
      await mutateAsync(userId)
    } finally {
      isLoading.value = false
    }
  }

  return {
    resendReset,
    isLoading,
  }
}
