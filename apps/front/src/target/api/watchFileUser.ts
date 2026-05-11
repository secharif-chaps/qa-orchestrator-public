import { apiClient } from '@/api/client'
import type { DefaultErrorMessage } from '@target/types/api'
import type { JsonLdCollection } from '@target/types/jsonld'
import type { User } from '@target/types/user'
import type { WatchFileUser, WatchFileUserRole } from '@target/types/watchFileUser'

export const getWatchFileUsers = async (watchFileId: string) => {
  return apiClient.get<JsonLdCollection<WatchFileUser>>(`/watch_files/${watchFileId}/share`)
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
  return apiClient.post<JsonLdCollection<WatchFileUser>>(
    `/watch_files/${watchFileId}/share`,
    payload,
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const removeWatchFileUser = async (
  watchFileId: string,
  watchFileUserId: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  await apiClient.delete(`/watch_files/${watchFileId}/share/${watchFileUserId}`, {
    errorMessage: defaultErrorMessage,
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
