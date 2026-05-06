import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { WATCH_FILE_USER_QUERY_KEYS } from '@target/api/queries/watchFileUser'
import {
  addWatchFileUsers,
  removeWatchFileUser,
  updateWatchFileUserRole,
} from '@target/api/watchFileUser'
import { useRole } from '@target/composables/useRole'
import { useToast } from '@target/composables/useToast'
import type { User } from '@target/types/user'
import type { WatchFileUser, WatchFileUserRole } from '@target/types/watchFileUser'
import { useI18n } from 'vue-i18n'

interface CallbackMutations<T> {
  onSuccess?: (data: T) => void
  onError?: (error: Error) => void
}

export const useAddWatchFileUsers = (options?: CallbackMutations<WatchFileUser[]>) => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('target.watchFiles.toast.error.addUsers'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      users,
      role,
    }: {
      watchFileId: string
      users: User[]
      role: WatchFileUserRole
    }) => addWatchFileUsers(watchFileId, users, role, defaultErrorMessage),

    onError(error) {
      options?.onError?.(error)
      console.error('Failed to add watchfile users:', error)
    },
    onSuccess(data, { watchFileId }) {
      options?.onSuccess?.(data.member)

      toast.success(
        t('target.watchFiles.toast.success.addUsers', {
          count: data.member.length,
        }),
      )

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
  })

  return { ...mutation, addUsers: mutate }
}

export const useRemoveWatchFileUser = defineMutation(() => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('target.watchFiles.toast.error.removeUser'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      watchFileUserId,
    }: {
      watchFileId: string
      watchFileUserId: string
      displayName: string
    }) => removeWatchFileUser(watchFileId, watchFileUserId, defaultErrorMessage),

    onSuccess(_, { watchFileId, displayName }) {
      toast.success(
        t('target.watchFiles.toast.success.removeUser', {
          user: displayName,
        }),
      )

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
    onError(error) {
      console.error('Failed to remove watchfile user:', error)
    },
  })

  return { ...mutation, removeUser: mutate }
})

export const useUpdateWatchFileUserRole = (options?: CallbackMutations<WatchFileUser[]>) => {
  const queryCache = useQueryCache()
  const toast = useToast()
  const { t } = useI18n()
  const { roleLabel } = useRole()

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      watchFileId,
      user,
      role,
    }: {
      watchFileId: string
      user: User
      role: WatchFileUserRole
    }) => {
      const defaultErrorMessage = {
        title: t('target.watchFiles.toast.error.changeRole', {
          user: user.displayName,
          role: roleLabel(role),
        }),
      }
      return updateWatchFileUserRole(watchFileId, user, role, defaultErrorMessage)
    },

    onError(error) {
      options?.onError?.(error)
      console.error('Failed to update watchfile user role:', error)
    },
    onSuccess(data, { watchFileId, user, role }) {
      options?.onSuccess?.(data.member)

      toast.success(
        t('target.watchFiles.toast.success.changeRole', {
          user: user.displayName,
          role: roleLabel(role),
        }),
      )

      queryCache.invalidateQueries({
        key: WATCH_FILE_USER_QUERY_KEYS.byWatchFile(watchFileId),
      })
    },
  })

  return { ...mutation, updateRole: mutate }
}
