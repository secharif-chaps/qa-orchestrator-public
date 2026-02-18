<template>
  <div
    class="chat-message message-animation mb-4 flex w-full"
    :class="{
      ['justify-end pl-12']: isUserMessage,
      ['justify-start pr-12']: !isUserMessage,
    }"
    :data-testid="messageTestId"
  >
    <!-- SYSTEM ERROR MESSAGE -->
    <ErrorMessage v-if="isSystemErrorMessage" width="full" :fill="false" :is-chat-message="true" />

    <!-- SYSTEM MESSAGE (when not grouped - handled by parent for grouping) -->
    <CollapsibleSystemMessage
      v-else-if="isSystemMessage"
      :message="message"
      :is-expanded="chatStore.isMessageExpanded(message.id)"
      class="ml-8"
      @toggle="handleSystemMessageToggle"
    />

    <!-- REGULAR MESSAGES (user & chatbot) -->
    <div
      v-else
      class="flex w-full flex-col gap-1"
      :class="{
        'items-end': isUserMessage,
        'items-start': !isUserMessage,
      }"
    >
      <div class="flex items-end gap-2">
        <!-- AVATAR (chatbot) -->
        <img v-if="!isUserMessage" :src="chapse_head" alt="Chapse" class="size-6 shrink-0" />

        <!-- MESSAGE BUBBLE -->
        <div
          class="w-max max-w-[33vw] overflow-x-auto rounded-sm border px-3 pt-3 pb-2 lg:max-w-[28vw] 2xl:max-w-[24vw]"
          :class="{
            'bg-primary-lighter border-primary-stroke': isUserMessage,
            'border-gray-200 bg-white': !isUserMessage,
          }"
        >
          <!-- LOADING STATE -->
          <div v-if="isLoading" class="animate-pulse" role="progressbar" aria-busy="true">
            <div class="mb-2 h-4 w-3/4 rounded bg-gray-300" />
            <div class="h-4 w-1/2 rounded bg-gray-300" />
          </div>

          <!-- MESSAGE CONTENT -->
          <template v-else>
            <div
              v-sanitize-html="renderedContent"
              class="markdown-content text-sm whitespace-normal"
            />

            <!-- ERROR STATUS INDICATOR -->
            <div v-if="isError" class="text-error-700 mt-1 flex items-center gap-1 text-xs">
              <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
              <span>{{ t('watch_files.chat.message.send_failed') }}</span>
            </div>

            <!-- DATE -->
            <OPopper
              v-if="message.createdAt"
              placement="top"
              class="block"
              :class="{
                'ml-auto': isUserMessage,
              }"
            >
              <template #tooltip>
                {{ fullDateTime }}
              </template>
              <span
                class="mt-1 block text-right text-[10px]"
                :class="{
                  'opacity-60': isUserMessage,
                  'text-gray-600': !isUserMessage,
                }"
              >
                {{ contextualDate }}
              </span>
            </OPopper>
          </template>
        </div>

        <!-- AVATAR (user) -->
        <div v-if="isUserMessage && !isLoading" class="size-6 shrink-0">
          <Badge
            v-if="message.createdBy?.defaultThumbnail"
            :number="message.createdBy.defaultThumbnail"
            size="sm"
            variant="secondary"
          />
        </div>
      </div>

      <!-- RETRY BUTTON for error messages -->
      <div v-if="isError && canRetry" class="flex justify-end">
        <Button
          variant="tertiary"
          size="sm"
          icon="fa-rotate-right"
          :loading="isRetrying"
          :disabled="isRetrying"
          @click="handleRetry"
        >
          {{ t('common.button.retry') }}
        </Button>
      </div>

      <!-- MAX RETRIES REACHED message -->
      <div v-if="isError && !canRetry" class="text-error text-xs">
        {{ t('watch_files.chat.message.max_retries_reached') }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Badge, Button, OPopper } from '@owlint/feathers-vue'
import { useRetryMessage } from '@target/api/mutations/conversation'
import chapse_head from '@target/assets/images/chapse_head.svg'
import { useChatDateDisplay } from '@target/composables/useChatDateDisplay'
import { useMarkdown } from '@target/composables/useMarkdown'
import { useStringUtils } from '@target/composables/useStringUtils'
import { useChatStore } from '@target/stores/chat'
import { useConversationStore } from '@target/stores/conversation'
import type { Message } from '@target/types/conversation'
import { MessageRole } from '@target/types/conversation'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ErrorMessage from '../global/ErrorMessage.vue'
import CollapsibleSystemMessage from './CollapsibleSystemMessage.vue'

const MAX_RETRY_ATTEMPTS = 3

interface Props {
  message: Message
}

const { message } = defineProps<Props>()

const { t } = useI18n()
const { unescapeString } = useStringUtils()
const { toHtml } = useMarkdown({
  gfm: true,
  breaks: false,
})
const chatStore = useChatStore()
const conversationStore = useConversationStore()
const { getContextualDate, getFullDateTime } = useChatDateDisplay()

const { retryMessage, isLoading: isRetrying } = useRetryMessage()

// Extract text content from message contents
const messageContent = computed(() => {
  if (!message.contents || !Array.isArray(message.contents) || message.contents.length === 0) {
    return ''
  }
  const content = message.contents[0] as { content: string }

  return unescapeString(content.content || '')
})

const renderedContent = toHtml(messageContent)

// Computed properties for message display
const isUserMessage = computed(() => message.role === MessageRole.USER)
const isSystemMessage = computed(() => message.role === MessageRole.SYSTEM)
const isSystemErrorMessage = computed(() => message.role === MessageRole.SYSTEM_ERROR)
const isLoading = computed(() => message.loading === true)
const isError = computed(() => message.status === 'error')
const canRetry = computed(() => (message.retryCount ?? 0) < MAX_RETRY_ATTEMPTS)

const contextualDate = computed(() => {
  if (!message.createdAt) return ''
  return getContextualDate(message.createdAt).value
})

const fullDateTime = computed(() => {
  if (!message.createdAt) return ''
  return getFullDateTime(message.createdAt).value
})

// Test ID for E2E testing - maps role to test identifier
const messageTestId = computed(() => `message-${message.role}`)

// Handle system message toggle
const handleSystemMessageToggle = () => {
  chatStore.toggleMessage(message.id)
}

// Handle retry action
const handleRetry = () => {
  if (isRetrying.value || !canRetry.value) {
    return
  }

  const conversationId = conversationStore.currentConversation?.id
  if (!conversationId) {
    console.error('No conversation ID available for retry')
    return
  }

  retryMessage({ message, conversationId })
}
</script>

<style scoped>
/* Custom purple color for user messages */

/* Message slide-in animation for new messages */
.message-animation {
  animation: messageSlideIn 0.3s ease-out;
}

@keyframes messageSlideIn {
  0% {
    opacity: 0;
    transform: translateY(20px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Loading animation for AI messages */
.message-animation:has(.animate-pulse) {
  animation:
    messageSlideIn 0.3s ease-out,
    loadingPulse 2s ease-in-out infinite;
}

@keyframes loadingPulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

@keyframes typingBounce {
  0%,
  80%,
  100% {
    transform: scale(0.8);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
