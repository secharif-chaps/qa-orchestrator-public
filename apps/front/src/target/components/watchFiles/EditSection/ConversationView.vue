<template>
  <div class="flex h-full flex-col bg-slate-50">
    <div
      ref="messagesContainer"
      class="relative flex-1 space-y-4 overflow-y-auto p-2"
      data-testid="messages-container"
      @scroll="handleScroll"
    >
      <div
        v-if="hasMoreMessages"
        ref="loadMoreSentinel"
        class="h-1 w-full"
        data-testid="load-more-sentinel"
      />
      <div v-else class="invisible h-1 w-full" />

      <div
        v-if="isLoadingOlderMessages"
        class="flex h-12 items-center justify-center"
        role="status"
      >
        <div class="text-center">
          <Icon icon="fa-spinner-third" class="text-primary-600 animate-spin text-3xl" />
          <p class="text-sm text-gray-600">
            {{ t('watch_files.chat.loading_older_messages') }}
          </p>
        </div>
      </div>
      <div v-else class="invisible h-12 w-full" />

      <template v-if="!isLoading">
        <template v-for="item in groupedMessages" :key="item.key">
          <!-- Grouped system messages (3+) -->
          <SystemMessagesSection
            v-if="item.type === 'system-group'"
            :messages="item.messages"
            :group-id="item.groupId"
          />
          <!-- Individual messages -->
          <ChatMessageComponent v-else :message="item.message" :data-message-id="item.message.id" />
        </template>
      </template>

      <div v-else class="flex h-full items-center justify-center" role="status">
        <div class="text-center">
          <div
            class="border-primary-600 mx-auto mb-2 h-8 w-8 animate-spin rounded-full border-b-2"
          ></div>
          <p class="text-sm text-gray-500">
            {{ t('watch_files.chat.loading_messages') }}
          </p>
        </div>
      </div>

      <div
        v-if="!isLoading && (!displayMessages || displayMessages.length === 0)"
        class="flex h-full items-center justify-center"
      >
        <div class="text-center">
          <p class="text-gray-500">{{ t('watch_files.chat.no_messages') }}</p>
        </div>
      </div>

      <!-- Typing indicator -->
      <ChatTypingIndicator v-if="isWaitingForAI" :show-reassurance="showReassurance" />

      <!-- New message notification -->
      <div v-if="showNewMessageNotification" class="sticky bottom-4 z-10 flex justify-center">
        <Button
          variant="accent"
          size="sm"
          class="cursor-pointer shadow-lg"
          @click="handleNewMessageClick"
        >
          {{ t('watch_files.chat.new_message') }}
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Button, Icon } from '@owlint/feathers-vue'
import ChatMessageComponent from '@target/components/chat/ChatMessage.vue'
import ChatTypingIndicator from '@target/components/chat/ChatTypingIndicator.vue'
import SystemMessagesSection from '@target/components/chat/SystemMessagesSection.vue'
import { useConversationStore } from '@target/stores/conversation'
import type { Message } from '@target/types/conversation'
import { MessageRole } from '@target/types/conversation'
import { computed, nextTick, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const conversationStore = useConversationStore()

interface Props {
  isLoading?: boolean
  onLoadOlderMessages?: () => Promise<Message[]>
  showReassurance?: boolean
}

const { onLoadOlderMessages = undefined, showReassurance = false } = defineProps<Props>()

const isWaitingForAI = computed(() => conversationStore.isWaitingForAI)
const displayMessages = computed<Message[]>(() => conversationStore.messages)
const hasMoreMessages = computed(() => conversationStore.hasMoreMessages)

const messagesContainerRef = useTemplateRef('messagesContainer')
const loadMoreSentinelRef = useTemplateRef('loadMoreSentinel')

const isLoadingOlderMessages = ref(false)
let intersectionObserver: IntersectionObserver | null = null
let scrollToBottomTimeout: ReturnType<typeof setTimeout> | null = null
const userHasScrolledUp = ref(false)
const showNewMessageNotification = ref(false)
const lastMessageId = ref<string | null>(null)

const isInitialScrollComplete = ref(false)

// Type definitions for grouped messages
interface MessageItem {
  type: 'message'
  key: string
  message: Message
}

interface SystemGroupItem {
  type: 'system-group'
  key: string
  messages: Message[]
  groupId: string
}

type GroupedMessageItem = MessageItem | SystemGroupItem

// Minimum number of consecutive system messages required to form a group
const MIN_SYSTEM_GROUP_SIZE = 2

// Group consecutive system messages (3+) together
const groupedMessages = computed<GroupedMessageItem[]>(() => {
  const groups: GroupedMessageItem[] = []
  let currentSystemGroup: Message[] = []

  const flushSystemGroup = () => {
    if (currentSystemGroup.length >= MIN_SYSTEM_GROUP_SIZE) {
      // Create a grouped system section
      groups.push({
        type: 'system-group',
        key: `system-group-${currentSystemGroup[0]!.id}`,
        messages: currentSystemGroup,
        groupId: currentSystemGroup[0]!.id,
      })
    } else {
      // Add individually if less than MIN_SYSTEM_GROUP_SIZE
      for (const msg of currentSystemGroup) {
        groups.push({
          type: 'message',
          key: msg.id,
          message: msg,
        })
      }
    }
    currentSystemGroup = []
  }

  for (const message of displayMessages.value) {
    // Only group non-loading system messages (exclude system_error)
    if (message.role === MessageRole.SYSTEM && !message.loading) {
      currentSystemGroup.push(message)
    } else {
      // Flush any accumulated system messages
      flushSystemGroup()
      // Add the current non-system message
      groups.push({
        type: 'message',
        key: message.id,
        message,
      })
    }
  }

  // Handle trailing system group
  flushSystemGroup()

  return groups
})

const SCROLL_DEBOUNCE_MS = 100
const SCROLL_BOTTOM_THRESHOLD = 100
const SCROLL_TOP_THRESHOLD = 50

const loadOlderMessages = async () => {
  // Skip loading during initial scroll to bottom
  if (!isInitialScrollComplete.value) {
    return
  }

  if (!onLoadOlderMessages || isLoadingOlderMessages.value || !conversationStore.hasMoreMessages) {
    return
  }

  isLoadingOlderMessages.value = true

  const container = messagesContainerRef.value
  if (!container) {
    isLoadingOlderMessages.value = false
    return
  }

  // Save current scroll position
  const oldScrollHeight = container.scrollHeight
  const oldScrollTop = container.scrollTop

  try {
    await onLoadOlderMessages()
    await nextTick()

    if (container) {
      const newScrollHeight = container.scrollHeight
      const heightDifference = newScrollHeight - oldScrollHeight
      container.scrollTop = oldScrollTop + heightDifference - SCROLL_TOP_THRESHOLD
    }
  } catch (error) {
    console.error('Failed to load older messages:', error)
  } finally {
    isLoadingOlderMessages.value = false
  }
}

const setupIntersectionObserver = () => {
  // Cleanup any existing observer first
  cleanupIntersectionObserver()

  const sentinel = loadMoreSentinelRef.value
  const container = messagesContainerRef.value

  if (!sentinel || !container) {
    return
  }

  intersectionObserver = new IntersectionObserver(
    async (entries) => {
      const entry = entries[0]

      // Only load if intersecting and not already loading
      if (entry?.isIntersecting && !isLoadingOlderMessages.value) {
        await loadOlderMessages()
      }
    },
    {
      root: container,
      rootMargin: '50px 0px',
      threshold: 0,
    },
  )

  intersectionObserver.observe(sentinel)

  // Check if sentinel is already visible (user is already at top)
  // This handles the case where the user is already scrolled to the top
  // when the observer is set up
  nextTick(() => {
    if (sentinel && container) {
      const rect = sentinel.getBoundingClientRect()
      const containerRect = container.getBoundingClientRect()
      const isVisible =
        rect.top >= containerRect.top &&
        rect.top <= containerRect.bottom &&
        !isLoadingOlderMessages.value &&
        isInitialScrollComplete.value

      if (isVisible && conversationStore.hasMoreMessages && onLoadOlderMessages) {
        loadOlderMessages()
      }
    }
  })
}

const cleanupIntersectionObserver = () => {
  if (intersectionObserver) {
    intersectionObserver.disconnect()
    intersectionObserver = null
  }
}

const isNearBottom = (): boolean => {
  const container = messagesContainerRef.value
  if (!container) return true

  const { scrollTop, scrollHeight, clientHeight } = container
  return scrollHeight - scrollTop - clientHeight < SCROLL_BOTTOM_THRESHOLD
}

const isNearTop = (): boolean => {
  const container = messagesContainerRef.value
  if (!container) return false

  const { scrollTop } = container
  return scrollTop < SCROLL_TOP_THRESHOLD
}

const handleScroll = () => {
  const wasAtBottom = !userHasScrolledUp.value
  userHasScrolledUp.value = !isNearBottom()

  if (isNearTop() && isInitialScrollComplete.value) {
    // Only trigger if we have more messages and aren't already loading
    if (conversationStore.hasMoreMessages && !isLoadingOlderMessages.value && onLoadOlderMessages) {
      loadOlderMessages()
    }
  }

  // Hide notification if user scrolls to bottom
  if (!userHasScrolledUp.value && !wasAtBottom) {
    showNewMessageNotification.value = false
    // Update last message ID when user scrolls to bottom
    if (displayMessages.value.length) {
      lastMessageId.value = displayMessages.value.at(-1)?.id ?? null
    }
  }
}

const cancelPendingScroll = () => {
  if (scrollToBottomTimeout) {
    clearTimeout(scrollToBottomTimeout)
    scrollToBottomTimeout = null
  }
}

const scrollToBottom = (force = false): boolean => {
  cancelPendingScroll()

  if (!force && userHasScrolledUp.value) {
    return false
  }

  const debounceTimeout = force ? 0 : SCROLL_DEBOUNCE_MS

  scrollToBottomTimeout = setTimeout(() => {
    if (!force && userHasScrolledUp.value) {
      return
    }

    nextTick(() => {
      const container = messagesContainerRef.value
      requestAnimationFrame(() => {
        if (container) {
          container.scrollTop = container.scrollHeight
          userHasScrolledUp.value = false
          showNewMessageNotification.value = false
          const lastMessage = displayMessages.value.at(-1)
          // Update last message ID when scrolling to bottom
          if (!displayMessages.value.length) {
            lastMessageId.value = lastMessage?.id ?? null
          }
          // Mark initial scroll as complete only when we have messages
          if (!isInitialScrollComplete.value && displayMessages.value.length) {
            isInitialScrollComplete.value = true
            // Set initial last message ID
            lastMessageId.value = lastMessage?.id ?? null
          }
        }
      })
    })
  }, debounceTimeout)

  return true
}

const isAtBottom = (): boolean => {
  return !userHasScrolledUp.value
}

const handleNewMessageClick = () => {
  scrollToBottom(true)
}

defineExpose({
  scrollToBottom,
  isAtBottom,
})

// Watch for new messages when user is scrolled up
watch(
  displayMessages,
  (newMessages) => {
    if (isLoadingOlderMessages.value) {
      return
    }

    // Only show notification if:
    // 1. User has scrolled up
    // 2. We're not in initial loading phase
    // 3. A new message was actually added at the end (not just an update)
    if (!userHasScrolledUp.value || !isInitialScrollComplete.value || newMessages.length === 0) {
      return
    }

    const lastMessage = newMessages.at(-1)
    if (!lastMessage) {
      return
    }

    if (lastMessage.role === MessageRole.USER) {
      return
    }

    // Check if this is a truly new message (different ID than last known)
    // This handles:
    // - New messages added at the end
    // - Not showing for updates to existing messages
    // - Not showing when loading older messages (prepended at the start)
    if (lastMessage.id !== lastMessageId.value) {
      showNewMessageNotification.value = true
    }
  },
  { deep: true },
)

// Re-setup intersection observer when hasMoreMessages changes
watch(hasMoreMessages, (newValue) => {
  if (newValue && isInitialScrollComplete.value) {
    nextTick(() => {
      setupIntersectionObserver()
    })
  } else if (!newValue) {
    cleanupIntersectionObserver()
  }
})

// Setup observer when initial scroll completes (if there are more messages)
watch(isInitialScrollComplete, (newValue) => {
  if (newValue && hasMoreMessages.value) {
    nextTick(() => {
      setupIntersectionObserver()
    })
  }
})

onMounted(() => {
  nextTick(() => {
    scrollToBottom(true)
    setupIntersectionObserver()
  })
})

onUnmounted(() => {
  cleanupIntersectionObserver()
  cancelPendingScroll()
})
</script>
