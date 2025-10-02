import { ref, computed } from 'vue'
import { apiClient } from '@/api/client'
import { useI18n } from 'vue-i18n'

export interface ChapseMessage {
  id: string
  content: string
  role: 'user' | 'assistant'
  timestamp: number
  avatar?: string
}

export interface ChapseContext {
  type: 'company' | 'folder' | 'workspace'
  id: string | number
  name: string
  data?: any
}

interface ChatbotResponse {
  response: string
  status?: string
}

const STORAGE_KEY = 'chapse_chat_history'
const MAX_HISTORY_SIZE = 50

export function useChapseChat() {
  const { locale } = useI18n()

  // Reactive state
  const messages = ref<ChapseMessage[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const sessionId = ref<string>(generateSessionId())

  // Initialize with welcome message
  const initializeChat = () => {
    if (messages.value.length === 0) {
      const welcomeMessage: ChapseMessage = {
        id: generateMessageId(),
        content: locale.value === 'fr'
          ? "Bonjour ! Je suis Chaps-e, votre assistant IA. Je peux vous aider avec des questions sur vos entreprises, sur l'utilisation du logiciel, et bien plus encore. Comment puis-je vous aider ?"
          : "Hello! I'm Chaps-e, your AI assistant. I can help you with questions about your companies, how to use the software, and much more. How can I help you?",
        role: 'assistant',
        timestamp: Date.now(),
        avatar: 'chapse'
      }
      messages.value.push(welcomeMessage)
    }
  }

  // Load chat history from localStorage
  const loadHistory = () => {
    try {
      const stored = localStorage.getItem(`${STORAGE_KEY}_${sessionId.value}`)
      if (stored) {
        const parsed = JSON.parse(stored)
        messages.value = parsed
      } else {
        initializeChat()
      }
    } catch (err) {
      console.error('Failed to load chat history:', err)
      initializeChat()
    }
  }

  // Save chat history to localStorage
  const saveHistory = () => {
    try {
      // Keep only last MAX_HISTORY_SIZE messages
      const historyToSave = messages.value.slice(-MAX_HISTORY_SIZE)
      localStorage.setItem(`${STORAGE_KEY}_${sessionId.value}`, JSON.stringify(historyToSave))
    } catch (err) {
      console.error('Failed to save chat history:', err)
    }
  }

  // Clear chat history
  const clearHistory = () => {
    try {
      localStorage.removeItem(`${STORAGE_KEY}_${sessionId.value}`)
      messages.value = []
      initializeChat()
    } catch (err) {
      console.error('Failed to clear chat history:', err)
    }
  }

  // Send message to Chaps-e
  const sendMessage = async (content: string, contexts: ChapseContext[] = []) => {
    if (!content.trim() || isLoading.value) return

    error.value = null

    // Add user message
    const userMessage: ChapseMessage = {
      id: generateMessageId(),
      content: content.trim(),
      role: 'user',
      timestamp: Date.now()
    }
    messages.value.push(userMessage)
    saveHistory()

    isLoading.value = true

    try {
      // Format chat history for API
      const chatHistory = messages.value
        .slice(0, -1) // Exclude the message we just added
        .map(msg => ({
          role: msg.role === 'user' ? 'user' : 'assistant',
          content: msg.content
        }))

      // Format contexts for API
      const contextsPayload: any = {}
      contexts.forEach(ctx => {
        contextsPayload[ctx.type] = ctx.data || { id: ctx.id, name: ctx.name }
      })

      // Make API call
      const response: ChatbotResponse = await apiClient.post('/chatbot', {
        message: content.trim(),
        contexts: contextsPayload,
        chat_history: chatHistory,
        language: locale.value
      })

      // Parse response (handle stringified JSON if needed)
      let responseText = response.response || "Je n'ai pas pu générer de réponse."

      if (typeof responseText === 'string' && responseText.startsWith('{') && responseText.endsWith('}')) {
        try {
          const parsed = eval(`(${responseText})`)
          if (parsed && typeof parsed === 'object' && 'output' in parsed) {
            responseText = parsed.output
          }
        } catch (parseError) {
          console.warn('Could not parse stringified response:', parseError)
        }
      }

      // Add assistant message
      const assistantMessage: ChapseMessage = {
        id: generateMessageId(),
        content: responseText,
        role: 'assistant',
        timestamp: Date.now(),
        avatar: 'chapse'
      }
      messages.value.push(assistantMessage)
      saveHistory()

    } catch (err: any) {
      console.error('Error sending message:', err)
      error.value = err.message || 'Une erreur est survenue. Veuillez réessayer.'

      // Add error message
      const errorMessage: ChapseMessage = {
        id: generateMessageId(),
        content: locale.value === 'fr'
          ? "Désolé, une erreur s'est produite lors du traitement de votre demande. Veuillez réessayer ou contacter un administrateur."
          : "Sorry, an error occurred while processing your request. Please try again or contact an administrator.",
        role: 'assistant',
        timestamp: Date.now(),
        avatar: 'chapse'
      }
      messages.value.push(errorMessage)
      saveHistory()
    } finally {
      isLoading.value = false
    }
  }

  // Computed properties
  const hasMessages = computed(() => messages.value.length > 1) // More than just welcome message

  // Helper functions
  function generateMessageId(): string {
    return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
  }

  function generateSessionId(): string {
    const stored = sessionStorage.getItem('chapse_session_id')
    if (stored) return stored

    const newId = `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
    sessionStorage.setItem('chapse_session_id', newId)
    return newId
  }

  // Format markdown in messages
  const formatMarkdown = (text: string): string => {
    // Convert **text** to <strong>text</strong>
    const boldFormatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

    // Convert *text* to <em>text</em> for italics
    const italicsFormatted = boldFormatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

    // Convert numbered lists (1. Item) to HTML ordered lists
    const listFormatted = italicsFormatted
      .replace(/(\d+\.\s.*?)(?=\n\d+\.|$)/gs, '<li>$1</li>')
      .replace(/(<li>.*?<\/li>)+/gs, '<ol class="list-decimal ml-4 my-2">$&</ol>')

    // Convert newlines to <br> tags
    return listFormatted.replace(/\n/g, '<br>')
  }

  return {
    // State
    messages,
    isLoading,
    error,
    hasMessages,

    // Methods
    sendMessage,
    loadHistory,
    clearHistory,
    initializeChat,
    formatMarkdown
  }
}
