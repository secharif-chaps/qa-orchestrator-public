import { useApi } from '~/composables/useApi'
import type { DefaultErrorMessage } from '~/types/api'
import type { JsonLdCollection } from '~/types/jsonld'
import type { User } from '~/types/user'
import type { WatchFileUser, WatchFileUserRole } from '~/types/watchFileUser'

export const getWatchFileUsers = async (watchFileId: string) => {
  const response = await useApi().get<JsonLdCollection<WatchFileUser>>(
    `/watch_files/${watchFileId}/share`,
  )
  return response.data
}

export const addWatchFileUsers = async (
  watchFileId: string,
  users: User[],
  role: WatchFileUserRole,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const payload = {
    member: users.map((user) => ({
      userId: user.id,
      role,
    })),
  }
  const response = await useApi().post<JsonLdCollection<WatchFileUser>>(
    `/watch_files/${watchFileId}/share`,
    payload,
    { defaultErrorMessage },
  )
  return response.data
}

export const removeWatchFileUser = async (
  watchFileId: string,
  watchFileUserId: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  await useApi().delete(`/watch_files/${watchFileId}/share/${watchFileUserId}`, {
    defaultErrorMessage,
  })
}

export const updateWatchFileUserRole = async (
  watchFileId: string,
  user: User,
  role: WatchFileUserRole,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return await addWatchFileUsers(watchFileId, [user], role, defaultErrorMessage)
}
