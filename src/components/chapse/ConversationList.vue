<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-sage-700">
      <h3 class="text-sm font-semibold text-sage-100">
        {{ $t('chapse.conversations', 'Conversations') }}
      </h3>
      <Button
        variant="tertiary"
        icon="fa fa-plus"
        size="sm"
        :title="$t('chapse.newConversation', 'New conversation')"
        @click="$emit('new-conversation')"
      />
    </div>

    <!-- Conversations List -->
    <div class="flex-1 overflow-y-auto px-2 py-2">
      <!-- Loading State -->
      <div v-if="loading && conversations.length === 0" class="flex items-center justify-center py-8">
        <i class="fa fa-spinner fa-spin text-sage-400"></i>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="conversations.length === 0"
        class="flex flex-col items-center justify-center py-8 px-4 text-center"
      >
        <div class="w-12 h-12 rounded-full bg-sage-700 flex items-center justify-center mb-3">
          <i class="fa fa-comments text-sage-400"></i>
        </div>
        <p class="text-sm text-sage-400">
          {{ $t('chapse.noConversations', 'No conversations yet') }}
        </p>
        <p class="text-xs text-sage-500 mt-1">
          {{ $t('chapse.startConversation', 'Start a new conversation to begin') }}
        </p>
      </div>

      <!-- Conversations -->
      <template v-else>
        <!-- Group by date -->
        <div v-for="group in groupedConversations" :key="group.label" class="mb-4">
          <p class="text-xs font-medium text-sage-500 px-3 py-1 uppercase tracking-wide">
            {{ group.label }}
          </p>
          <ConversationItem
            v-for="conversation in group.conversations"
            :key="conversation.id"
            :conversation="conversation"
            :is-active="conversation.id === currentConversationId"
            @select="$emit('select', $event)"
            @delete="$emit('delete', $event)"
          />
        </div>

        <!-- Load More -->
        <div v-if="hasMore" class="px-3 py-2">
          <Button
            variant="tertiary"
            size="sm"
            :loading="loading"
            label="Load more"
            block
            @click="$emit('load-more')"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ChapseConversation } from '@/api/chapse'
import ConversationItem from './ConversationItem.vue'
import { Button } from '@owlint/feathers-vue'

interface Props {
  conversations: ChapseConversation[]
  currentConversationId?: string | null
  loading?: boolean
  hasMore?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  currentConversationId: null,
  loading: false,
  hasMore: false,
})

defineEmits<{
  select: [conversationId: string]
  delete: [conversationId: string]
  'new-conversation': []
  'load-more': []
}>()

interface ConversationGroup {
  label: string
  conversations: ChapseConversation[]
}

const groupedConversations = computed<ConversationGroup[]>(() => {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)
  const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
  const lastMonth = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)

  const groups: Record<string, ChapseConversation[]> = {
    today: [],
    yesterday: [],
    lastWeek: [],
    lastMonth: [],
    older: [],
  }

  for (const conv of props.conversations) {
    const convDate = new Date(conv.updated_at * 1000)

    if (convDate >= today) {
      groups.today.push(conv)
    } else if (convDate >= yesterday) {
      groups.yesterday.push(conv)
    } else if (convDate >= lastWeek) {
      groups.lastWeek.push(conv)
    } else if (convDate >= lastMonth) {
      groups.lastMonth.push(conv)
    } else {
      groups.older.push(conv)
    }
  }

  const result: ConversationGroup[] = []

  if (groups.today.length > 0) {
    result.push({ label: 'Today', conversations: groups.today })
  }
  if (groups.yesterday.length > 0) {
    result.push({ label: 'Yesterday', conversations: groups.yesterday })
  }
  if (groups.lastWeek.length > 0) {
    result.push({ label: 'Last 7 days', conversations: groups.lastWeek })
  }
  if (groups.lastMonth.length > 0) {
    result.push({ label: 'Last 30 days', conversations: groups.lastMonth })
  }
  if (groups.older.length > 0) {
    result.push({ label: 'Older', conversations: groups.older })
  }

  return result
})
</script>
