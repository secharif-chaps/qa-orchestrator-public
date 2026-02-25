import { defineQueryOptions } from '@pinia/colada'
import { searchUsers } from '@target/api/user'

export const USER_QUERY_KEYS = {
  root: ['users'] as const,
  search: (query: string, excludeWatchFileSharedUsers: string | null) =>
    [...USER_QUERY_KEYS.root, 'search', query, excludeWatchFileSharedUsers] as const,
}

export const searchUsersQuery = defineQueryOptions(
  ({
    query,
    excludeWatchFileSharedUsers,
  }: {
    query: string
    excludeWatchFileSharedUsers?: string | null
  }) => ({
    key: USER_QUERY_KEYS.search(query, excludeWatchFileSharedUsers || null),
    query: () => searchUsers(query, excludeWatchFileSharedUsers || null),
    enabled: !!query && query.length > 0,
  }),
)
