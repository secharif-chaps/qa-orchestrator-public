import { defineQueryOptions } from '@pinia/colada'
import { getFolderShares, searchUsersForSharing } from '@/api/folders'
import { FOLDER_SHARE_QUERY_KEYS } from '@/mutations/folderShares'

/**
 * Query for fetching all shares for a folder
 * Only accessible by folder owner
 */
export const folderSharesQuery = defineQueryOptions(({ folderId }: { folderId: string }) => ({
  key: FOLDER_SHARE_QUERY_KEYS.byFolderId(folderId),
  query: () => {
    if (!folderId || folderId === 'null' || folderId === 'undefined') {
      return Promise.resolve([])
    }
    return getFolderShares(folderId)
  },
}))

// Query keys for user search
export const USER_SEARCH_QUERY_KEYS = {
  root: ['user-search'] as const,
  withQuery: (query: string) => [...USER_SEARCH_QUERY_KEYS.root, query] as const,
}

/**
 * Query for searching users to share with
 * Returns users in the organization with their write permission status
 */
export const userSearchQuery = defineQueryOptions(({ query }: { query: string }) => ({
  key: USER_SEARCH_QUERY_KEYS.withQuery(query),
  query: () => {
    // Don't search with empty query
    if (!query || query.trim().length < 2) {
      return Promise.resolve([])
    }
    return searchUsersForSharing(query.trim(), 10)
  },
  // Short stale time for search results
  staleTime: 30000, // 30 seconds
}))
