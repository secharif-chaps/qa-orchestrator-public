<template>
  <div
    class="group flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 transition-colors"
    :class="[isActive ? 'bg-sage-700 text-white' : 'hover:bg-sage-800/50 text-sage-200']"
    @click="$emit('select', conversation.id)"
  >
    <!-- Conversation Icon -->
    <div class="bg-sage-600 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full">
      <i class="fa fa-comment text-sage-200 text-xs"></i>
    </div>

    <!-- Conversation Info -->
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium">
        {{ conversation.name || $t('chapse.untitledConversation', 'New conversation') }}
      </p>
      <p v-if="showDate" class="text-sage-400 truncate text-xs">
        {{ formattedDate }}
      </p>
    </div>

    <!-- Company Context Indicator -->
    <div
      v-if="conversation.companies && conversation.companies.length > 0"
      class="flex flex-shrink-0 items-center gap-1"
    >
      <span
        class="bg-sage-600 text-sage-200 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs"
        :title="companyNames"
      >
        {{ conversation.companies.length }}
      </span>
    </div>

    <!-- Delete Button (visible on hover) -->
    <button
      v-if="deletable"
      class="hover:bg-error/20 hover:text-error flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full opacity-0 transition-all group-hover:opacity-100"
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
