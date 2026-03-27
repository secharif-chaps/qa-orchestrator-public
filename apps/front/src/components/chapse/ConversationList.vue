<template>
  <div class="flex h-full flex-col">
    <!-- Header -->
    <div
      class="border-sage-300 dark:border-sage-700 flex items-center justify-between border-b px-4 py-3.5"
    >
      <h3 class="dark:text-sage-100 text-sm font-semibold text-black">
        {{ $t('screen.chapse.conversations', 'Conversations') }}
      </h3>
      <Button
        variant="tertiary"
        icon="fa fa-plus"
        size="sm"
        :title="$t('screen.chapse.newConversation', 'New conversation')"
        @click="$emit('new-conversation')"
      />
    </div>

    <!-- Conversations List -->
    <div class="flex-1 overflow-y-auto px-2 py-2">
      <!-- Loading State -->
      <div
        v-if="loading && conversations.length === 0"
        class="flex items-center justify-center py-8"
      >
        <Icon icon="fa-spinner" class="fa-spin text-sage-900 dark:text-sage-300" />
      </div>

      <!-- Empty State -->
      <div
        v-else-if="conversations.length === 0"
        class="flex flex-col items-center justify-center px-4 py-8 text-center"
      >
        <div
          class="bg-sage-200 dark:bg-sage-700 mb-3 flex size-12 items-center justify-center rounded-full"
        >
          <Icon icon="fa-comments" class="text-sage-900 dark:text-sage-300" />
        </div>
        <p class="text-sage-900 dark:text-sage-300 text-sm">
          {{ $t('screen.chapse.noConversations', 'No conversations yet') }}
        </p>
        <p class="text-sage-700 dark:text-sage-200 mt-1 text-xs">
          {{ $t('screen.chapse.startConversation', 'Start a new conversation to begin') }}
        </p>
      </div>

      <!-- Conversations -->
      <template v-else>
        <!-- Group by date -->
        <div v-for="group in groupedConversations" :key="group.label" class="mb-4">
          <p class="text-sage-500 px-3 py-1 text-xs font-medium tracking-wide uppercase">
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
            :label="$t('common.sidebar.chapse.loadMore')"
            block
            @click="$emit('load-more')"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ChapseConversation } from '@/api/chapse'
import { Button, Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ConversationItem from './ConversationItem.vue'

const { t } = useI18n()

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
    result.push({ label: t('common.sidebar.chapse.dateGroups.today'), conversations: groups.today })
  }
  if (groups.yesterday.length > 0) {
    result.push({
      label: t('common.sidebar.chapse.dateGroups.yesterday'),
      conversations: groups.yesterday,
    })
  }
  if (groups.lastWeek.length > 0) {
    result.push({
      label: t('common.sidebar.chapse.dateGroups.lastWeek'),
      conversations: groups.lastWeek,
    })
  }
  if (groups.lastMonth.length > 0) {
    result.push({
      label: t('common.sidebar.chapse.dateGroups.lastMonth'),
      conversations: groups.lastMonth,
    })
  }
  if (groups.older.length > 0) {
    result.push({ label: t('common.sidebar.chapse.dateGroups.older'), conversations: groups.older })
  }

  return result
})
</script>
