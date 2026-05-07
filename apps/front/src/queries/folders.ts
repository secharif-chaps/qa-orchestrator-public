import { defineQueryOptions } from '@pinia/colada'
import { getFolders, getFolderById, getFoldersWithItems } from '@/api/folders'
import type { FolderSortField, FolderSortOrder } from '@/stores/folders'

export interface FolderListFilters {
  page: number
  size: number
  name: string
  archived?: boolean
  favorites?: boolean
  include_all?: boolean
  sort_by?: FolderSortField
  sort_order?: FolderSortOrder
}

export const FOLDER_QUERY_KEYS = {
  root: ['folders'] as const,
  byId: (id: string, filters?: { archived?: boolean }) => {
    return filters
      ? ([...FOLDER_QUERY_KEYS.root, id, { filters }] as const)
      : ([...FOLDER_QUERY_KEYS.root, id] as const)
  },
  withFilters: (filters: FolderListFilters) => [...FOLDER_QUERY_KEYS.root, { filters }] as const,
  withItems: (filters: FolderListFilters) =>
    [...FOLDER_QUERY_KEYS.root, 'with-items', { filters }] as const,
  favorites: () => [...FOLDER_QUERY_KEYS.root, 'favorites'] as const,
}

export const folderByIdQuery = defineQueryOptions(
  ({ id, filters }: { id: string; filters?: { archived?: boolean } }) => ({
    key: FOLDER_QUERY_KEYS.byId(id || 'invalid', filters),
    enabled: !!id && id !== 'null' && id !== 'undefined',
    query: () => getFolderById(id, filters),
    staleTime: 1000 * 60 * 2, // 2 minutes
  }),
)

export const foldersQuery = defineQueryOptions(({ filters }: { filters: FolderListFilters }) => ({
  key: FOLDER_QUERY_KEYS.withFilters(filters),
  query: () => getFolders(filters),
  staleTime: 1000 * 60 * 2, // 2 minutes
}))

export const foldersWithItemsQuery = defineQueryOptions(
  ({ filters }: { filters: FolderListFilters }) => ({
    key: FOLDER_QUERY_KEYS.withItems(filters),
    query: () => getFoldersWithItems(filters),
    staleTime: 1000 * 60 * 2, // 2 minutes
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
      favorites: true,
    })

    return response
  },
  staleTime: 1000 * 60 * 2, // 2 minutes
}))
