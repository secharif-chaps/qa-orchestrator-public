import { ref } from 'vue'
import { useMutation, useQueryCache } from '@pinia/colada'
import {
  createWorkspaceUser,
  updateWorkspaceUser,
  deleteWorkspaceUser,
  toggleUserStatus,
  resendPasswordReset,
} from '@/api/user'
import { USER_QUERY_KEYS } from '@/queries/user'
import { toast } from '@/utils/toast'
import type { WorkspaceUserCreate, WorkspaceUserUpdate } from '@/types/user'

export const useCreateWorkspaceUser = (workspaceId: number) => {
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (user: WorkspaceUserCreate) => createWorkspaceUser(workspaceId, user),
    onSuccess: () => {
      // Invalidate workspace users query to refresh the list
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.workspace(workspaceId),
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

  const createUser = async (user: WorkspaceUserCreate) => {
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

export const useUpdateWorkspaceUser = (workspaceId: number) => {
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, user }: { userId: string; user: WorkspaceUserUpdate }) =>
      updateWorkspaceUser(workspaceId, userId, user),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.workspace(workspaceId),
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

  const updateUser = async (userId: string, user: WorkspaceUserUpdate) => {
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

export const useDeleteWorkspaceUser = (workspaceId: number) => {
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => deleteWorkspaceUser(workspaceId, userId),
    onSuccess: () => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.workspace(workspaceId),
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

export const useToggleUserStatus = (workspaceId: number) => {
  const queryCache = useQueryCache()
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: ({ userId, enabled }: { userId: string; enabled: boolean }) =>
      toggleUserStatus(workspaceId, userId, enabled),
    onSuccess: (_, { enabled }) => {
      queryCache.invalidateQueries({
        key: USER_QUERY_KEYS.workspace(workspaceId),
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

export const useResendPasswordReset = (workspaceId: number) => {
  const isLoading = ref(false)

  const { mutateAsync } = useMutation({
    mutation: (userId: string) => resendPasswordReset(workspaceId, userId),
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
