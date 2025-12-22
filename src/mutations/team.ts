import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { updateMemberPermissions, resetMemberPassword } from '@/api/team'
import { TEAM_QUERY_KEYS } from '@/queries/team'
import type { UpdateTeamMemberPermissions } from '@/types/team'

/**
 * Mutation to update team member permissions
 */
export const useUpdateMemberPermissions = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ userId, data }: { userId: string; data: UpdateTeamMemberPermissions }) =>
      updateMemberPermissions(userId, data),
    onSuccess: () => {
      // Invalidate all team queries to refresh the list
      queryCache.invalidateQueries({ key: TEAM_QUERY_KEYS.root })
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
  const { mutate, ...mutation } = useMutation({
    mutation: (userId: string) => resetMemberPassword(userId),
  })

  return {
    ...mutation,
    resetPassword: mutate,
  }
})
