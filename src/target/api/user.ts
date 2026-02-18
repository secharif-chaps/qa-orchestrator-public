import { useApi } from '@target/composables/useApi'
import type { JsonLdCollection } from '@target/types/jsonld'
import type { User } from '@target/types/user'

export const searchUsers = async (
  query: string,
  excludeWatchFileSharedUsers: string | null = null,
) => {
  const response = await useApi().get<JsonLdCollection<User>>('/users', {
    query: {
      search: query,
      excludeCurrentUser: true,
      excludeWatchFileSharedUsers: excludeWatchFileSharedUsers,
    },
  })

  return response.data
}
