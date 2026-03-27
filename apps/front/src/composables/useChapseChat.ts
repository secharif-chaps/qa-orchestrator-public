/**
 * Chapse AI Chat Composable
 *
 * Provides reactive chat functionality for the Chapse chatbot including:
 * - Sending messages with SSE streaming
 * - Loading and managing conversations
 * - Company context management
 */
import { computed, type ComputedRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQueryCache } from '@pinia/colada'
import { useChapseStore, parseSmartActionMarker, type CompanyContext } from '@/stores/chapse'
import { useAuthStore } from '@/stores/auth'
import { useEndpointResolver } from '@/composables/useEndpointResolver'
import {
  sendChatMessage,
  getConversations,
  getConversation,
  deleteConversation as apiDeleteConversation,
  renameConversation as apiRenameConversation,
  updateConversationContext,
  type ChatHistoryMessage,
} from '@/api/chapse'
import { CHAPSE_QUERY_KEYS } from '@/queries/chapse'

// =============================================================================
// Composable
// =============================================================================

export function useChapseChat() {
  const { t } = useI18n()
  const store = useChapseStore()
  const authStore = useAuthStore()
  const { endpoints } = useEndpointResolver()
  const queryCache = useQueryCache()

  // =========================================================================
  // Computed Properties (from store)
  // =========================================================================

  const messages = computed(() => store.messages)
  const isLoading = computed(() => store.isLoading)
  const isStreaming = computed(() => store.isStreaming)
  const error = computed(() => store.error)
  const hasMessages = computed(() => store.hasMessages)
  const currentConversationId = computed(() => store.currentConversationId)
  const currentConversationName = computed(() => store.currentConversationName)
  const conversations = computed(() => store.conversations)
  const conversationsLoading = computed(() => store.conversationsLoading)
  const hasMoreConversations = computed(() => store.hasMoreConversations)

  // Company context computed
  const companyContext = computed(() => store.companyContext)
  const canAddMoreCompanies: ComputedRef<boolean> = computed(() => store.canAddMoreCompanies)
  const companyContextCount = computed(() => store.companyContextCount)

  // =========================================================================
  // Send Message
  // =========================================================================

  interface SmartActionOptions {
    label: string
    icon: string
  }

  async function sendMessage(content: string, smartAction?: SmartActionOptions): Promise<void> {
    if (!content.trim() || store.isLoading || store.isStreaming) return

    const accessToken = authStore.accessToken
    if (!accessToken) {
      store.setError(t('screen.chapse.errors.notAuthenticated'))
      return
    }

    store.setError(null)
    store.setLoading(true)

    // Add user message to store with optional smart action metadata
    store.addUserMessage(content.trim(), smartAction)

    // Create streaming assistant message
    store.addAssistantMessage('', true)
    store.setStreamingState(true)

    try {
      let newConversationId: string | null = null

      // Build prior messages from store (exclude the user + assistant placeholder just added)
      const allMessages = store.messages
      const priorMessages: ChatHistoryMessage[] = []
      // Last 2 messages are the user message + streaming assistant placeholder we just added
      const historyMessages = allMessages.slice(0, allMessages.length - 2)
      for (const msg of historyMessages) {
        if (msg.isStreaming) continue
        const { cleanContent } = parseSmartActionMarker(msg.content)
        if (cleanContent.trim()) {
          priorMessages.push({ role: msg.role, content: cleanContent })
        }
      }

      await sendChatMessage(
        {
          query: content.trim(),
          conversation_id: store.currentConversationId,
          company_ids: store.companyIds,
          ...(priorMessages.length > 0 ? { messages: priorMessages } : {}),
        },
        accessToken,
        endpoints.value.apiUrl,
        // onChunk - append to streaming message
        (chunk: string) => {
          store.appendToLastMessage(chunk)
        },
        // onConversationId - capture new conversation ID
        (conversationId: string) => {
          newConversationId = conversationId
        },
        // onError
        (errorMessage: string) => {
          store.setError(errorMessage)
        },
      )

      // If this was a new conversation, update the store
      if (newConversationId && !store.currentConversationId) {
        // For smart actions, use the action label as the conversation name
        // This prevents the marker from showing in the title
        const conversationName = smartAction?.label || ''
        store.setCurrentConversation(newConversationId, conversationName)

        // Rename the conversation if it's a smart action (to override Dify's auto-generated name)
        if (smartAction?.label) {
          try {
            await apiRenameConversation(newConversationId, smartAction.label)
          } catch (err) {
            console.error('Failed to rename smart action conversation:', err)
          }
        }

        // Save company context for new conversation
        if (store.companyIds.length > 0) {
          try {
            await updateConversationContext(newConversationId, store.companyIds)
          } catch (err) {
            console.error('Failed to save company context:', err)
          }
        }

        // Add the new conversation to the list immediately
        // This provides instant feedback without waiting for a refresh
        const newConversation = {
          id: newConversationId,
          name: conversationName, // Use smart action label if available
          created_at: Math.floor(Date.now() / 1000),
          updated_at: Math.floor(Date.now() / 1000),
          company_ids: store.companyIds,
          companies: store.companyContext.map((c) => ({
            id: c.id,
            name: c.name,
            siren: c.siren || null,
          })),
        }
        store.prependConversation(newConversation)
      }
    } catch (err: unknown) {
      console.error('Error sending message:', err)
      const errorMessage = t('screen.chapse.errors.sendMessageFailed')
      store.setError(errorMessage)

      // Update the streaming message with error
      store.appendToLastMessage(errorMessage)
    } finally {
      store.finishStreaming()
      store.setLoading(false)
    }
  }

  // =========================================================================
  // Conversations
  // =========================================================================

  async function loadConversations(refresh: boolean = false): Promise<void> {
    if (store.conversationsLoading) return

    store.setConversationsLoading(true)

    try {
      const lastId = refresh ? undefined : conversations.value[conversations.value.length - 1]?.id
      const response = await getConversations(20, lastId)

      if (refresh) {
        store.setConversations(response.data, response.has_more)
      } else {
        store.appendConversations(response.data, response.has_more)
      }
    } catch (err) {
      console.error('Error loading conversations:', err)
      store.setError(t('screen.chapse.errors.loadConversationsFailed'))
    } finally {
      store.setConversationsLoading(false)
    }
  }

  async function loadConversation(conversationId: string): Promise<void> {
    if (!conversationId) return

    store.setLoading(true)
    store.setError(null)

    // Try localStorage first for instant display
    const hadCachedMessages = store.loadFromStorage(conversationId)

    try {
      const response = await getConversation(conversationId)

      // Update metadata from server (name, company context)
      store.setCurrentConversation(conversationId, response.name)

      const companies: CompanyContext[] = response.companies.map((c) => ({
        id: c.id,
        name: c.name,
        siren: c.siren,
      }))
      store.setCompanyContext(companies)

      // Only load API messages if server actually returned some
      // (post-LangGraph migration, server returns messages: [])
      if (response.messages.length > 0) {
        store.loadMessagesFromApi(response.messages)
      }
    } catch (err) {
      console.error('Error loading conversation:', err)
      // Only show error if we don't have cached messages to display
      if (!hadCachedMessages) {
        store.setError(t('screen.chapse.errors.loadConversationFailed'))
      }
    } finally {
      store.setLoading(false)
    }
  }

  async function deleteConversation(conversationId: string): Promise<boolean> {
    try {
      await apiDeleteConversation(conversationId)
      store.removeConversation(conversationId)
      invalidateConversations()
      return true
    } catch (err) {
      console.error('Error deleting conversation:', err)
      store.setError(t('screen.chapse.errors.deleteConversationFailed'))
      return false
    }
  }

  async function renameConversation(
    conversationId: string,
    name?: string,
    autoGenerate: boolean = false,
  ): Promise<boolean> {
    try {
      const response = await apiRenameConversation(conversationId, name, autoGenerate)
      store.updateConversationName(conversationId, response.name)
      return true
    } catch (err) {
      console.error('Error renaming conversation:', err)
      return false
    }
  }

  function startNewConversation(): void {
    store.startNewConversation()
  }

  // =========================================================================
  // Company Context
  // =========================================================================

  function addCompanyToContext(company: CompanyContext): boolean {
    return store.addCompanyToContext(company)
  }

  function removeCompanyFromContext(companyId: number): void {
    store.removeCompanyFromContext(companyId)
  }

  function clearCompanyContext(): void {
    store.clearCompanyContext()
  }

  function isCompanyInContext(companyId: number): boolean {
    return store.isCompanyInContext(companyId)
  }

  // Save context to server for existing conversation
  async function saveCompanyContext(): Promise<boolean> {
    if (!store.currentConversationId) return false

    try {
      await updateConversationContext(store.currentConversationId, store.companyIds)
      return true
    } catch (err) {
      console.error('Error saving company context:', err)
      return false
    }
  }

  // =========================================================================
  // Utility
  // =========================================================================

  function clearHistory(): void {
    store.startNewConversation()
    store.clearCompanyContext()
  }

  function invalidateConversations(): void {
    queryCache.invalidateQueries({ key: CHAPSE_QUERY_KEYS.conversations() })
  }

  // Format markdown in messages (kept for compatibility)
  function formatMarkdown(text: string): string {
    // Convert **text** to <strong>text</strong>
    let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

    // Convert *text* to <em>text</em> for italics
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

    // Convert numbered lists (1. Item) to HTML ordered lists
    formatted = formatted
      .replace(/(\d+\.\s.*?)(?=\n\d+\.|$)/gs, '<li>$1</li>')
      .replace(/(<li>.*?<\/li>)+/gs, '<ol class="list-decimal ml-4 my-2">$&</ol>')

    // Convert newlines to <br> tags
    return formatted.replace(/\n/g, '<br>')
  }

  // =========================================================================
  // Return
  // =========================================================================

  return {
    // State (from store)
    messages,
    isLoading,
    isStreaming,
    error,
    hasMessages,
    currentConversationId,
    currentConversationName,
    conversations,
    conversationsLoading,
    hasMoreConversations,

    // Company context
    companyContext,
    canAddMoreCompanies,
    companyContextCount,

    // Message methods
    sendMessage,

    // Conversation methods
    loadConversations,
    loadConversation,
    deleteConversation,
    renameConversation,
    startNewConversation,

    // Company context methods
    addCompanyToContext,
    removeCompanyFromContext,
    clearCompanyContext,
    isCompanyInContext,
    saveCompanyContext,

    // Utility methods
    clearHistory,
    formatMarkdown,
  }
}

// Re-export types for convenience
export type { CompanyContext } from '@/stores/chapse'
