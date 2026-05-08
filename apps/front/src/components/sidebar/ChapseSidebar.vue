<template>
  <div
    class="flex px-6"
    :class="{
      'h-[calc(100vh-140px)]': !sidebarStore.isFullscreen,
      'h-[calc(100vh-70px)]': sidebarStore.isFullscreen,
    }"
  >
    <!-- Conversation List (visible in fullscreen mode) -->
    <div
      v-if="sidebarStore.isFullscreen"
      class="border-sage-300 dark:border-sage-700 bg-sage-850 w-64 shrink-0 border-r"
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
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Header -->
      <SidebarHeader :title="$t('sidebar.chapse.title')">
        <div class="flex items-center gap-2">
          <Button
            variant="tertiary"
            :icon="sidebarStore.isFullscreen ? 'fa-solid fa-compress' : 'fa-solid fa-expand'"
            size="sm"
            :title="
              sidebarStore.isFullscreen
                ? $t('sidebar.chapse.exitFullscreen')
                : $t('sidebar.chapse.enterFullscreen')
            "
            @click="toggleFullscreen"
          />
          <Button
            variant="tertiary"
            icon="fa-solid fa-plus"
            size="sm"
            :title="$t('sidebar.chapse.newConversation')"
            @click="handleNewConversation"
          />
          <Button
            variant="tertiary"
            icon="fa-solid fa-trash"
            size="sm"
            :title="$t('sidebar.chapse.clearHistory')"
            @click="handleClearHistory"
          />
        </div>
      </SidebarHeader>

      <!-- Add Company from Page Button (when on company page and not in context) -->
      <div
        v-if="availablePageContext && !isPageContextActive && canAddMoreCompanies"
        class="border-sage-300 dark:border-sage-700 border-b px-4 py-2"
      >
        <Button
          variant="primary"
          size="sm"
          icon="fa-building"
          icon-right="fa-plus"
          class="w-full"
          @click="handleAddPageContext"
        >
          <i18n-t scope="global" keypath="common.sidebar.chapse.addThisCompanyToContext" tag="span">
            <template #name>
              <strong>{{ availablePageContext.name }}</strong>
            </template>
          </i18n-t>
        </Button>
      </div>

      <!-- Messages Container -->
      <div ref="messagesContainer" class="flex-1 overflow-y-auto px-4 py-4">
        <!-- Welcome State (no messages) -->
        <div
          v-if="!hasMessages && !isLoading"
          class="flex h-full flex-col items-center justify-center gap-4"
        >
          <img :src="withBody" class="h-32 w-32" alt="Chaps-e" />
          <p class="text-sage-900 dark:text-sage-300 max-w-80 text-center">
            {{
              $t(
                'sidebar.chapse.welcomeMessage',
                "Hello! I'm Chaps-e, your AI assistant. How can I help you today?",
              )
            }}
          </p>

          <!-- Suggestions -->
          <div v-if="suggestions.length > 0" class="mt-4 flex flex-col gap-2">
            <Button
              v-for="suggestion in suggestions"
              :key="suggestion.label"
              variant="secondary"
              size="sm"
              :label="suggestion.label"
              @click="sendSuggestion(suggestion.message)"
            />
          </div>
        </div>

        <!-- Messages -->
        <template v-else>
          <ChatMessage v-for="message in messages" :key="message.id" :message="message" />

          <!-- Thinking Indicator (only shows before content arrives) -->
          <!-- <div v-if="isThinking" class="flex gap-3 mb-4">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-sage-800 flex items-center justify-center">
              <img :src="chapseAvatar" class="w-6 h-6" alt="Chaps-e" />
            </div>
            <div class="bg-sage-800 text-sage-200 text-sm rounded-md px-4 py-3">
              <i class="fa fa-circle fa-beat text-primary text-xs mr-2"></i>
              {{ $t('sidebar.chapse.thinking') }}
            </div>
          </div> -->
        </template>
      </div>

      <!-- Chat Input -->
      <ChatInput
        v-model="userMessage"
        :placeholder="$t('sidebar.chapse.placeholder')"
        :loading="isLoading"
        :disabled="isStreaming || !canUseChapse"
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
import { getCompanyById } from '@/api/companies'
import withBody from '@/assets/chapse/default.svg'
import ChatInput from '@/components/chapse/ChatInput.vue'
import ChatMessage from '@/components/chapse/ChatMessage.vue'
import ConversationList from '@/components/chapse/ConversationList.vue'
import { useChapseChat, type CompanyContext } from '@/composables/useChapseChat'
import { useChapseContext } from '@/composables/useChapseContext'
import { useAuthStore } from '@/stores/auth'
import { buildSmartActionMarker, useChapseStore } from '@/stores/chapse'
import { useSidebarStore } from '@/stores/sidebar'
import { toast } from '@/utils/toast'
import { Button } from '@owlint/feathers-vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SidebarHeader from './SidebarHeader.vue'

// =============================================================================
// Props & Emits
// =============================================================================

interface AssistActionData {
  action: {
    id: string
    label: string
    description: string
    icon: string
  }
  user_preferences: {
    role: string
    goals: string
    desired_output: string
    documentation: string
  }
  companyId: number
}

const props = defineProps<{
  /**
   * Pending assist action from Smart Assist quick actions
   */
  pendingAssistAction?: AssistActionData | null
}>()

const emit = defineEmits<{
  /**
   * Emitted when the assist action has been processed
   */
  assistActionProcessed: []
}>()

// =============================================================================
// Assist Action Processing
// =============================================================================

/**
 * Process a smart assist action by:
 * 1. Adding the company to context (if not already)
 * 2. Starting a new conversation
 * 3. Sending the action as a message with user preferences context
 */
async function processAssistAction(actionData: AssistActionData): Promise<void> {
  console.log('🤖 Processing assist action:', actionData)

  try {
    // Start a new conversation for the assist action
    startNewConversation()

    // Try to fetch company data and add to context
    try {
      const company = await getCompanyById(String(actionData.companyId))
      if (company && company.id !== undefined) {
        addCompanyToContext({
          id: company.id,
          name: company.name,
          siren: (company as unknown as Record<string, unknown>).siren as string | undefined,
        })
      }
    } catch (err) {
      console.warn('Could not fetch company for context:', err)
    }

    // Build the assist action message with marker prefix for persistence
    const { message, label, icon } = buildAssistActionMessage(actionData)

    // Send the message with smart action metadata for special rendering
    await sendMessage(message, { label, icon })

    console.log('✅ Assist action processed successfully')
  } catch (err) {
    console.error('Failed to process assist action:', err)
    toast.error('Failed to execute smart action')
  }
}

/**
 * Build a message from the assist action data
 * This constructs a prompt that includes user preferences as context
 * Returns the full message (with marker prefix for persistence), label, and icon
 */
function buildAssistActionMessage(actionData: AssistActionData): {
  message: string
  label: string
  icon: string
} {
  const { action, user_preferences } = actionData

  // Build a structured message that the AI can understand
  const parts = [
    `**Action demandée:** ${action.label}`,
    `**Description:** ${action.description}`,
    '',
    '**Contexte utilisateur:**',
    `- Rôle: ${user_preferences.role}`,
    `- Objectifs: ${user_preferences.goals}`,
    `- Format de sortie souhaité: ${user_preferences.desired_output}`,
  ]

  // Add documentation context if provided
  if (user_preferences.documentation) {
    parts.push(`- Documentation: ${user_preferences.documentation}`)
  }

  parts.push(
    '',
    'Merci de répondre à cette demande en tenant compte de mon profil et de mes préférences.',
  )

  const promptContent = parts.join('\n')

  // Get icon from action data, with a fallback
  const icon = action.icon || 'fa-solid fa-wand-magic-sparkles'

  // Build the message with marker prefix for persistence
  // The marker allows us to detect smart action messages when loading from history
  const marker = buildSmartActionMarker(action.label, icon)
  const message = marker + promptContent

  return { message, label: action.label, icon }
}

// Stores
const sidebarStore = useSidebarStore()
const chapseStore = useChapseStore()

// Router
const route = useRoute()

// i18n
const { t } = useI18n()

// Chat composable
const {
  messages,
  isLoading,
  isStreaming,
  error,
  hasMessages,
  currentConversationId,
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
const { availablePageContext, isPageContextActive, addPageContextToChat } = useChapseContext()

// Local state
const userMessage = ref('')
const messagesContainer = ref<HTMLElement | null>(null)

// Suggestions based on route
interface Suggestion {
  label: string
  message: string
}

const suggestions = computed<Suggestion[]>(() => {
  const routeName = route.name as string

  if (routeName === '/(home)') {
    return [
      {
        label: t('sidebar.chapse.suggestions.home.recentCompanies'),
        message: t('sidebar.chapse.suggestions.home.recentCompanies'),
      },
      {
        label: t('sidebar.chapse.suggestions.home.orgActivity'),
        message: t('sidebar.chapse.suggestions.home.orgActivity'),
      },
    ]
  }

  if (routeName?.includes('/companies/[companyId]')) {
    return [
      {
        label: t('sidebar.chapse.suggestions.company.summary'),
        message: t('sidebar.chapse.suggestions.company.summary'),
      },
      {
        label: t('sidebar.chapse.suggestions.company.technologies'),
        message: t('sidebar.chapse.suggestions.company.technologies'),
      },
    ]
  }

  if (routeName?.startsWith('/folders/[folderId]') && !routeName?.includes('/companies/')) {
    return [
      {
        label: t('sidebar.chapse.suggestions.folder.summarize'),
        message: t('sidebar.chapse.suggestions.folder.summarize'),
      },
      {
        label: t('sidebar.chapse.suggestions.folder.compare'),
        message: t('sidebar.chapse.suggestions.folder.compare'),
      },
    ]
  }

  return []
})

// Handlers
async function handleSendMessage(message: string) {
  if (!message.trim() || !canUseChapse.value) return
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

const handleDeleteConversation = async (conversationId: string) => {
  if (confirm(t('sidebar.chapse.confirmDeleteConversation'))) {
    await deleteConversation(conversationId)
  }
}

function handleNewConversation() {
  startNewConversation()
}

async function handleLoadMoreConversations() {
  await loadConversations(false)
}

const handleClearHistory = () => {
  if (confirm(t('sidebar.chapse.confirmClearMessages'))) {
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
watch(error, (newError) => {
  if (newError) {
    toast.error(newError)
    // Clear error from store after showing toast
    chapseStore.setError(null)
  }
})

// Watch for pending assist action and process it
watch(
  () => props.pendingAssistAction,
  async (actionData) => {
    if (actionData) {
      console.log('🎯 ChapseSidebar: Received pending assist action', actionData)
      await processAssistAction(actionData)
      // Notify parent that action has been processed
      emit('assistActionProcessed')
    }
  },
  { immediate: true },
)

// Auto-attach/replace/remove company context based on current page
// This provides better UX by automatically managing context based on navigation
watch(
  availablePageContext,
  (newContext, oldContext) => {
    // Only act if conversation is new with no messages
    if (!chapseStore.isNewConversation || chapseStore.hasMessages) {
      return
    }

    // Case 1: Leaving a company page (going to non-company page)
    // Remove the auto-attached context if it was the only one
    if (!newContext && oldContext) {
      if (companyContext.value.length === 1 && companyContext.value[0].id === oldContext.id) {
        console.log('🔗 Removing auto-attached context (left company page):', oldContext.name)
        removeCompanyFromContext(oldContext.id)
      }
      return
    }

    // Case 2: No new context to attach
    if (!newContext) {
      return
    }

    // Case 3: Switching from one company to another
    if (oldContext && newContext.id !== oldContext.id) {
      console.log('🔄 Switching company context from', oldContext.name, 'to', newContext.name)

      // Remove old context if it was auto-attached (only one in context)
      if (companyContext.value.length === 1 && companyContext.value[0].id === oldContext.id) {
        removeCompanyFromContext(oldContext.id)
      }

      // Add new context if not already there
      if (!isPageContextActive.value) {
        addCompanyToContext(newContext)
      }
    } else if (!isPageContextActive.value) {
      // Case 4: Initial attachment - no old context or same company
      console.log('🔗 Auto-attaching company context:', newContext.name)
      addCompanyToContext(newContext)
    }
  },
  { immediate: true },
)

// Permission check — users without organization.read can't use Chapse
const canUseChapse = computed(() => useAuthStore().hasPermission('organization.read'))

// Load conversations on mount (only if user has access)
onMounted(async () => {
  if (canUseChapse.value) {
    await loadConversations(true)
  }
})
</script>
