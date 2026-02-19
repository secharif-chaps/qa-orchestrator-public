import { useMutation, useQueryCache } from '@pinia/colada'
import {
  batchDocumentValidation,
  documentValidation,
  markDocumentAsSeen,
} from '@target/api/document'
import { DOCUMENT_QUERY_KEYS } from '@target/api/queries/document'
import { useToast } from '@target/composables/useToast'
import { useWatchFileDocumentsStore } from '@target/stores/watchFileDocuments'
import type {
  BatchValidationResponse,
  Document,
  DocumentFacets,
  DocumentValidationResponse,
  ManualValidationStatus,
} from '@target/types/document'
import { DocumentValidationAction } from '@target/types/document'
import type { User } from '@target/types/user'
import { useI18n } from 'vue-i18n'

interface CallbackMutations<T> {
  onSuccess?: (data: T) => void
  onError?: () => void
}

export const useMarkDocumentAsSeen = () => {
  const queryCache = useQueryCache()
  const watchFileDocumentsStore = useWatchFileDocumentsStore()
  const { t } = useI18n()

  const defaultErrorMessage = {
    title: t('common.error.title'),
    description: t('documents.mark_as_seen.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({ documentId }: { documentId: string; watchFileId: string }) =>
      markDocumentAsSeen(documentId, defaultErrorMessage),
    onMutate: ({ documentId, watchFileId }) => {
      const documentsCollection = queryCache.getQueryData<{
        items: Document[]
        totalItems: number
        facets?: DocumentFacets
      }>(DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams))

      const oldCollection = JSON.parse(JSON.stringify(documentsCollection))

      const findDocument = documentsCollection?.items.find((document) => document.id === documentId)
      if (findDocument) {
        findDocument.isSeen = true
      }

      queryCache.setQueryData(
        DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        documentsCollection,
      )
      queryCache.cancelQueries({
        key: DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
      })
      return { documentsCollection, oldCollection }
    },
    onError: (_, { watchFileId }, { oldCollection }) => {
      queryCache.setQueryData(
        DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        oldCollection,
      )
    },
  })

  return { ...mutation, markAsSeen: mutate }
}

export const useDocumentValidation = (options?: CallbackMutations<DocumentValidationResponse>) => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileDocumentsStore = useWatchFileDocumentsStore()

  const defaultErrorMessage = {
    title: t('watch_files.documents.status_change.error'),
  }

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      documentId,
      action,
    }: {
      watchFileId: string
      documentId: string
      action: DocumentValidationAction
    }) => documentValidation(documentId, action, defaultErrorMessage),
    onMutate: ({ watchFileId, documentId, action }) => {
      const documentsCollection = queryCache.getQueryData<{
        items: Document[]
        totalItems: number
        facets?: DocumentFacets
      }>(DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams))

      const oldCollection = JSON.parse(JSON.stringify(documentsCollection ?? null))

      const findDocument = documentsCollection?.items.find((document) => document.id === documentId)
      if (findDocument) {
        findDocument.manualStatus = action === DocumentValidationAction.UNCERTAIN ? null : action
      }

      queryCache.setQueryData(
        DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        documentsCollection,
      )
      queryCache.cancelQueries({
        key: DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
      })
      return { documentsCollection, oldCollection }
    },
    onError: (error, { watchFileId }, { oldCollection, documentsCollection }) => {
      if (
        documentsCollection ===
        queryCache.getQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        )
      ) {
        queryCache.setQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
          oldCollection,
        )
      }
      console.error('Error During DocumentValidation: ', error)
    },
    onSuccess(data, { action, watchFileId, documentId }) {
      const validatedByUser = data.validated_by
        ? {
            '@id': data.validated_by,
            '@type': 'User' as const,
            id: data.validated_by.split('/').pop() || '',
            email: '',
            displayName: data.validated_by,
            defaultThumbnail: '',
          }
        : null

      const newManualStatus = action === DocumentValidationAction.UNCERTAIN ? null : action

      // Update item query cache (for DocumentViewer)
      const itemDocument = queryCache.getQueryData<Document>(DOCUMENT_QUERY_KEYS.byId(documentId))

      if (itemDocument) {
        queryCache.setQueryData(DOCUMENT_QUERY_KEYS.byId(documentId), {
          ...itemDocument,
          validatedAt: data.validated_at || null,
          validatedBy: validatedByUser,
          manualStatus: newManualStatus,
        })
      }

      // Update collection query cache
      const documentsCollection = queryCache.getQueryData<{
        items: Document[]
        totalItems: number
        facets?: DocumentFacets
      }>(DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams))

      if (
        documentsCollection &&
        ((data.validated_at && data.validated_by) || action === DocumentValidationAction.UNCERTAIN)
      ) {
        const updatedItems = documentsCollection.items.map((doc) => {
          if (doc.id === documentId) {
            return {
              ...doc,
              validatedAt: data.validated_at || null,
              validatedBy: validatedByUser,
              manualStatus: newManualStatus,
            }
          }
          return doc
        })

        const updatedCollection = {
          ...documentsCollection,
          items: updatedItems,
        }

        queryCache.setQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
          updatedCollection,
        )
      }

      options?.onSuccess?.(data)
      toast.success(t('watch_files.documents.status_change.success_' + action))
    },
  })
  return {
    ...mutation,
    toggleDocumentStatus: mutate,
  }
}

export const useBatchDocumentValidation = (
  options?: CallbackMutations<BatchValidationResponse>,
) => {
  const toast = useToast()
  const { t } = useI18n()
  const queryCache = useQueryCache()
  const watchFileDocumentsStore = useWatchFileDocumentsStore()

  const { mutate, ...mutation } = useMutation({
    mutation: ({
      documentIds,
      action,
    }: {
      watchFileId: string
      documentIds: string[]
      action: DocumentValidationAction
    }): Promise<BatchValidationResponse> => {
      const defaultErrorMessage = {
        title: t('watch_files.documents.batch_status_change.error_' + action),
      }
      return batchDocumentValidation(documentIds, action, defaultErrorMessage)
    },
    onMutate: ({ watchFileId, documentIds, action }) => {
      const documentsCollection = queryCache.getQueryData<{
        items: Document[]
        totalItems: number
        facets?: DocumentFacets
      }>(DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams))

      if (!documentsCollection) {
        return
      }

      const oldCollection = JSON.parse(JSON.stringify(documentsCollection ?? null))

      const updatedItems = documentsCollection.items.map((document) => {
        if (documentIds.includes(document.id)) {
          return {
            ...document,
            manualStatus: action as ManualValidationStatus,
          }
        }
        return document
      })

      const updatedCollection = {
        ...documentsCollection,
        items: updatedItems,
      }

      queryCache.setQueryData(
        DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        updatedCollection,
      )
      queryCache.cancelQueries({
        key: DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
      })
      return { documentsCollection: updatedCollection, oldCollection }
    },
    onSuccess(data, { watchFileId, action }, { oldCollection }) {
      options?.onSuccess?.(data)

      if (oldCollection && data.validated_at && data.validated_by) {
        const successfulDocIds = new Set(
          data.results
            .filter((result) => result.status === 'success')
            .map((result) => result.document_id),
        )

        const validatedByUser: User = {
          '@id': data.validated_by,
          '@type': 'User' as const,
          id: data.validated_by.split('/').pop() || '',
          email: '',
          displayName: data.validated_by,
          defaultThumbnail: '',
        }

        const updatedItems = oldCollection.items.map((document: Document) => {
          if (successfulDocIds.has(document.id)) {
            return {
              ...document,
              manualStatus: action as ManualValidationStatus,
              validatedBy: validatedByUser,
              validatedAt: data.validated_at,
            }
          }
          return document
        })

        const updatedCollection = {
          ...oldCollection,
          items: updatedItems,
        }

        queryCache.setQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
          updatedCollection,
        )
      }

      if (data.failed_count > 0) {
        toast.warning(
          t(
            'watch_files.documents.batch_status_change.partial_success_' + action,
            {
              success: data.validated_count,
              failed: data.failed_count,
            },
            data.validated_count,
          ),
        )
      } else {
        toast.success(
          t(
            'watch_files.documents.batch_status_change.success_' + action,
            { count: data.validated_count },
            data.validated_count,
          ),
        )
      }
    },
    onError: (error, { watchFileId }, { oldCollection, documentsCollection }) => {
      if (
        documentsCollection ===
        queryCache.getQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
        )
      ) {
        queryCache.setQueryData(
          DOCUMENT_QUERY_KEYS.withFilters(watchFileId, watchFileDocumentsStore.queryParams),
          oldCollection,
        )
      }
      console.error('Error During Batch DocumentValidation: ', error)
    },
  })
  return {
    ...mutation,
    batchToggleDocumentStatus: mutate,
  }
}
