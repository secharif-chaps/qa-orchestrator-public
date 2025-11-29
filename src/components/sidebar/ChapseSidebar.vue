<template>
  <div class="flex " :class="{ 'h-[calc(100vh-140px)]': !sidebarStore.isFullscreen, 'h-[calc(100vh-70px)]': sidebarStore.isFullscreen }">
    <!-- Conversation List (visible in fullscreen mode) -->
    <div
      v-if="sidebarStore.isFullscreen"
      class="w-64 flex-shrink-0 border-r border-sage-700 bg-sage-850"
    >
      <ConversationList
        :conversations="conversations"
        :current-conversation-id="currentConversationId"
        :loading="conversationsLoading"
        :has-more="hasMoreConversations"
        @select="handleSelectConversation"
        @delete="handleDeleteConversation"
        @new-conversation="handleNewConversation"
        @load-more="handleLoadMoreConversations"
      />
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-sage-700 px-4 py-2">
        <div class="flex items-center gap-3">
          <h2 class="text-headline-2xl">{{ $t('sidebar.chapse.title', 'Chaps-e') }}</h2>
          <span v-if="currentConversationName" class="text-sm text-sage-400 truncate max-w-[200px]">
            {{ currentConversationName }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="tertiary"
            dark
            :icon="sidebarStore.isFullscreen ? 'fa-solid fa-compress' : 'fa-solid fa-expand'"
            icon-only
            size="sm"
            :title="sidebarStore.isFullscreen ? $t('chapse.exitFullscreen', 'Exit fullscreen') : $t('chapse.enterFullscreen', 'Enter fullscreen')"
            @click="toggleFullscreen"
          />
          <Button
            variant="tertiary"
            dark
            icon="fa-solid fa-plus"
            icon-only
            size="sm"
            :title="$t('chapse.newConversation', 'New conversation')"
            @click="handleNewConversation"
          />
          <Button
            variant="tertiary"
            dark
            icon="fa-solid fa-trash"
            icon-only
            size="sm"
            :title="$t('chapse.clearHistory', 'Clear history')"
            @click="handleClearHistory"
          />
        </div>
      </div>

      <!-- Add Company from Page Button (when on company page and not in context) -->
      <div
        v-if="availablePageContext && !isPageContextActive && canAddMoreCompanies"
        class="px-4 py-2 border-b border-sage-700"
      >
        <button
          class="w-full flex items-center gap-2 px-3 py-2 rounded-lg bg-sage-800 hover:bg-sage-700 transition-colors text-left"
          @click="handleAddPageContext"
        >
          <i class="fa fa-building text-sage-400 text-sm"></i>
          <span class="text-sm text-sage-200">
            {{ $t('chapse.addThisCompany', 'Add') }}
            <strong>{{ availablePageContext.name }}</strong>
            {{ $t('chapse.toContext', 'to context') }}
          </span>
          <i class="fa fa-plus text-sage-400 text-xs ml-auto"></i>
        </button>
      </div>

      <!-- Messages Container -->
      <div
        ref="messagesContainer"
        class="flex-1 overflow-y-auto px-4 py-4"
      >
        <!-- Welcome State (no messages) -->
        <div
          v-if="!hasMessages && !isLoading"
          class="flex flex-col items-center justify-center h-full gap-4"
        >
          <img :src="withBody" class="w-32 h-32" alt="Chaps-e" />
          <p class="text-sage-300 text-center max-w-xs">
            {{ $t('chapse.welcomeMessage', 'Hello! I\'m Chaps-e, your AI assistant. How can I help you today?') }}
          </p>

          <!-- Suggestions -->
          <div v-if="suggestions.length > 0" class="flex flex-col gap-2 mt-4">
            <Button
              v-for="suggestion in suggestions"
              :key="suggestion.label"
              variant="secondary"
              dark
              size="sm"
              @click="sendSuggestion(suggestion.message)"
            >
              {{ suggestion.label }}
            </Button>
          </div>
        </div>

        <!-- Messages -->
        <template v-else>
          <ChatMessage
            v-for="message in messages"
            :key="message.id"
            :message="message"
          />

          <!-- Thinking Indicator (only shows before content arrives) -->
          <div v-if="isThinking" class="flex gap-3 mb-4">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-sage-800 flex items-center justify-center">
              <img :src="chapseAvatar" class="w-6 h-6" alt="Chaps-e" />
            </div>
            <div class="bg-sage-800 text-sage-200 text-sm rounded-xl px-4 py-3">
              <i class="fa fa-circle fa-beat text-primary text-xs mr-2"></i>
              {{ $t('chapse.thinking', 'Thinking...') }}
            </div>
          </div>
        </template>
      </div>

      <!-- Chat Input -->
      <ChatInput
        v-model="userMessage"
        :placeholder="$t('sidebar.chapse.placeholder', 'Write a message...')"
        :loading="isLoading"
        :disabled="isStreaming"
        :company-context="companyContext"
        :can-add-more-companies="canAddMoreCompanies"
        @send="handleSendMessage"
        @add-context="handleAddCompanyContext"
        @remove-context="handleRemoveCompanyContext"
      />

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useChapseChat, type CompanyContext } from '@/composables/useChapseChat'
import { useChapseContext } from '@/composables/useChapseContext'
import { useSidebarStore } from '@/stores/sidebar'
import { useChapseStore } from '@/stores/chapse'
import ChatMessage from '@/components/chapse/ChatMessage.vue'
import ChatInput from '@/components/chapse/ChatInput.vue'
import ConversationList from '@/components/chapse/ConversationList.vue'
import Button from '@/components/ui/Button.vue'
import chapseAvatar from '@/assets/chapse/head.svg'
import withBody from '@/assets/chapse/default.svg'
import { toast } from '@/utils/toast'

// Stores
const sidebarStore = useSidebarStore()
const chapseStore = useChapseStore()

// Router
const route = useRoute()

// Chat composable
const {
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
  companyContext,
  canAddMoreCompanies,
  sendMessage,
  loadConversations,
  loadConversation,
  deleteConversation,
  startNewConversation,
  addCompanyToContext,
  removeCompanyFromContext,
  clearHistory,
} = useChapseChat()

// Context composable
const {
  availablePageContext,
  isPageContextActive,
  addPageContextToChat,
} = useChapseContext()

// Local state
const userMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

// Thinking state: streaming is active but no content has arrived yet
const isThinking = computed(() => {
  if (!isStreaming.value) return false

  // Check if the last message is an assistant message with no content
  const lastMessage = messages.value[messages.value.length - 1]
  return lastMessage?.role === 'assistant' && !lastMessage.content
})

// Suggestions based on route
interface Suggestion {
  label: string
  message: string
}

const suggestions = computed<Suggestion[]>(() => {
  const routeName = route.name as string

  if (routeName === '/(home)') {
    return [
      { label: 'Montre-moi mes entreprises récentes', message: 'Montre-moi mes entreprises récentes' },
      { label: "Résume l'activité de mon organization", message: "Résume l'activité de mon organization" },
    ]
  }

  if (routeName?.includes('/companies/[companyId]')) {
    return [
      { label: 'Fais-moi une synthèse de cette entreprise', message: 'Fais-moi une synthèse de cette entreprise' },
      { label: 'Liste les technologies citées', message: 'Liste les technologies citées' },
    ]
  }

  if (routeName?.startsWith('/folders/[folderId]') && !routeName?.includes('/companies/')) {
    return [
      { label: 'Résume les entreprises de ce dossier', message: 'Résume les entreprises de ce dossier' },
      { label: 'Compare les entreprises de ce dossier', message: 'Compare les entreprises de ce dossier' },
    ]
  }

  return []
})

// Handlers
async function handleSendMessage(message: string) {
  if (!message.trim()) return
  userMessage.value = ''
  await sendMessage(message)
}

function sendSuggestion(suggestion: string) {
  handleSendMessage(suggestion)
}

function handleAddCompanyContext(company: CompanyContext) {
  addCompanyToContext(company)
}

function handleRemoveCompanyContext(companyId: number) {
  removeCompanyFromContext(companyId)
}

function handleAddPageContext() {
  addPageContextToChat()
}

async function handleSelectConversation(conversationId: string) {
  await loadConversation(conversationId)
}

async function handleDeleteConversation(conversationId: string) {
  if (confirm('Are you sure you want to delete this conversation?')) {
    await deleteConversation(conversationId)
  }
}

function handleNewConversation() {
  startNewConversation()
}

async function handleLoadMoreConversations() {
  await loadConversations(false)
}

function handleClearHistory() {
  if (confirm('Are you sure you want to clear all messages?')) {
    clearHistory()
  }
}

function toggleFullscreen() {
  sidebarStore.setFullscreen(!sidebarStore.isFullscreen)
}

// Auto-scroll to bottom when messages change
watch(
  messages,
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  },
  { deep: true },
)

// Show toast notification when error occurs
watch(
  error,
  (newError) => {
    if (newError) {
      toast.error(newError)
      // Clear error from store after showing toast
      chapseStore.setError(null)
    }
  },
)

// Load conversations on mount
onMounted(async () => {
  await loadConversations(true)
})
</script>
