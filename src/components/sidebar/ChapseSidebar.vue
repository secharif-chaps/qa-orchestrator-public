<template>
  <div class="flex flex-col justify-between h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between border-b-2 shadow border-sage-800 px-4 py-2">
      <h2 class="text-headline-2xl">Chaps-e</h2>
      <div class="flex items-center gap-2">
        <Button
          variant="ghost-primary"
          dark
          icon="fa-solid fa-trash"
          icon-only
          @click="handleClearHistory"
          size="sm"
        />
      </div>
    </div>

    <!-- Context Selector Buttons -->
    <div
      v-if="availableContexts.length > 0"
      class="px-4 py-2 border-b border-sage-800 flex items-center gap-2 flex-wrap"
    >
      <span class="text-xs text-sage-200">Contexte :</span>
      <button
        v-for="context in availableContexts"
        :key="`${context.type}-${context.id}`"
        @click="handleAddContext(context)"
        :disabled="isContextActive(context)"
        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium transition-all"
        :class="isContextActive(context)
          ? 'bg-sage-300 text-sage-950 cursor-not-allowed opacity-100'
          : 'bg-sage-800 text-sage-200 hover:bg-sage-700 cursor-pointer'"
      >
        <i :class="getContextIcon(context)" class="text-xs"></i>
        <span>{{ context.name }}</span>
        <i v-if="!isContextActive(context)" class="fa fa-plus text-[10px]"></i>
        <i v-else class="fa fa-check text-[10px]"></i>
      </button>
    </div>

    <!-- Messages Container -->
    <div
      ref="messagesContainer"
      class="overflow-y-auto px-4 py-4 grow max-h-[calc(100vh-450px)]"
    >
      <ChatMessage
        v-for="message in messages"
        :key="message.id"
        :message="message"
      />

      <!-- Loading Indicator -->
      <div v-if="isLoading" class="flex gap-3 mb-4">
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
          <img :src="chapseAvatar" class="w-6 h-6" alt="Chaps-e" />
        </div>
        <div class="bg-almond-300/30 text-white text-sm rounded-xl px-4 py-3">
          <i class="fa fa-spinner fa-spin mr-2"></i>
          {{ $t('chapse.thinking') || 'Réflexion en cours...' }}
        </div>
      </div>

      <!-- Suggestion Buttons (shown when no messages) -->
      <div
        v-if="!hasMessages && !isLoading && suggestions.length > 0"
        class="w-full grow gap-2 flex flex-col items-center justify-center py-8"
      >
        <img :src="withBody" class="w-32 h-32 mb-4" />
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

    <!-- Active Context Badges -->
    <div
      v-if="hasActiveContexts"
      class="px-4 py-2 border-t border-sage-800 flex items-center gap-2 flex-wrap"
    >
      <span class="text-xs text-sage-200">Contexte actif :</span>
      <ContextBadge
        v-for="context in activeContexts"
        :key="`active-${context.type}-${context.id}`"
        :context="context"
        dismissible
        @dismiss="removeContext(context.id.toString())"
      />
    </div>

    <!-- Input Area -->
    <div class="relative p-4">
      <textarea
        ref="textareaRef"
        v-model="userMessage"
        @keydown.enter.ctrl.prevent="handleSendMessage"
        :placeholder="$t('chapse.placeholder') || 'Écrivez un message...'"
        class="p-4 w-full h-32 bg-sage-900 rounded-block text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary"
        :disabled="isLoading"
      ></textarea>
      <Button
        variant="primary"
        icon="fa fa-send"
        icon-only
        class="absolute right-6 bottom-8"
        @click="handleSendMessage"
        :disabled="isLoading || !userMessage.trim()"
      />
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="px-4 pb-4">
      <Alert
        variant="error"
        :message="error"
        dismissible
        @dismiss="error = null"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick, watch, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useChapseChat } from '@/composables/useChapseChat'
import { useChapseContext } from '@/composables/useChapseContext'
import ChatMessage from '@/components/chapse/ChatMessage.vue'
import ContextBadge from '@/components/chapse/ContextBadge.vue'
import Button from '../ui/Button.vue'
import Alert from '../ui/Alert.vue'
import chapseAvatar from '@/assets/chapse/head.svg'
import withBody from '@/assets/chapse/default.svg'
import type { ChapseContext } from '@/composables/useChapseChat'

// Router
const route = useRoute()

// Chat composable
const {
  messages,
  isLoading,
  error,
  hasMessages,
  sendMessage,
  loadHistory,
  clearHistory,
  initializeChat
} = useChapseChat()

// Context composable
const {
  activeContexts,
  availableContexts,
  hasActiveContexts,
  addContext,
  removeContext,
  isContextActive,
  getContextIcon,
  shouldWarnContextSwitch
} = useChapseContext()

// Local state
const userMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)
const textareaRef = ref<HTMLTextAreaElement | null>(null)

// Context-aware suggestions based on current route
interface Suggestion {
  label: string
  message: string
}

const suggestions = computed<Suggestion[]>(() => {
  const routeName = route.name as string

  // Home page suggestions
  if (routeName === '/(home)') {
    return [
      { label: 'Montre-moi mes entreprises récentes', message: 'Montre-moi mes entreprises récentes' },
      { label: "Résume l'activité de mon workspace", message: "Résume l'activité de mon workspace" }
    ]
  }

  // Company page suggestions (must check before folder page)
  // Routes: /folders/[folderId]/companies/[companyId], /folders/[folderId]/companies/[companyId]/,
  // /folders/[folderId]/companies/[companyId]/profile, etc.
  if (routeName?.includes('/companies/[companyId]')) {
    return [
      { label: 'Fais-moi une synthèse de cette entreprise', message: 'Fais-moi une synthèse de cette entreprise' },
      { label: 'Liste les technologies citées', message: 'Liste les technologies citées' }
    ]
  }

  // Folder page suggestions
  // Routes: /folders/[folderId], /folders/[folderId]/(folderId)
  if (routeName?.startsWith('/folders/[folderId]') && !routeName?.includes('/companies/')) {
    return [
      { label: 'Résume les entreprises de ce dossier', message: 'Résume les entreprises de ce dossier' },
      { label: 'Compare les entreprises de ce dossier', message: 'Compare les entreprises de ce dossier' }
    ]
  }

  // No suggestions for other pages
  return []
})

// Load chat history on mount
onMounted(() => {
  loadHistory()
})

// Auto-scroll to bottom when messages change
watch(
  messages,
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  },
  { deep: true }
)

// Handle send message
const handleSendMessage = async () => {
  if (!userMessage.value.trim() || isLoading.value) return

  const messageToSend = userMessage.value.trim()
  userMessage.value = ''

  await sendMessage(messageToSend, activeContexts.value)

  // Clear active contexts after sending
  activeContexts.value = []
}

// Handle suggestion button click
const sendSuggestion = (suggestion: string) => {
  userMessage.value = suggestion
  handleSendMessage()
}

// Handle add context
const handleAddContext = (context: ChapseContext) => {
  if (shouldWarnContextSwitch(context)) {
    // Warn user about context switch
    const confirmed = confirm(
      `Voulez-vous remplacer le contexte actuel par ${context.name} ? Cela peut affecter la pertinence de la réponse.`
    )
    if (!confirmed) return

    // Remove existing company context
    const existingCompany = activeContexts.value.find(ctx => ctx.type === 'company')
    if (existingCompany) {
      removeContext(existingCompany.id.toString())
    }
  }

  addContext(context)
}

// Handle clear history
const handleClearHistory = () => {
  clearHistory()
}
</script>
