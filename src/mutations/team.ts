import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateMemberPermissions, resetMemberPassword } from '@/api/team'
import { TEAM_QUERY_KEYS } from '@/queries/team'
import { toast } from '@/utils/toast'
import type { UpdateTeamMemberPermissions, TeamMember } from '@/types/team'

/**
 * Mutation to update team member permissions with optimistic UI updates
 */
export const useUpdateMemberPermissions = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, data }: { userId: string; data: UpdateTeamMemberPermissions }) =>
      updateMemberPermissions(userId, data),
    onMutate: async ({ userId, data }) => {
      // Cancel any outgoing refetches to avoid overwriting optimistic update
      await queryCache.cancelQueries({ key: TEAM_QUERY_KEYS.root })

      // Snapshot previous values for rollback
      const previousData = new Map<string, TeamMember[]>()

      // Helper function to update team members in cache
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updateMembersInCache = (queryKey: any) => {
        const cachedData = queryCache.getQueryData<TeamMember[]>(queryKey)
        if (cachedData) {
          // Store snapshot for rollback
          previousData.set(JSON.stringify(queryKey), cachedData)

          // Optimistically update the cache
          const updatedData = cachedData.map((member: TeamMember) =>
            member.id === userId ? { ...member, permission_tier: data.permission_tier } : member,
          )

          queryCache.setQueryData(queryKey, updatedData)
        }
      }

      // Update members query without search
      updateMembersInCache(TEAM_QUERY_KEYS.members(undefined))

      // Update members query with empty search
      updateMembersInCache(TEAM_QUERY_KEYS.members(''))

      return { previousData }
    },
    onError: (_error, _variables, context) => {
      // Rollback to previous state on error
      if (context?.previousData) {
        context.previousData.forEach((data, key) => {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          queryCache.setQueryData(JSON.parse(key) as any, data)
        })
      }
      toast.error('Failed to update permissions')
    },
    onSuccess: (updatedMember, { userId }) => {
      // Helper function to update with server response
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const updateWithServerData = (queryKey: any) => {
        const cachedData = queryCache.getQueryData<TeamMember[]>(queryKey)
        if (cachedData) {
          const updatedData = cachedData.map((member: TeamMember) =>
            member.id === userId ? updatedMember : member,
          )
          queryCache.setQueryData(queryKey, updatedData)
        }
      }

      // Update with server response for consistency
      updateWithServerData(TEAM_QUERY_KEYS.members(undefined))
      updateWithServerData(TEAM_QUERY_KEYS.members(''))

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
  const { mutateAsync, ...mutation } = useMutation({
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
    resetPasswordAsync: mutateAsync,
  }
})
