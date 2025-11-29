<template>
  <div
    class="group flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors"
    :class="[
      isActive
        ? 'bg-sage-700 text-white'
        : 'hover:bg-sage-800/50 text-sage-200',
    ]"
    @click="$emit('select', conversation.id)"
  >
    <!-- Conversation Icon -->
    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-sage-600 flex items-center justify-center">
      <i class="fa fa-comment text-xs text-sage-200"></i>
    </div>

    <!-- Conversation Info -->
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium truncate">
        {{ conversation.name || $t('chapse.untitledConversation', 'New conversation') }}
      </p>
      <p v-if="showDate" class="text-xs text-sage-400 truncate">
        {{ formattedDate }}
      </p>
    </div>

    <!-- Company Context Indicator -->
    <div
      v-if="conversation.companies && conversation.companies.length > 0"
      class="flex-shrink-0 flex items-center gap-1"
    >
      <span
        class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-sage-600 text-xs text-sage-200"
        :title="companyNames"
      >
        {{ conversation.companies.length }}
      </span>
    </div>

    <!-- Delete Button (visible on hover) -->
    <button
      v-if="deletable"
      class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-error/20 hover:text-error transition-all"
      :title="$t('chapse.deleteConversation', 'Delete conversation')"
      @click.stop="$emit('delete', conversation.id)"
    >
      <i class="fa fa-trash text-xs"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ChapseConversation } from '@/api/chapse'

interface Props {
  conversation: ChapseConversation
  isActive?: boolean
  deletable?: boolean
  showDate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isActive: false,
  deletable: true,
  showDate: true,
})

defineEmits<{
  select: [conversationId: string]
  delete: [conversationId: string]
}>()

const formattedDate = computed(() => {
  const date = new Date(props.conversation.updated_at * 1000)
  const now = new Date()
  const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } else if (diffDays === 1) {
    return 'Yesterday'
  } else if (diffDays < 7) {
    return date.toLocaleDateString([], { weekday: 'short' })
  } else {
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' })
  }
})

const companyNames = computed(() => {
  if (!props.conversation.companies || props.conversation.companies.length === 0) {
    return ''
  }
  return props.conversation.companies.map((c) => c.name).join(', ')
})
</script>
