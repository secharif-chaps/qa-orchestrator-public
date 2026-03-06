import { useQueryCache } from '@pinia/colada'
import { ACTOR_QUERY_KEYS } from '@target/api/queries/actor'
import { SOURCES_QUERY_KEYS } from '@target/api/queries/sources'
import { useApi } from '@target/composables/useApi'
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

  const response = await useApi().get<WatchFile>(`${ROOT_URL}/${id}`, {
    subscribeKey: WATCHFILES_SUBSCRIBE_KEYS.byId(id),
    onUpdate: (data: WatchFile) => {
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
  })
  return response.data
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
  const response = await useApi().get<JsonLdCollection<WatchFile>>(ROOT_URL, {
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
    items: response.data.member,
    totalItems: response.data.totalItems,
  }
}

export const createWatchFile = async (content: string) => {
  const response = await useApi().post<WatchFile>(ROOT_URL, {
    content,
  })
  return response.data
}

export const updateWatchFile = async (
  id: string,
  data: Partial<WatchFile>,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().patch<WatchFile>(`${ROOT_URL}/${id}`, data, {
    defaultErrorMessage,
  })
  return response.data
}

export const deleteWatchFile = async (id: string) => {
  await useApi().delete(`${ROOT_URL}/${id}`)
}

export const changeWatchFileStatus = async (
  id: string,
  status: string,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  const response = await useApi().post<WatchFile>(
    `${ROOT_URL}/${id}/status/${status}`,
    {},
    { defaultErrorMessage },
  )
  return response.data
}

export const toggleWatchFileFavorite = async (
  id: string,
  isFavorite: boolean,
  defaultErrorMessage: DefaultErrorMessage,
) => {
  if (isFavorite) {
    await useApi().post(`${ROOT_URL}/${id}/favorite`, {}, { defaultErrorMessage })
  } else {
    await useApi().delete(`${ROOT_URL}/${id}/favorite`, {
      defaultErrorMessage,
    })
  }
}

export const removeWatchFileActor = async (watchFileId: string, actorId: number) => {
  await useApi().delete(`${ROOT_URL}/${watchFileId}/actors/${actorId}`)
}

export const getWatchFileTimeline = async (
  watchFileId: string,
  params?: { page?: number; limit?: number },
) => {
  const response = await useApi().get<GroupedWatchFileActivityDto>(
    `${ROOT_URL}/${watchFileId}/history`,
    {
      query: {
        itemsPerPage: 10,
        ...params,
      },
    },
  )
  return response.data
}

export const getLastConversation = async (
  watchFileId: string,
  onUpdate?: (conversation: Conversation) => void,
) => {
  let options = {}

  if (onUpdate) {
    options = {
      subscribeKey: WATCHFILES_SUBSCRIBE_KEYS.lastConversation(watchFileId),
      onUpdate: (data: unknown) => {
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
  const response = await useApi().get<Conversation>(
    `${ROOT_URL}/${watchFileId}/conversations/last`,
    options,
  )
  return response.data
}

export const getConversationMessages = async (
  conversationId: string,
  params?: { order?: Record<string, string> },
  onUpdate?: (message: Message) => void,
) => {
  const options: {
    query: Record<string, unknown>
    subscribeKey?: string
    onUpdate?: (data: unknown) => void
  } = {
    query: {
      ...params,
    },
  }

  if (onUpdate) {
    options.subscribeKey = WATCHFILES_SUBSCRIBE_KEYS.conversationMessages(conversationId)
    options.onUpdate = (data: unknown) => {
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
    }
  }

  const response = await useApi().get<JsonLdCollection<Message>>(
    `/conversations/${conversationId}/messages`,
    options,
  )

  return {
    items: response.data.member.reverse(),
    totalItems: response.data.totalItems,
    nextUrl: response.data.view?.next,
  }
}

export const getOlderConversationMessages = async (nextUrl: string) => {
  const response = await useApi().get<JsonLdCollection<Message>>(nextUrl)
  return {
    items: response.data.member.reverse(),
    totalItems: response.data.totalItems,
    nextUrl: response.data.view?.next,
  }
}

export const addMessage = async (conversationId: string, message: string) => {
  const response = await useApi().post<Conversation>(`/conversations/${conversationId}/messages`, {
    content: message,
  })
  return response.data
}

export const retryMessage = async (messageId: string, defaultErrorMessage: DefaultErrorMessage) => {
  const response = await useApi().post<Message>(
    `/messages/${messageId}/retry`,
    {},
    { defaultErrorMessage },
  )
  return response.data
}

export const cancelConversation = async (conversationId: string) => {
  const response = await useApi().post<Conversation>(`/conversations/${conversationId}/cancel`, {})
  return response.data
}
