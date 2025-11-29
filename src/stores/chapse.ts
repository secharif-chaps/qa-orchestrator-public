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
import type { CompanySummary, ChapseConversation } from '@/api/chapse'

// =============================================================================
// Types
// =============================================================================

export interface ChatMessage {
  id: string
  content: string
  role: 'user' | 'assistant'
  timestamp: number
  isStreaming?: boolean
}

export interface CompanyContext {
  id: number
  name: string
  siren?: string | null
}

// Maximum number of companies in context
const MAX_COMPANY_CONTEXT = 3

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
  // Message Actions
  // =========================================================================

  function addUserMessage(content: string): string {
    const messageId = `user_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
    messages.value.push({
      id: messageId,
      content,
      role: 'user',
      timestamp: Date.now(),
    })
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
  }

  function clearMessages(): void {
    messages.value = []
  }

  // =========================================================================
  // Conversation Actions
  // =========================================================================

  function setCurrentConversation(conversationId: string | null, name: string = ''): void {
    currentConversationId.value = conversationId
    currentConversationName.value = name
  }

  function startNewConversation(): void {
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

    // If deleted conversation was current, start new
    if (currentConversationId.value === conversationId) {
      startNewConversation()
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
      // Add user message
      messages.value.push({
        id: `user_${msg.id}`,
        content: msg.query,
        role: 'user',
        timestamp: msg.created_at * 1000,
      })

      // Add assistant message
      messages.value.push({
        id: `assistant_${msg.id}`,
        content: msg.answer,
        role: 'assistant',
        timestamp: msg.created_at * 1000 + 1, // +1ms to ensure order
      })
    }
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

    // Reset
    $reset,
  }
})
