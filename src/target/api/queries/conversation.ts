import { defineQueryOptions } from '@pinia/colada';
import { getConversationMessages, getLastConversation } from '~/api/watchFile';
import type { Conversation, Message } from '~/types/conversation';

export const CONVERSATION_QUERY_KEYS = {
  root: ['conversations'] as const,
  byId: (id: string) => [...CONVERSATION_QUERY_KEYS.root, id] as const,
  lastConversation: (watchFileId: string) =>
    [
      ...CONVERSATION_QUERY_KEYS.root,
      'watchFile',
      watchFileId,
      'last',
    ] as const,
  messages: (conversationId: string) =>
    [...CONVERSATION_QUERY_KEYS.root, conversationId, 'messages'] as const,
};

export const getLastConversationQuery = defineQueryOptions(
  ({
    watchFileId,
    onUpdate,
  }: {
    watchFileId: string;
    onUpdate?: (conversation: Conversation) => void;
  }) => ({
    key: CONVERSATION_QUERY_KEYS.lastConversation(watchFileId),
    query: () => getLastConversation(watchFileId, onUpdate),
    enabled: !!watchFileId,
  }),
);

export const getConversationMessagesQuery = defineQueryOptions(
  ({
    conversationId,
    onUpdate,
  }: {
    conversationId: string;
    onUpdate?: (message: Message) => void;
  }) => ({
    key: CONVERSATION_QUERY_KEYS.messages(conversationId),
    query: () => getConversationMessages(conversationId, undefined, onUpdate),
    enabled: !!conversationId,
  }),
);
