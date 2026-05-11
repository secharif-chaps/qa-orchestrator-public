import { useQueryCache } from '@pinia/colada'
import { apiClient } from '@/api/client'
import { ACTOR_QUERY_KEYS } from '@target/api/queries/actor'
import { SOURCES_QUERY_KEYS } from '@target/api/queries/sources'
import type { DefaultErrorMessage } from '@target/types/api'
import type {
  Conversation,
  FunctionCallContent,
  Message,
  MessageRole,
  MessageStatus,
  TextContent,
} from '@target/types/conversation'
import type { JsonLdCollection } from '@target/types/jsonld'
import type {
  GroupedWatchFileActivityDto,
  WatchFile,
  WatchFileFilters,
} from '@target/types/watchFile'
import { WATCH_FILE_QUERY_KEYS } from './queries/watchFile'

const ROOT_URL = '/watch_files'

export const WATCHFILES_SUBSCRIBE_KEYS = {
  root: ROOT_URL,
  byId: (id: string) => `${WATCHFILES_SUBSCRIBE_KEYS.root}/${id}`,
  lastConversation: (id: string) => `${WATCHFILES_SUBSCRIBE_KEYS.root}/${id}/conversation/last`,
  conversationMessages: (conversationId: string) =>
    `${WATCHFILES_SUBSCRIBE_KEYS.root}/conversation/${conversationId}/messages`,
}

export const getItemWatchFile = async (id: string) => {
  const queryCache = useQueryCache()

  return apiClient.get<WatchFile, WatchFile>(`${ROOT_URL}/${id}`, {
    realtime: {
      key: WATCHFILES_SUBSCRIBE_KEYS.byId(id),
      onMessage: (data: WatchFile) => {
        if (data && typeof data === 'object' && 'id' in data) {
          queryCache.setQueryData(WATCH_FILE_QUERY_KEYS.byId(id), data)
          queryCache.invalidateQueries({
            key: ACTOR_QUERY_KEYS.byWatchFile(id),
          })
          queryCache.invalidateQueries({
            key: SOURCES_QUERY_KEYS.byWatchFile(id),
          })
        }
      },
    },
  })
}

export const getCollectionWatchFile = async ({
  sortBy,
  sortOrder,
  page,
  itemsPerPage,
  name,
  onlyFavorites,
  includeArchived,
}: WatchFileFilters) => {
  const response = await apiClient.get<JsonLdCollection<WatchFile>>(ROOT_URL, {
    query: {
      [`sort[${sortBy}]`]: sortOrder.toLowerCase(),
      page,
      itemsPerPage,
      name,
      onlyFavorites,
      includeArchived,
    },
  })

  return {
    items: response.member,
    totalItems: response.totalItems,
  }
}

export const createWatchFile = async (content: string) => {
  return apiClient.post<WatchFile>(
    ROOT_URL,
    {
      content,
    },
    { mediaType: 'ld+json' },
  )
}

export const updateWatchFile = async (
  id: string,
  data: Partial<WatchFile>,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.patch<WatchFile>(`${ROOT_URL}/${id}`, data, {
    mediaType: 'merge-patch+json',
    errorMessage: defaultErrorMessage,
  })
}

export const deleteWatchFile = async (id: string) => {
  await apiClient.delete(`${ROOT_URL}/${id}`)
}

export const changeWatchFileStatus = async (
  id: string,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  return apiClient.post<WatchFile>(
    `${ROOT_URL}/${id}/status/${status}`,
    {},
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const toggleWatchFileFavorite = async (
  id: string,
  isFavorite: boolean,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  if (isFavorite) {
    await apiClient.post(
      `${ROOT_URL}/${id}/favorite`,
      {},
      { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
    )
  } else {
    await apiClient.delete(`${ROOT_URL}/${id}/favorite`, {
      errorMessage: defaultErrorMessage,
    })
  }
}

export const removeWatchFileActor = async (watchFileId: string, actorId: number) => {
  await apiClient.delete(`${ROOT_URL}/${watchFileId}/actors/${actorId}`)
}

export const getWatchFileTimeline = async (
  watchFileId: string,
  params?: { page?: number; limit?: number },
) => {
  return apiClient.get<GroupedWatchFileActivityDto>(`${ROOT_URL}/${watchFileId}/history`, {
    query: {
      itemsPerPage: 10,
      ...params,
    },
  })
}

export const getLastConversation = async (
  watchFileId: string,
  onUpdate?: (conversation: Conversation) => void,
) => {
  const options: Parameters<typeof apiClient.get<Conversation>>[1] = {}

  if (onUpdate) {
    options.realtime = {
      key: WATCHFILES_SUBSCRIBE_KEYS.lastConversation(watchFileId),
      onMessage: (data: unknown) => {
        if (
          data &&
          typeof data === 'object' &&
          'id' in data &&
          '@type' in data &&
          (data as Record<string, unknown>)['@type'] === 'Conversation'
        ) {
          onUpdate(data as unknown as Conversation)
        }
      },
    }
  }

  return apiClient.get<Conversation>(`${ROOT_URL}/${watchFileId}/conversations/last`, options)
}

export const getConversationMessages = async (
  conversationId: string,
  params?: { order?: Record<string, string> },
  onUpdate?: (message: Message) => void,
) => {
  const options: Parameters<typeof apiClient.get<JsonLdCollection<Message>>>[1] = {
    query: {
      ...params,
    },
  }

  if (onUpdate) {
    options.realtime = {
      key: WATCHFILES_SUBSCRIBE_KEYS.conversationMessages(conversationId),
      onMessage: (data: unknown) => {
        if (
          data &&
          typeof data === 'object' &&
          '@type' in data &&
          (data as Record<string, unknown>)['@type'] === 'Message' &&
          'contents' in data &&
          Array.isArray((data as Record<string, unknown>).contents)
        ) {
          const messageData = data as Record<string, unknown>
          const newMessage: Message = {
            id: messageData.id as string,
            '@type': 'Message',
            contents: messageData.contents as (TextContent | FunctionCallContent)[],
            role: messageData.role as MessageRole,
            status: (messageData.status as MessageStatus) || 'sent',
            retryCount: (messageData.retryCount as number) || 0,
            metadata: messageData.metadata as Record<string, unknown>,
            createdAt: messageData.createdAt as string,
            loading: messageData.loading as boolean,
            createdBy: messageData.createdBy as {
              defaultThumbnail?: string
            } | null,
          }

          onUpdate(newMessage)
        }
      },
    }
  }

  const response = await apiClient.get<JsonLdCollection<Message>>(
    `/conversations/${conversationId}/messages`,
    options,
  )

  return {
    items: response.member.reverse(),
    totalItems: response.totalItems,
    nextUrl: response.view?.next,
  }
}

export const getOlderConversationMessages = async (nextUrl: string) => {
  const response = await apiClient.get<JsonLdCollection<Message>>(nextUrl)
  return {
    items: response.member.reverse(),
    totalItems: response.totalItems,
    nextUrl: response.view?.next,
  }
}

export const addMessage = async (conversationId: string, message: string) => {
  return apiClient.post<Conversation>(
    `/conversations/${conversationId}/messages`,
    {
      content: message,
    },
    { mediaType: 'ld+json' },
  )
}

export const retryMessage = async (messageId: string, defaultErrorMessage: DefaultErrorMessage) => {
  return apiClient.post<Message>(
    `/messages/${messageId}/retry`,
    {},
    { mediaType: 'ld+json', errorMessage: defaultErrorMessage },
  )
}

export const cancelConversation = async (conversationId: string) => {
  return apiClient.post<Conversation>(
    `/conversations/${conversationId}/cancel`,
    {},
    { mediaType: 'ld+json' },
  )
}
