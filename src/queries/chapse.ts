/**
 * Chapse AI Chatbot Pinia Colada Queries
 *
 * Query definitions for conversations and context data fetching.
 */
import { defineQueryOptions } from '@pinia/colada'
import { getConversations, getConversation, getConversationContext } from '@/api/chapse'

// =============================================================================
// Query Keys
// =============================================================================

export const CHAPSE_QUERY_KEYS = {
  root: ['chapse'] as const,
  conversations: () => [...CHAPSE_QUERY_KEYS.root, 'conversations'] as const,
  conversationsWithFilters: (filters: { limit: number; lastId?: string }) =>
    [...CHAPSE_QUERY_KEYS.conversations(), { filters }] as const,
  conversationById: (id: string) => [...CHAPSE_QUERY_KEYS.root, 'conversation', id] as const,
  conversationContext: (id: string) => [...CHAPSE_QUERY_KEYS.root, 'context', id] as const,
}

// =============================================================================
// Conversations Query
// =============================================================================

/**
 * Query for listing conversations with pagination.
 */
export const conversationsQuery = defineQueryOptions(
  ({ limit, lastId }: { limit?: number; lastId?: string }) => ({
    key: CHAPSE_QUERY_KEYS.conversationsWithFilters({ limit: limit || 20, lastId }),
    query: () => getConversations(limit || 20, lastId),
  }),
)

// =============================================================================
// Conversation Detail Query
// =============================================================================

/**
 * Query for getting a single conversation with messages.
 */
export const conversationByIdQuery = defineQueryOptions(
  ({ id, limit, firstId }: { id: string; limit?: number; firstId?: string }) => ({
    key: CHAPSE_QUERY_KEYS.conversationById(id),
    query: () => {
      if (!id || id === 'null' || id === 'undefined' || id.trim() === '') {
        return Promise.resolve(null)
      }
      return getConversation(id, limit || 50, firstId)
    },
  }),
)

// =============================================================================
// Conversation Context Query
// =============================================================================

/**
 * Query for getting conversation company context.
 */
export const conversationContextQuery = defineQueryOptions(({ id }: { id: string }) => ({
  key: CHAPSE_QUERY_KEYS.conversationContext(id),
  query: () => {
    if (!id || id === 'null' || id === 'undefined' || id.trim() === '') {
      return Promise.resolve({ company_ids: [], companies: [] })
    }
    return getConversationContext(id)
  },
}))
