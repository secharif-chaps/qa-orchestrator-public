import { defineQueryOptions } from '@pinia/colada'
import { getCollectionDocument, getItemDocument } from '@target/api/document'
import type { CollectionParams, DocumentQueryOptions } from '@target/types/document'

export const DOCUMENT_QUERY_KEYS = {
  root: ['documents'] as const,
  byId: (id: string) => [...DOCUMENT_QUERY_KEYS.root, id] as const,
  withFilters: (watchFileId: string, filters: CollectionParams) =>
    [...DOCUMENT_QUERY_KEYS.root, 'watchFile', watchFileId, JSON.stringify(filters)] as const,
}

export const getItemDocumentQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: DOCUMENT_QUERY_KEYS.byId(id),
  query: () => getItemDocument(id),
  enabled: !!id,
}))

export const getCollectionDocumentQuery = defineQueryOptions(
  ({ watchFileId, filters }: DocumentQueryOptions) => ({
    key: DOCUMENT_QUERY_KEYS.withFilters(watchFileId, filters),
    query: () => getCollectionDocument({ watchFileId, filters }),
    enabled: !!filters.sortBy,
  }),
)
