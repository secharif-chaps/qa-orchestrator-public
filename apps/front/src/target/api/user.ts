import { apiClient } from '@/api/client'
import type { JsonLdCollection } from '@target/types/jsonld'
import type { User } from '@target/types/user'

export const searchUsers = async (
  query: string,
  excludeWatchFileSharedUsers: string | null = null,
) => {
  return apiClient.get<JsonLdCollection<User>>('/users', {
    query: {
      search: query,
      excludeCurrentUser: true,
      excludeWatchFileSharedUsers: excludeWatchFileSharedUsers,
    },
  })
}
