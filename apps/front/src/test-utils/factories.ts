import {
  type Conversation,
  ConversationState,
  type Message,
  MessageRole,
  MessageStatus,
} from '@target/types/conversation'
import type { WatchFile } from '@target/types/watchFile'

export function createMessage(id: string, overrides: Partial<Message> = {}): Message {
  return {
    id,
    '@type': 'Message',
    contents: [
      {
        '@type': 'TextContent',
        '@id': `/contents/${id}`,
        id,
        content: `Message content ${id}`,
      },
    ],
    role: MessageRole.USER,
    status: MessageStatus.SENT,
    retryCount: 0,
    createdAt: new Date().toISOString(),
    ...overrides,
  }
}

export function createMessages(count: number, overrides: Partial<Message> = {}): Message[] {
  return Array.from({ length: count }, (_, i) => createMessage(`msg-${i + 1}`, overrides))
}

export function createMockWatchFile(overrides: Partial<WatchFile> = {}): WatchFile {
  return {
    id: 'wf-1',
    '@id': '/watch_files/wf-1',
    '@type': 'WatchFile',
    name: 'Test WatchFile',
    titleManuallySetByUser: false,
    status: 'draft',
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    watchFileUsersCount: 1,
    isFavorite: false,
    userEditable: true,
    ...overrides,
  } as WatchFile
}

export function createConversation(
  id: string,
  messages: Message[] = [],
  overrides: Partial<Conversation> = {},
): Conversation {
  return {
    id,
    title: 'Test Conversation',
    watchFile: createMockWatchFile(),
    state: ConversationState.IDLE,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    language: 'en',
    messages,
    ...overrides,
  }
}
