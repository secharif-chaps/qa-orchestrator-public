import { computed } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateMemberPermissions, resetMemberPassword } from '@/api/team'
import { TEAM_QUERY_KEYS } from '@/queries/team'
import { toast } from '@/utils/toast'
import type { UpdateTeamMemberPermissions } from '@/types/team'

/**
 * Mutation to update team member permissions.
 * Invalidates queries on success instead of optimistic updates,
 * since the permission tier lives in a separate lazy-loaded query.
 */
export const useUpdateMemberPermissions = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, data }: { userId: string; data: UpdateTeamMemberPermissions }) =>
      updateMemberPermissions(userId, data),
    onError: () => {
      toast.error('Failed to update permissions')
    },
    onSuccess: (_updatedMember, { userId }) => {
      // Invalidate the member's permission query so dropdown refreshes
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.memberPermissions(userId) })
      toast.success('Permissions updated successfully')
    },
  })

  return {
    ...mutation,
    updatePermissions: mutate,
  }
})

/**
 * Mutation to reset team member password
 */
export const useResetMemberPassword = defineMutation(() => {
  const { mutateAsync, status, ...mutation } = useMutation({
    mutation: ({ userId, temporaryPassword }: { userId: string; temporaryPassword: string }) =>
      resetMemberPassword(userId, { temporary_password: temporaryPassword }),
    onSuccess: () => {
      toast.success('Password reset successfully')
    },
    onError: () => {
      toast.error('Failed to reset password')
    },
  })

  return {
    ...mutation,
    status,
    isPending: computed(() => status.value === 'pending'),
    resetPasswordAsync: mutateAsync,
  }
})
