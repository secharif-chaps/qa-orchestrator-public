import { ref } from 'vue'
import { useMutation, useQueryCache } from '@pinia/colada'
import {
  createOrganizationUser,
  updateOrganizationUser,
  deleteOrganizationUser,
  toggleUserStatus,
  resendPasswordReset,
} from '@/api/user'
import { USER_QUERY_KEYS } from '@/queries/user'
import { toast } from '@/utils/toast'
import type { OrganizationUserCreate, OrganizationUserUpdate } from '@/types/user'

export const useCreateOrganizationUser = (organizationId: string) => {
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (user: OrganizationUserCreate) => createOrganizationUser(organizationId, user),
    onSuccess: () => {
      // Invalidate organization users query to refresh the list
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      toast.success('User Created')
    },
    onError: (error: any) => {
      console.error('Failed to add user:', error)
      toast.error(
        'Failed to Add User',
        error.response?.data?.message || error.message || 'An unexpected error occurred',
      )
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
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, user }: { userId: string; user: OrganizationUserUpdate }) =>
      updateOrganizationUser(organizationId, userId, user),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      toast.success('User Updated')
    },
    onError: (error: any) => {
      console.error('Failed to update user:', error)
      toast.error(
        'Failed to Update User',
        error.response?.data?.message || error.message || 'An unexpected error occurred',
      )
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
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => deleteOrganizationUser(organizationId, userId),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      toast.success('User Deleted')
    },
    onError: (error: any) => {
      console.error('Failed to delete user:', error)
      toast.error(
        'Failed to Delete User',
        error.response?.data?.message || error.message || 'An unexpected error occurred',
      )
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
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, enabled }: { userId: string; enabled: boolean }) =>
      toggleUserStatus(organizationId, userId, enabled),
    onSuccess: (_, { enabled }) => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.organization(organizationId),
      })
      toast.success('User Status Updated')
    },
    onError: (error: any) => {
      console.error('Failed to toggle user status:', error)
      toast.error(
        'Failed to Update Status',
        error.response?.data?.message || error.message || 'An unexpected error occurred',
      )
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
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => resendPasswordReset(organizationId, userId),
    onSuccess: () => {
      toast.success('Password Reset Sent')
    },
    onError: (error: any) => {
      console.error('Failed to send password reset:', error)
      toast.error(
        'Failed to Send Reset',
        error.response?.data?.message || error.message || 'An unexpected error occurred',
      )
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
