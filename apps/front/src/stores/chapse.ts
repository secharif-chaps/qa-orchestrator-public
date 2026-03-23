/**
 * Chapse AI Chatbot Store
 *
 * Manages the state for the Chapse chatbot including:
 * - Current conversation and messages
 * - Company context (max 3 companies)
 * - Conversations list
 * - UI state (fullscreen mode)
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { ChapseConversation } from '@/api/chapse'

// =============================================================================
// Types
// =============================================================================

export interface ChatMessage {
  id: string
  content: string
  role: 'user' | 'assistant'
  timestamp: number
  isStreaming?: boolean
  // Smart action metadata - when message is triggered by a quick action
  isSmartAction?: boolean
  smartActionLabel?: string
  smartActionIcon?: string
}

// Marker prefix for smart action messages (stored in content for persistence)
export const SMART_ACTION_MARKER_PREFIX = '<!--SMART_ACTION:'
export const SMART_ACTION_MARKER_SUFFIX = '-->'

/**
 * Parse smart action metadata from message content if present
 * Returns the metadata and the clean content without the marker
 */
export function parseSmartActionMarker(content: string): {
  isSmartAction: boolean
  label?: string
  icon?: string
  cleanContent: string
} {
  if (!content.startsWith(SMART_ACTION_MARKER_PREFIX)) {
    return { isSmartAction: false, cleanContent: content }
  }

  const markerEndIndex = content.indexOf(SMART_ACTION_MARKER_SUFFIX)
  if (markerEndIndex === -1) {
    return { isSmartAction: false, cleanContent: content }
  }

  try {
    const jsonStr = content.slice(SMART_ACTION_MARKER_PREFIX.length, markerEndIndex)
    const metadata = JSON.parse(jsonStr)
    const cleanContent = content.slice(markerEndIndex + SMART_ACTION_MARKER_SUFFIX.length).trim()

    return {
      isSmartAction: true,
      label: metadata.label,
      icon: metadata.icon,
      cleanContent,
    }
  } catch {
    return { isSmartAction: false, cleanContent: content }
  }
}

/**
 * Build a smart action marker to prefix message content
 */
export function buildSmartActionMarker(label: string, icon: string): string {
  return `${SMART_ACTION_MARKER_PREFIX}${JSON.stringify({ label, icon })}${SMART_ACTION_MARKER_SUFFIX}\n`
}

export interface CompanyContext {
  id: number
  name: string
  siren?: string | null
}

// Maximum number of companies in context
const MAX_COMPANY_CONTEXT = 3

// localStorage persistence constants
const STORAGE_KEY_PREFIX = 'chapse_conv_'
const MAX_STORED_CONVERSATIONS = 30
const MAX_MESSAGES_PER_CONVERSATION = 100

// =============================================================================
// Store
// =============================================================================

export const useChapseStore = defineStore('chapse', () => {
  // =========================================================================
  // Current Conversation State
  // =========================================================================

  const currentConversationId = ref<string | null>(null)
  const currentConversationName = ref<string>('')
  const messages = ref<ChatMessage[]>([])
  const isLoading = ref(false)
  const isStreaming = ref(false)
  const error = ref<string | null>(null)

  // =========================================================================
  // Company Context State
  // =========================================================================

  const companyContext = ref<CompanyContext[]>([])

  // =========================================================================
  // Conversations List State
  // =========================================================================

  const conversations = ref<ChapseConversation[]>([])
  const conversationsLoading = ref(false)
  const hasMoreConversations = ref(false)

  // =========================================================================
  // Computed Properties
  // =========================================================================

  const hasMessages = computed(() => messages.value.length > 0)

  const canAddMoreCompanies = computed(() => companyContext.value.length < MAX_COMPANY_CONTEXT)

  const companyContextCount = computed(() => companyContext.value.length)

  const companyIds = computed(() => companyContext.value.map((c) => c.id))

  const isNewConversation = computed(() => currentConversationId.value === null)

  // =========================================================================
  // localStorage Persistence (per-conversation)
  // =========================================================================

  function _saveToStorage(): void {
    if (!currentConversationId.value) return

    try {
      // Filter out streaming messages and cap at max
      const storedMessages = messages.value
        .filter((m) => !m.isStreaming)
        .slice(-MAX_MESSAGES_PER_CONVERSATION)

      const data = {
        messages: storedMessages,
        companyContext: companyContext.value,
        conversationName: currentConversationName.value,
        savedAt: Date.now(),
      }

      localStorage.setItem(
        `${STORAGE_KEY_PREFIX}${currentConversationId.value}`,
        JSON.stringify(data),
      )

      _pruneStorage()
    } catch (err) {
      console.warn('Failed to save conversation to localStorage:', err)
    }
  }

  function _loadFromStorage(conversationId: string): boolean {
    try {
      const raw = localStorage.getItem(`${STORAGE_KEY_PREFIX}${conversationId}`)
      if (!raw) return false

      const data = JSON.parse(raw)
      if (!Array.isArray(data.messages)) return false

      messages.value = data.messages
      if (Array.isArray(data.companyContext)) {
        companyContext.value = data.companyContext
      }
      if (data.conversationName) {
        currentConversationName.value = data.conversationName
      }

      return true
    } catch {
      // Corrupt data — remove it
      localStorage.removeItem(`${STORAGE_KEY_PREFIX}${conversationId}`)
      return false
    }
  }

  function _removeFromStorage(conversationId: string): void {
    localStorage.removeItem(`${STORAGE_KEY_PREFIX}${conversationId}`)
  }

  function _pruneStorage(): void {
    try {
      interface StorageEntry {
        key: string
        savedAt: number
      }
      const entries: StorageEntry[] = []

      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i)
        if (!key?.startsWith(STORAGE_KEY_PREFIX)) continue

        try {
          const data = JSON.parse(localStorage.getItem(key) || '')
          entries.push({ key, savedAt: data.savedAt || 0 })
        } catch {
          // Remove corrupt entries
          localStorage.removeItem(key)
        }
      }

      if (entries.length <= MAX_STORED_CONVERSATIONS) return

      // Sort oldest first, remove excess
      entries.sort((a, b) => a.savedAt - b.savedAt)
      const toRemove = entries.slice(0, entries.length - MAX_STORED_CONVERSATIONS)
      for (const entry of toRemove) {
        localStorage.removeItem(entry.key)
      }
    } catch (err) {
      console.warn('Failed to prune localStorage:', err)
    }
  }

  function _clearAllStorage(): void {
    const keysToRemove: string[] = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key?.startsWith(STORAGE_KEY_PREFIX)) {
        keysToRemove.push(key)
      }
    }
    for (const key of keysToRemove) {
      localStorage.removeItem(key)
    }
  }

  // =========================================================================
  // Message Actions
  // =========================================================================

  interface SmartActionMetadata {
    label: string
    icon: string
  }

  function addUserMessage(content: string, smartAction?: SmartActionMetadata): string {
    const messageId = `user_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
    messages.value.push({
      id: messageId,
      content,
      role: 'user',
      timestamp: Date.now(),
      // Add smart action metadata if provided
      isSmartAction: !!smartAction,
      smartActionLabel: smartAction?.label,
      smartActionIcon: smartAction?.icon,
    })
    _saveToStorage()
    return messageId
  }

  function addAssistantMessage(content: string = '', isStreaming: boolean = false): string {
    const messageId = `assistant_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
    messages.value.push({
      id: messageId,
      content,
      role: 'assistant',
      timestamp: Date.now(),
      isStreaming,
    })
    return messageId
  }

  function appendToLastMessage(chunk: string): void {
    const lastMessage = messages.value[messages.value.length - 1]
    if (lastMessage && lastMessage.role === 'assistant') {
      lastMessage.content += chunk
    }
  }

  function finishStreaming(): void {
    const lastMessage = messages.value[messages.value.length - 1]
    if (lastMessage && lastMessage.role === 'assistant') {
      lastMessage.isStreaming = false
    }
    isStreaming.value = false
    _saveToStorage()
  }

  function clearMessages(): void {
    messages.value = []
  }

  // =========================================================================
  // Conversation Actions
  // =========================================================================

  function setCurrentConversation(conversationId: string | null, name: string = ''): void {
    // Save previous conversation before switching
    _saveToStorage()
    currentConversationId.value = conversationId
    currentConversationName.value = name
  }

  function startNewConversation(): void {
    // Save current conversation before clearing
    _saveToStorage()
    currentConversationId.value = null
    currentConversationName.value = ''
    messages.value = []
    error.value = null
    // Keep company context for new conversation
  }

  function setConversations(data: ChapseConversation[], hasMore: boolean): void {
    conversations.value = data
    hasMoreConversations.value = hasMore
  }

  function appendConversations(data: ChapseConversation[], hasMore: boolean): void {
    conversations.value = [...conversations.value, ...data]
    hasMoreConversations.value = hasMore
  }

  function removeConversation(conversationId: string): void {
    conversations.value = conversations.value.filter((c) => c.id !== conversationId)
    _removeFromStorage(conversationId)

    // If deleted conversation was current, start new
    if (currentConversationId.value === conversationId) {
      startNewConversation()
    }
  }

  function prependConversation(conversation: ChapseConversation): void {
    // Add to the beginning of the list, avoiding duplicates
    const exists = conversations.value.some((c) => c.id === conversation.id)
    if (!exists) {
      conversations.value = [conversation, ...conversations.value]
    }
  }

  function updateConversationName(conversationId: string, name: string): void {
    const conversation = conversations.value.find((c) => c.id === conversationId)
    if (conversation) {
      conversation.name = name
    }

    if (currentConversationId.value === conversationId) {
      currentConversationName.value = name
    }
  }

  // =========================================================================
  // Company Context Actions
  // =========================================================================

  function addCompanyToContext(company: CompanyContext): boolean {
    // Check limit
    if (companyContext.value.length >= MAX_COMPANY_CONTEXT) {
      return false
    }

    // Check if already exists
    if (companyContext.value.some((c) => c.id === company.id)) {
      return false
    }

    companyContext.value.push(company)
    return true
  }

  function removeCompanyFromContext(companyId: number): void {
    companyContext.value = companyContext.value.filter((c) => c.id !== companyId)
  }

  function setCompanyContext(companies: CompanyContext[]): void {
    // Enforce max limit
    companyContext.value = companies.slice(0, MAX_COMPANY_CONTEXT)
  }

  function clearCompanyContext(): void {
    companyContext.value = []
  }

  function isCompanyInContext(companyId: number): boolean {
    return companyContext.value.some((c) => c.id === companyId)
  }

  // =========================================================================
  // Loading State Actions
  // =========================================================================

  function setLoading(loading: boolean): void {
    isLoading.value = loading
  }

  function setStreamingState(streaming: boolean): void {
    isStreaming.value = streaming
  }

  function setError(errorMessage: string | null): void {
    error.value = errorMessage
  }

  function setConversationsLoading(loading: boolean): void {
    conversationsLoading.value = loading
  }

  // =========================================================================
  // Load Conversation Messages
  // =========================================================================

  function loadMessagesFromApi(
    apiMessages: Array<{ id: string; query: string; answer: string; created_at: number }>,
  ): void {
    messages.value = []

    // Convert API messages to chat messages
    // API messages contain both query and answer in single object
    for (const msg of apiMessages) {
      // Parse smart action marker from query if present
      const parsed = parseSmartActionMarker(msg.query)

      // Add user message
      messages.value.push({
        id: `user_${msg.id}`,
        content: msg.query,
        role: 'user',
        timestamp: msg.created_at * 1000,
        // Add smart action metadata if detected
        isSmartAction: parsed.isSmartAction,
        smartActionLabel: parsed.label,
        smartActionIcon: parsed.icon,
      })

      // Add assistant message
      messages.value.push({
        id: `assistant_${msg.id}`,
        content: msg.answer,
        role: 'assistant',
        timestamp: msg.created_at * 1000 + 1, // +1ms to ensure order
      })
    }

    _saveToStorage()
  }

  /**
   * Load a conversation's messages from localStorage.
   * Sets currentConversationId and populates messages if found.
   * Returns true if cached data was found, false otherwise.
   */
  function loadFromStorage(conversationId: string): boolean {
    currentConversationId.value = conversationId
    return _loadFromStorage(conversationId)
  }

  // =========================================================================
  // Reset Store
  // =========================================================================

  function $reset(): void {
    currentConversationId.value = null
    currentConversationName.value = ''
    messages.value = []
    isLoading.value = false
    isStreaming.value = false
    error.value = null
    companyContext.value = []
    conversations.value = []
    conversationsLoading.value = false
    hasMoreConversations.value = false
    _clearAllStorage()
  }

  return {
    // State
    currentConversationId,
    currentConversationName,
    messages,
    isLoading,
    isStreaming,
    error,
    companyContext,
    conversations,
    conversationsLoading,
    hasMoreConversations,

    // Computed
    hasMessages,
    canAddMoreCompanies,
    companyContextCount,
    companyIds,
    isNewConversation,

    // Message Actions
    addUserMessage,
    addAssistantMessage,
    appendToLastMessage,
    finishStreaming,
    clearMessages,

    // Conversation Actions
    setCurrentConversation,
    startNewConversation,
    setConversations,
    appendConversations,
    removeConversation,
    prependConversation,
    updateConversationName,

    // Company Context Actions
    addCompanyToContext,
    removeCompanyFromContext,
    setCompanyContext,
    clearCompanyContext,
    isCompanyInContext,

    // Loading State Actions
    setLoading,
    setStreamingState,
    setError,
    setConversationsLoading,

    // Load Messages
    loadMessagesFromApi,
    loadFromStorage,

    // Reset
    $reset,
  }
})
