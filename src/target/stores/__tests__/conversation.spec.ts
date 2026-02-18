import { useConversationStore } from '@target/stores/conversation'
import {
    ConversationState,
    MessageRole,
    MessageStatus,
    type Conversation,
    type Message,
} from '@target/types/conversation'
import type { WatchFile } from '@target/types/watchFile'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'

const createMockWatchFile = (): WatchFile =>
  ({
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
  }) as WatchFile

const createMessage = (id: string, overrides: Partial<Message> = {}): Message => ({
  id,
  '@type': 'Message',
  contents: [
    {
      '@type': 'TextContent',
      '@id': `/contents/${id}`,
      id,
      content: `Message ${id}`,
    },
  ],
  role: MessageRole.USER,
  status: MessageStatus.SENT,
  retryCount: 0,
  createdAt: new Date().toISOString(),
  ...overrides,
})

const createConversation = (id: string, messages: Message[] = []): Conversation => ({
  id,
  title: 'Test Conversation',
  watchFile: createMockWatchFile(),
  state: ConversationState.IDLE,
  createdAt: new Date().toISOString(),
  updatedAt: new Date().toISOString(),
  language: 'en',
  messages,
})

describe('useConversationStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('has correct initial state', () => {
    const store = useConversationStore()
    expect(store.currentConversation).toBeNull()
    expect(store.messages).toEqual([])
    expect(store.isWaitingForAI).toBe(false)
    expect(store.waitingStartTime).toBeNull()
    expect(store.nextMessagesUrl).toBeNull()
  })

  it('adds new message and returns "added"', () => {
    const store = useConversationStore()
    const message = createMessage('msg-1')
    const result = store.addOrUpdateMessage(message)
    expect(result).toBe('added')
    expect(store.messages).toHaveLength(1)
    expect(store.messages[0]?.id).toBe('msg-1')
  })

  it('updates existing message when changed and returns "updated"', () => {
    const store = useConversationStore()
    const message1 = createMessage('msg-1', { status: MessageStatus.PENDING })
    const message2 = createMessage('msg-1', { status: MessageStatus.SENT })

    store.addOrUpdateMessage(message1)
    const result = store.addOrUpdateMessage(message2)

    expect(result).toBe('updated')
    expect(store.messages).toHaveLength(1)
    expect(store.messages[0]?.status).toBe(MessageStatus.SENT)
  })

  it('returns "unchanged" when message has not changed', () => {
    const store = useConversationStore()
    const message = createMessage('msg-1', { status: MessageStatus.SENT })

    store.addOrUpdateMessage(message)
    const result = store.addOrUpdateMessage(message)

    expect(result).toBe('unchanged')
    expect(store.messages).toHaveLength(1)
  })

  it('transitions message status from pending to sent', () => {
    const store = useConversationStore()
    const message = createMessage('msg-1', { status: MessageStatus.PENDING })
    store.addOrUpdateMessage(message)

    store.updateMessageStatus('msg-1', MessageStatus.SENT)

    expect(store.messages[0]?.status).toBe(MessageStatus.SENT)
  })

  it('sets isWaitingForAI and records start time', () => {
    const store = useConversationStore()

    store.setWaitingForAI(true)

    expect(store.isWaitingForAI).toBe(true)
    expect(store.waitingStartTime).not.toBeNull()
    expect(typeof store.waitingStartTime).toBe('number')
  })

  it('clears waitingStartTime when setting isWaitingForAI to false', () => {
    const store = useConversationStore()
    store.setWaitingForAI(true)

    store.setWaitingForAI(false)

    expect(store.isWaitingForAI).toBe(false)
    expect(store.waitingStartTime).toBeNull()
  })

  it('stops waiting for AI when receiving a model message', () => {
    const store = useConversationStore()
    store.setWaitingForAI(true)

    const modelMessage = createMessage('msg-1', { role: MessageRole.MODEL })
    store.addOrUpdateMessage(modelMessage)

    expect(store.isWaitingForAI).toBe(false)
    expect(store.waitingStartTime).toBeNull()
  })

  it('stops waiting for AI when receiving a system_error message', () => {
    const store = useConversationStore()
    store.setWaitingForAI(true)

    const errorMessage = createMessage('msg-1', {
      role: MessageRole.SYSTEM_ERROR,
    })
    store.addOrUpdateMessage(errorMessage)

    expect(store.isWaitingForAI).toBe(false)
    expect(store.waitingStartTime).toBeNull()
  })

  it('does NOT stop waiting for AI when receiving a user message', () => {
    const store = useConversationStore()
    store.setWaitingForAI(true)

    const userMessage = createMessage('msg-1', { role: MessageRole.USER })
    store.addOrUpdateMessage(userMessage)

    expect(store.isWaitingForAI).toBe(true)
    expect(store.waitingStartTime).not.toBeNull()
  })

  it('clears all state with reset', () => {
    const store = useConversationStore()
    const conversation = createConversation('conv-1')
    const message = createMessage('msg-1')

    store.setCurrentConversation(conversation)
    store.addOrUpdateMessage(message)
    store.setWaitingForAI(true)
    store.setNextMessagesUrl('https://api.example.com/messages?page=2')

    store.$reset()

    expect(store.currentConversation).toBeNull()
    expect(store.messages).toEqual([])
    expect(store.isWaitingForAI).toBe(false)
    expect(store.waitingStartTime).toBeNull()
    expect(store.nextMessagesUrl).toBeNull()
  })
})
