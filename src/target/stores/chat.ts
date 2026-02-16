import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useChatStore = defineStore('chat', () => {
  // State: Currently expanded message ID (only one can be expanded at a time - accordion behavior)
  const expandedMessageId = ref<string | null>(null);

  // State: Groups where "View X older" was clicked to show hidden messages
  const visibleOlderGroups = ref<Set<string>>(new Set());

  // Action: Toggle message expand/collapse (accordion behavior - closes others)
  const toggleMessage = (messageId: string) => {
    expandedMessageId.value =
      expandedMessageId.value === messageId ? null : messageId;
  };

  // Action: Show older messages for a specific group
  const showOlderMessages = (groupId: string) => {
    visibleOlderGroups.value = new Set([...visibleOlderGroups.value, groupId]);
  };

  // Action: Hide older messages for a specific group
  const hideOlderMessages = (groupId: string) => {
    const newSet = new Set(visibleOlderGroups.value);
    newSet.delete(groupId);
    visibleOlderGroups.value = newSet;
  };

  // Computed: Check if a specific message is expanded
  const isMessageExpanded = computed(() => {
    return (messageId: string): boolean => {
      return expandedMessageId.value === messageId;
    };
  });

  // Computed: Check if older messages are visible for a specific group
  const isOlderMessagesVisible = computed(() => {
    return (groupId: string): boolean => {
      return visibleOlderGroups.value.has(groupId);
    };
  });

  // Action: Reset all state to initial values
  const $reset = () => {
    expandedMessageId.value = null;
    visibleOlderGroups.value = new Set();
  };

  return {
    // State
    expandedMessageId,
    visibleOlderGroups,

    // Actions
    toggleMessage,
    showOlderMessages,
    hideOlderMessages,
    $reset,

    // Computed helpers
    isMessageExpanded,
    isOlderMessagesVisible,
  };
});
