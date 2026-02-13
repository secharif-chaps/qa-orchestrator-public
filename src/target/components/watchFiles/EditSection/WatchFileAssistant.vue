<template>
  <div class="flex h-full flex-col bg-slate-50">
    <ConnectionBanner />
    <div class="min-h-0 flex-1">
      <div
        v-if="isLoading || isLoadingConversation"
        class="flex h-full items-center justify-center"
      >
        <div class="text-center">
          <div
            class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"
          ></div>
          <p class="text-gray-600">
            {{ $t('watch_files.chat.loading_messages') }}
          </p>
        </div>
      </div>

      <AssistantEmptyView v-else-if="shouldShowEmptyView" />

      <ConversationView
        v-else
        ref="conversationViewRef"
        :is-loading="
          (isLoadingConversationMessages || isLoadingLastConversation) &&
          !conversationStore.messages.length
        "
        :on-load-older-messages="loadOlderMessages"
        :show-reassurance="showReassurance"
      />
    </div>

    <Transition :name="hasInitialized ? 'chat-slide' : ''" mode="out-in">
      <div
        v-if="!shouldHideChat && (hasInitialized || !conversation)"
        key="chat-input"
        class="shrink-0 border-gray-200 bg-slate-50 p-2"
      >
        <ChatInput
          ref="chatInputRef"
          :loading="isLoading || isLoadingAddMessage"
          :disabled="isWaitingForAI || isLoadingCreate"
          :placeholder="placeholderInput"
          @send="handleSendMessage"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { useQuery } from '@pinia/colada';
import { storeToRefs } from 'pinia';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  useAddMessage,
  useGetOlderConversationMessages,
} from '~/api/mutations/conversation';
import { useCreateWatchFile } from '~/api/mutations/watchFile';
import {
  getConversationMessagesQuery,
  getLastConversationQuery,
} from '~/api/queries/conversation';
import ChatInput from '~/components/chat/ChatInput.vue';
import AssistantEmptyView from '~/components/watchFiles/EditSection/AssistantEmptyView.vue';
import ConnectionBanner from '~/components/watchFiles/EditSection/ConnectionBanner.vue';
import ConversationView from '~/components/watchFiles/EditSection/ConversationView.vue';
import { useConversationTimeout } from '~/composables/useConversationTimeout';
import { useChatStore } from '~/stores/chat';
import { useConversationStore } from '~/stores/conversation';
import type { Conversation, Message } from '~/types/conversation';
import type { WatchFileStatus } from '~/types/watchFile';

const { t } = useI18n();

interface Props {
  conversation?: Conversation | null;
  isLoading?: boolean;
  watchFileId?: string;
  watchFileStatus?: WatchFileStatus | null;
  isReadOnly?: boolean;
}

const {
  conversation = null,
  isLoading,
  watchFileId = '',
  watchFileStatus = null,
  isReadOnly,
} = defineProps<Props>();

const emit = defineEmits<{
  watchFileCreated: [id: string];
}>();

// Initialize stores
const chatStore = useChatStore();
const conversationStore = useConversationStore();
const { isWaitingForAI } = storeToRefs(conversationStore);

// Initialize timeout tracking for showing reassurance message after long waits
const { showReassurance } = useConversationTimeout();

// Local UI state
const hasInitialized = ref(false);
const isLoadingConversation = ref(false);
const conversationViewRef = ref<InstanceType<typeof ConversationView> | null>(
  null,
);

const shouldShowEmptyView = computed(() => {
  const isNotLoading =
    !isLoading &&
    !isLoadingConversation.value &&
    !isLoadingConversationMessages.value &&
    !isLoadingLastConversation.value;
  const hasNoMessages = conversationStore.messages.length === 0;
  const hasNoWatchFileId = !watchFileId;

  return isNotLoading && hasNoWatchFileId && hasNoMessages;
});

const shouldHideChat = computed(() => {
  return isReadOnly;
});

watch(
  () => watchFileStatus,
  (_newStatus, oldStatus) => {
    if (isReadOnly) {
      conversationStore.setWaitingForAI(false);
    }
    if (oldStatus === null || oldStatus === undefined) {
      hasInitialized.value = true;
    }
  },
);

watch(shouldHideChat, (isHidden) => {
  if (!isHidden) {
    conversationViewRef.value?.scrollToBottom();
  }
});

// Reset stores when watchFileId changes (switching between watchFiles)
watch(
  () => watchFileId,
  () => {
    chatStore.$reset();
    conversationStore.$reset();
  },
);

// Initialize with real data or props
onMounted(async () => {
  if (conversation) {
    conversationStore.setCurrentConversation(conversation);
    conversationStore.setMessages(conversation.messages || []);
  }
});

// Reset stores when component unmounts
onUnmounted(() => {
  chatStore.$reset();
  conversationStore.$reset();
});

const { data: dataLastConversation, isLoading: isLoadingLastConversation } =
  useQuery(getLastConversationQuery, () => ({
    watchFileId,
    onUpdate: (updatedConversation) => {
      conversationStore.setCurrentConversation(updatedConversation);
    },
  }));
const lastConversation = computed(() => dataLastConversation.value);

const {
  data: dataConversationMessages,
  isLoading: isLoadingConversationMessages,
} = useQuery(getConversationMessagesQuery, () => ({
  conversationId: lastConversation.value?.id ?? '',
  onUpdate: (message) => {
    if (conversationStore.addOrUpdateMessage(message) !== 'unchanged') {
      conversationViewRef.value?.scrollToBottom();
    }
  },
}));

const hasInitialMessagesLoaded = ref(false);

watch(
  dataConversationMessages,
  (newData) => {
    if (!newData) return;

    conversationStore.setMessages(newData.items);
    conversationStore.setNextMessagesUrl(newData.nextUrl ?? null);

    if (lastConversation.value) {
      conversationStore.setCurrentConversation({
        ...lastConversation.value,
        messages: conversationStore.messages,
      });
    }

    // Always scroll on initial load, then only when not waiting for AI
    if (!hasInitialMessagesLoaded.value || !isWaitingForAI.value) {
      hasInitialMessagesLoaded.value = true;
      nextTick(() => {
        conversationViewRef.value?.scrollToBottom(true);
      });
    }
  },
  { immediate: true },
);

const { mutation: getOlderConversationMessagesMutation } =
  useGetOlderConversationMessages();

// Load older messages for infinite scroll
const loadOlderMessages = async (): Promise<Message[]> => {
  if (!conversationStore.nextMessagesUrl) {
    return [];
  }
  try {
    const olderMessagesCollection = await getOlderConversationMessagesMutation(
      conversationStore.nextMessagesUrl,
    );

    const olderMessages = olderMessagesCollection.items;
    conversationStore.setNextMessagesUrl(
      olderMessagesCollection.nextUrl ?? null,
    );

    // Use store's prepend with built-in deduplication
    if (olderMessages.length > 0) {
      const addedCount = conversationStore.prependMessages(olderMessages);

      if (addedCount > 0) {
        await nextTick();
      }
    }
    return olderMessages;
  } catch (error) {
    console.error('Failed to load older messages:', error);
    return [];
  }
};

const placeholderInput = computed<string>(() => {
  if (isWaitingForAI.value) {
    return t('watch_files.chat.input.placeholder_processing');
  }

  // Check if there are messages in the conversation
  const hasMessages = conversationStore.messages.length > 0;

  if (hasMessages) {
    return t('watch_files.chat.input.placeholder');
  } else {
    return t('watch_files.chat.input.placeholder_not_started_conversation');
  }
});

const { createWatchFile, isLoading: isLoadingCreate } = useCreateWatchFile({
  onSuccess(data) {
    if (data.id) {
      // Emit event to parent to handle state update and silent URL navigation
      emit('watchFileCreated', data.id);
    }
  },
});

const { addMessage, isLoading: isLoadingAddMessage } = useAddMessage();

const handleSendMessage = async (message: string) => {
  if (shouldHideChat.value) {
    return;
  }

  // If no watchfile ID is provided, create a new watchfile with conversation
  if (!watchFileId) {
    // For new watchfile creation, we don't have a conversation yet
    // So just show the typing indicator
    conversationStore.setWaitingForAI(true);
    await createWatchFile(message);
  } else {
    if (!conversationStore.currentConversation) {
      console.error('No conversation available to add message to');
      return;
    }

    // Send message to API (mutation's onMutate handles optimistic message)
    addMessage({
      conversationId: conversationStore.currentConversation.id,
      message,
    });

    // Show typing indicator after user message
    conversationStore.setWaitingForAI(true);

    // Scroll to show the new message (scrollToBottom already handles nextTick internally)
    // conversationViewRef.value?.scrollToBottom();
  }
};
</script>

<style scoped>
/* Chat slide animation */
.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: all 0.5s ease-in-out;
  transform-origin: bottom;
}

.chat-slide-enter-from {
  opacity: 0;
  transform: translateY(100%) scaleY(0.8);
}

.chat-slide-enter-to {
  opacity: 1;
  transform: translateY(0) scaleY(1);
}

.chat-slide-leave-from {
  opacity: 1;
  transform: translateY(0) scaleY(1);
}

.chat-slide-leave-to {
  opacity: 0;
  transform: translateY(100%) scaleY(0.8);
}
</style>
