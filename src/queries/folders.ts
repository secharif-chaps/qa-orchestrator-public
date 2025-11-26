import { defineQueryOptions } from '@pinia/colada'
import { getFolders, getFolderById, getFoldersWithItems } from '@/api/folders'

export const FOLDER_QUERY_KEYS = {
  root: ['folders'] as const,
   byId: (id: string, filters?: { archived?: boolean }) => {
    return filters
      ? [...FOLDER_QUERY_KEYS.root, id, { filters }] as const
      : [...FOLDER_QUERY_KEYS.root, id] as const
  },
  withFilters: (filters: { page: number; size: number; name: string }) =>
    [...FOLDER_QUERY_KEYS.root, { filters }] as const,
  withItems: (filters: { page: number; size: number; name: string }) =>
    [...FOLDER_QUERY_KEYS.root, 'with-items', { filters }] as const,
  favorites: () => [...FOLDER_QUERY_KEYS.root, 'favorites'] as const,
}

export const folderByIdQuery = defineQueryOptions(({ id, filters }: { id: string; filters?: { archived?: boolean } }) => ({
  key: FOLDER_QUERY_KEYS.byId(id || 'invalid', filters),
  query: () => {
    // Ensure we don't make API calls with invalid IDs
    if (!id || id === 'null' || id === 'undefined' || id.trim() === '') {
      // Return a resolved promise with null to avoid errors during invalidation
      return Promise.resolve(null)
    }
    return getFolderById(id, filters)
  },
}))

export const foldersQuery = defineQueryOptions(
  ({ filters }: { filters: { page: number; size: number; name: string; archived?: boolean; favorites?: boolean } }) => ({
    key: FOLDER_QUERY_KEYS.withFilters(filters),
    query: () => getFolders(filters),
  }),
)

export const foldersWithItemsQuery = defineQueryOptions(
  ({ filters }: { filters: { page: number; size: number; name: string; archived?: boolean; favorites?: boolean } }) => ({
    key: FOLDER_QUERY_KEYS.withItems(filters),
    query: () => getFoldersWithItems(filters),
  }),
)

export const favoriteFoldersQuery = defineQueryOptions(() => ({
  key: FOLDER_QUERY_KEYS.favorites(),
  query: async () => {
    // Get favorite folders with items directly from the API
    const response = await getFoldersWithItems({
      page: 1,
      size: 4, // Only need first 4 favorites for home page
      name: '',
      favorites: true, // Use the favorites parameter to get only favorite folders
    })

    return response
  },
}))
