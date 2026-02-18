import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  type Conversation,
  ConversationState,
  type Message,
  MessageRole,
  type MessageStatus,
} from '~/types/conversation'

export const useConversationStore = defineStore('conversation', () => {
  // State: Current conversation
  const currentConversation = ref<Conversation | null>(null)

  // State: Messages array
  const messages = ref<Message[]>([])

  // State: Set of message IDs for O(1) deduplication
  const messageIds = ref<Set<string>>(new Set())

  // State: AI waiting state
  const isWaitingForAI = ref(false)
  const waitingStartTime = ref<number | null>(null)

  // State: Pagination cursor for older messages
  const nextMessagesUrl = ref<string | null>(null)

  // Action: Set current conversation
  const setCurrentConversation = (conversation: Conversation | null) => {
    currentConversation.value = conversation
    if (conversation?.state === ConversationState.WAITING_FOR_AGENT) {
      isWaitingForAI.value = true
      waitingStartTime.value = Date.now()
    }
  }

  // Action: Set messages and update the tracking set
  const setMessages = (newMessages: Message[]) => {
    messages.value = newMessages
    messageIds.value = new Set(newMessages.map((msg) => msg.id))
  }

  // Action: Add message if new, or update if existing
  const addOrUpdateMessage = (message: Message): 'added' | 'updated' | 'unchanged' => {
    // Check if message already exists by ID
    if (messageIds.value.has(message.id)) {
      const index = messages.value.findIndex((msg) => msg.id === message.id)
      if (index !== -1) {
        const existingMessage = messages.value[index]
        // Only update if there are actual changes
        if (JSON.stringify(existingMessage) !== JSON.stringify(message)) {
          messages.value = messages.value.map((msg, i) => (i === index ? message : msg))
          return 'updated'
        }
      }
      return 'unchanged'
    }

    // Stop waiting for AI when we receive a model response or system error
    if (message.role === MessageRole.MODEL || message.role === MessageRole.SYSTEM_ERROR) {
      setWaitingForAI(false)
    }

    // Reset waiting timer on SYSTEM messages (activity detected, no need for reassurance)
    if (message.role === MessageRole.SYSTEM && isWaitingForAI.value && message.createdAt) {
      waitingStartTime.value = new Date(message.createdAt).getTime()
    }

    // Handle USER message from server replacing a pending temp-* message
    // When the server sends the real message, replace the optimistic one
    if (message.role === MessageRole.USER) {
      const lastIndex = messages.value.length - 1
      const lastMessage = messages.value[lastIndex]
      if (
        lastMessage &&
        lastMessage.role === MessageRole.USER &&
        lastMessage.id.startsWith('temp-')
      ) {
        // Replace the temp message with the real one
        messageIds.value.delete(lastMessage.id)
        messageIds.value.add(message.id)
        messages.value = messages.value.map((msg, i) => (i === lastIndex ? message : msg))
        return 'updated'
      }
    }

    messageIds.value.add(message.id)
    messages.value = [...messages.value, message]

    return 'added'
  }

  // Action: Prepend messages (for loading older messages)
  const prependMessages = (olderMessages: Message[]): number => {
    const newMessages = olderMessages.filter((msg) => !messageIds.value.has(msg.id))
    if (newMessages.length === 0) {
      return 0
    }
    newMessages.forEach((msg) => messageIds.value.add(msg.id))
    messages.value = [...newMessages, ...messages.value]
    return newMessages.length
  }

  // Action: Update message status
  const updateMessageStatus = (messageId: string, status: MessageStatus) => {
    const index = messages.value.findIndex((msg) => msg.id === messageId)
    if (index !== -1) {
      messages.value = messages.value.map((msg, i) => (i === index ? { ...msg, status } : msg))
    }
  }

  // Action: Update message (for retry or other updates)
  // Handles ID changes when a pending message gets its real ID from the server
  const updateMessage = (messageId: string, updates: Partial<Message>): boolean => {
    const index = messages.value.findIndex((msg) => msg.id === messageId)
    if (index === -1) {
      return false
    }

    // Handle ID change: update the messageIds Set
    if (updates.id && updates.id !== messageId) {
      messageIds.value.delete(messageId)
      messageIds.value.add(updates.id)
    }

    messages.value = messages.value.map((msg, i) => (i === index ? { ...msg, ...updates } : msg))
    return true
  }

  // Action: Remove a message by ID
  const removeMessage = (messageId: string): boolean => {
    if (!messageIds.value.has(messageId)) {
      return false
    }
    messageIds.value.delete(messageId)
    messages.value = messages.value.filter((msg) => msg.id !== messageId)
    return true
  }

  // Action: Set waiting for AI state
  const setWaitingForAI = (waiting: boolean) => {
    isWaitingForAI.value = waiting
    if (waiting) {
      waitingStartTime.value = Date.now()
    } else {
      waitingStartTime.value = null
    }
  }

  // Action: Set next messages URL for pagination
  const setNextMessagesUrl = (url: string | null) => {
    nextMessagesUrl.value = url
  }

  // Computed: Check if we have more messages to load
  const hasMoreMessages = computed(() => !!nextMessagesUrl.value)

  // Computed: Get the last message
  const lastMessage = computed(() => {
    if (messages.value.length === 0) {
      return null
    }
    return messages.value[messages.value.length - 1]
  })

  // Computed: Get messages by status
  const pendingMessages = computed(() => messages.value.filter((msg) => msg.status === 'pending'))

  const errorMessages = computed(() => messages.value.filter((msg) => msg.status === 'error'))

  // Action: Reset all state to initial values
  const $reset = () => {
    currentConversation.value = null
    messages.value = []
    messageIds.value = new Set()
    isWaitingForAI.value = false
    waitingStartTime.value = null
    nextMessagesUrl.value = null
  }

  return {
    // State
    currentConversation,
    messages,
    isWaitingForAI,
    waitingStartTime,
    nextMessagesUrl,

    // Actions
    setCurrentConversation,
    setMessages,
    addOrUpdateMessage,
    prependMessages,
    updateMessageStatus,
    updateMessage,
    removeMessage,
    setWaitingForAI,
    setNextMessagesUrl,
    $reset,

    // Computed
    hasMoreMessages,
    lastMessage,
    pendingMessages,
    errorMessages,
  }
})
