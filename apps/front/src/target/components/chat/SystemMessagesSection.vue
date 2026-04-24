<template>
  <div class="ml-8 flex flex-col pr-12">
    <!-- Toggle older actions button (always above messages) -->
    <Button
      v-if="hasOlderMessages"
      variant="tertiary"
      size="sm"
      class="mb-2 w-fit self-center"
      :icon="showOlderMessages ? 'fa-chevron-up' : 'fa-clock-rotate-left'"
      :label="showOlderMessagesLabel"
      @click="showOlderMessages ? handleHideOlder() : handleShowOlder()"
    />

    <!-- Older messages (hidden by default) -->
    <template v-if="showOlderMessages">
      <CollapsibleSystemMessage
        v-for="(message, index) in olderMessages"
        :key="message.id"
        :message="message"
        :is-expanded="chatStore.isMessageExpanded(message.id)"
        :is-first="index === 0"
        :is-last="false"
        @toggle="handleMessageToggle(message.id)"
      />
    </template>

    <!-- Last 3 messages (always visible) -->
    <CollapsibleSystemMessage
      v-for="(message, index) in visibleMessages"
      :key="message.id"
      :message="message"
      :is-expanded="chatStore.isMessageExpanded(message.id)"
      :is-first="!showOlderMessages && index === 0"
      :is-last="index === visibleMessages.length - 1"
      @toggle="handleMessageToggle(message.id)"
    />
  </div>
</template>

<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime'
import { Button } from '@owlint/feathers-vue'
import { useChatStore } from '@target/stores/chat'
import type { Message } from '@target/types/conversation'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CollapsibleSystemMessage from './CollapsibleSystemMessage.vue'

interface Props {
  messages: Message[]
  groupId: string
}

const { messages, groupId } = defineProps<Props>()

const { t } = useI18n()
const chatStore = useChatStore()
const { formatRelativeTime } = useDateTime()

const DEFAULT_VISIBLE_COUNT = 3

const hasOlderMessages = computed(() => {
  return messages.length > DEFAULT_VISIBLE_COUNT
})

const hiddenCount = computed(() => {
  return Math.max(0, messages.length - DEFAULT_VISIBLE_COUNT)
})

const showOlderMessages = computed(() => {
  return chatStore.isOlderMessagesVisible(groupId)
})

// Messages that are always visible (last 3)
const visibleMessages = computed(() => {
  return messages.slice(-DEFAULT_VISIBLE_COUNT)
})

// Messages that are hidden by default (older messages)
const olderMessages = computed(() => {
  if (!hasOlderMessages.value) return []
  return messages.slice(0, -DEFAULT_VISIBLE_COUNT)
})

// Format time range for the group header
const groupTimeDisplay = computed(() => {
  if (olderMessages.value.length === 0) return ''

  const oldestMessage = olderMessages.value[0]
  if (!oldestMessage?.createdAt) return ''

  return formatRelativeTime(oldestMessage.createdAt)
})

const showOlderMessagesLabel = computed(() => {
  if (showOlderMessages.value) {
    return t('target.watchFiles.chat.system_messages.hide_older')
  }
  return t(
    'target.watchFiles.chat.system_messages.view_older',
    {
      count: hiddenCount.value,
      time: groupTimeDisplay.value,
    },
    hiddenCount.value,
  )
})

const handleShowOlder = () => {
  chatStore.showOlderMessages(groupId)
}

const handleHideOlder = () => {
  chatStore.hideOlderMessages(groupId)
}

const handleMessageToggle = (messageId: string) => {
  chatStore.toggleMessage(messageId)
}
</script>
