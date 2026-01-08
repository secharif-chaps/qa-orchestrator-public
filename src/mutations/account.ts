/**
 * Pinia Colada mutations for user account management
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { revokeSession, revokeAllSessions } from '@/api/account'
import { ACCOUNT_QUERY_KEYS } from '@/queries/account'

/**
 * Mutation for revoking a specific session
 */
export const useRevokeSession = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (sessionId: string) => revokeSession(sessionId),
    onSuccess: () => {
      // Invalidate sessions query to refetch
      queryCache.invalidateQueries({ key: ACCOUNT_QUERY_KEYS.sessions() })
    },
  })

  return {
    ...mutation,
    revokeSession: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Mutation for revoking all sessions (sign out all devices)
 */
export const useRevokeAllSessions = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (keepCurrent: boolean = true) => revokeAllSessions(keepCurrent),
    onSuccess: (_data, keepCurrent) => {
      // Invalidate sessions query to refetch
      queryCache.invalidateQueries({ key: ACCOUNT_QUERY_KEYS.sessions() })

      // If current session was also revoked, user will need to re-login
      // The API will return 401 on subsequent requests and redirect will happen
      if (!keepCurrent) {
        // Optionally could redirect to login page here
        // window.location.href = '/login'
      }
    },
  })

  return {
    ...mutation,
    revokeAllSessions: mutateAsync,
    mutate,
    mutateAsync,
  }
})
