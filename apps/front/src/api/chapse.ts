/**
 * Chapse AI Chatbot API functions
 *
 * Handles communication with the Chapse backend endpoints
 * for conversations, messages, and company context management.
 */
import { apiClient } from './client'

// =============================================================================
// Types
// =============================================================================

export interface CompanySummary {
  id: number
  name: string
  siren: string | null
}

export interface ChapseConversation {
  id: string
  name: string
  created_at: number
  updated_at: number
  company_ids: number[]
  companies: CompanySummary[]
}

export interface ChapseMessage {
  id: string
  conversation_id: string
  query: string
  answer: string
  created_at: number
  feedback: 'like' | 'dislike' | null
}

export interface ConversationsResponse {
  data: ChapseConversation[]
  has_more: boolean
  limit: number
}

export interface ConversationDetailResponse {
  id: string
  name: string
  created_at: number
  updated_at: number
  company_ids: number[]
  companies: CompanySummary[]
  messages: ChapseMessage[]
  has_more: boolean
}

export interface ContextResponse {
  company_ids: number[]
  companies: CompanySummary[]
}

export interface RenameResponse {
  id: string
  name: string
}

// =============================================================================
// Chat API
// =============================================================================

/**
 * Send a chat message and receive SSE streaming response.
 *
 * This returns an EventSource-like interface for handling streaming.
 * The actual streaming is handled by the composable.
 */
export interface ChatHistoryMessage {
  role: 'user' | 'assistant'
  content: string
}

export interface ChapseChatRequest {
  query: string
  conversation_id?: string | null
  company_ids?: number[]
  messages?: ChatHistoryMessage[]
}

/**
 * Get the chat endpoint URL for SSE streaming.
 * Returns the full URL that can be used with EventSource or fetch.
 */
export function getChatEndpointUrl(): string {
  // Get base URL from apiClient (we need to access the same base)
  return '/chapse/chat'
}

/**
 * Send a chat message via POST with streaming response.
 *
 * This function is designed to be called with fetch() directly
 * as apiClient doesn't support streaming responses.
 */
export async function sendChatMessage(
  request: ChapseChatRequest,
  accessToken: string,
  baseUrl: string,
  onChunk: (chunk: string) => void,
  onConversationId?: (conversationId: string) => void,
  onError?: (error: string) => void,
): Promise<void> {
  const response = await fetch(`${baseUrl}/chapse/chat`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
    body: JSON.stringify(request),
  })

  if (!response.ok) {
    const errorText = await response.text()
    throw new Error(`Chat request failed: ${response.status} ${errorText}`)
  }

  if (!response.body) {
    throw new Error('Response body is null')
  }

  const reader = response.body.getReader()
  const decoder = new TextDecoder()

  try {
    while (true) {
      const { done, value } = await reader.read()
      if (done) break

      const chunk = decoder.decode(value, { stream: true })

      // Parse SSE events from chunk
      const lines = chunk.split('\n')
      for (const line of lines) {
        if (line.startsWith('data: ')) {
          const data = line.slice(6)
          if (data === '[DONE]') continue

          try {
            const parsed = JSON.parse(data)

            // Handle different event types from Dify
            if (parsed.event === 'message' || parsed.event === 'agent_message') {
              if (parsed.answer) {
                onChunk(parsed.answer)
              }
            } else if (parsed.event === 'message_end') {
              // Conversation ID is returned at the end
              if (parsed.conversation_id && onConversationId) {
                onConversationId(parsed.conversation_id)
              }
            } else if (parsed.event === 'error') {
              if (onError) {
                onError(parsed.message || 'Unknown error')
              }
            }
          } catch {
            // Not JSON, might be partial data
          }
        }
      }
    }
  } finally {
    reader.releaseLock()
  }
}

// =============================================================================
// Conversations API
// =============================================================================

/**
 * List user's conversations with company context.
 */
export async function getConversations(
  limit: number = 20,
  lastId?: string,
): Promise<ConversationsResponse> {
  const params = new URLSearchParams({ limit: limit.toString() })
  if (lastId) {
    params.append('last_id', lastId)
  }
  return apiClient.get<ConversationsResponse>(`/chapse/conversations?${params.toString()}`)
}

/**
 * Get conversation detail with messages.
 */
export async function getConversation(
  conversationId: string,
  limit: number = 50,
  firstId?: string,
): Promise<ConversationDetailResponse> {
  const params = new URLSearchParams({ limit: limit.toString() })
  if (firstId) {
    params.append('first_id', firstId)
  }
  return apiClient.get<ConversationDetailResponse>(
    `/chapse/conversations/${conversationId}?${params.toString()}`,
  )
}

/**
 * Delete a conversation.
 */
export async function deleteConversation(conversationId: string): Promise<void> {
  await apiClient.delete(`/chapse/conversations/${conversationId}`)
}

/**
 * Rename a conversation.
 */
export async function renameConversation(
  conversationId: string,
  name?: string,
  autoGenerate: boolean = false,
): Promise<RenameResponse> {
  return apiClient.post<RenameResponse>(`/chapse/conversations/${conversationId}/rename`, {
    name,
    auto_generate: autoGenerate,
  })
}

// =============================================================================
// Context API
// =============================================================================

/**
 * Get company context for a conversation.
 */
export async function getConversationContext(conversationId: string): Promise<ContextResponse> {
  return apiClient.get<ContextResponse>(`/chapse/conversations/${conversationId}/context`)
}

/**
 * Update company context for a conversation.
 */
export async function updateConversationContext(
  conversationId: string,
  companyIds: number[],
): Promise<ContextResponse> {
  return apiClient.put<ContextResponse>(`/chapse/conversations/${conversationId}/context`, {
    company_ids: companyIds,
  })
}

// =============================================================================
// Export API object
// =============================================================================

export const chapseApi = {
  sendChatMessage,
  getConversations,
  getConversation,
  deleteConversation,
  renameConversation,
  getConversationContext,
  updateConversationContext,
}
