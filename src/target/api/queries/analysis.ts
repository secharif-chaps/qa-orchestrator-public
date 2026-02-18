import { defineQueryOptions } from '@pinia/colada'
import { getAnalysisFacets, getCollectionAnalysis } from '../analysis'

export const ANALYSIS_QUERY_KEYS = {
  root: ['analysis'] as const,
  byId: (id: string) => [...ANALYSIS_QUERY_KEYS.root, id] as const,
  withFilters: (watchFileId: string, filters: Record<string, string | number | string[]>) =>
    [...ANALYSIS_QUERY_KEYS.root, 'watchFile', watchFileId, { filters }] as const,
  facets: (watchFileId: string, filters: Record<string, string | number | string[]>) =>
    [...ANALYSIS_QUERY_KEYS.root, 'watchFile', watchFileId, { filters }] as const,
}

export const getCollectionAnalysisQuery = defineQueryOptions(
  (filters: {
    watchFileId: string
    sortBy: string
    sortOrder: string
    page?: number
    itemsPerPage?: number
    actors?: string[]
    sources?: string[]
  }) => ({
    key: ANALYSIS_QUERY_KEYS.withFilters(filters.watchFileId, filters),
    query: () => getCollectionAnalysis(filters),
    enabled: !!filters.sortBy,
  }),
)
export const getCollectionAnalysisFacetsQuery = defineQueryOptions(
  (filters: { watchFileId: string; search?: string; actors?: string[]; sources?: string[] }) => ({
    key: ANALYSIS_QUERY_KEYS.facets(filters.watchFileId, filters),
    query: () => getAnalysisFacets(filters),
    enabled: !!filters.watchFileId,
  }),
)
