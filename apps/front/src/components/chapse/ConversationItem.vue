<template>
  <div
    class="group text-sage-900 inset-ring-sage-300 flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 inset-ring transition-colors"
    :class="[
      isActive
        ? 'dark:bg-sage-700 bg-sage-50 dark:text-white'
        : 'dark:hover:bg-sage-800/50 dark:text-sage-200 bg-white hover:inset-ring-2',
    ]"
    @click="$emit('select', conversation.id)"
  >
    <Badge icon="fa-comment" fill size="sm" />

    <!-- Conversation Info -->
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium">
        {{ conversation.name || $t('screen.chapse.untitledConversation') }}
      </p>
      <p v-if="showDate" class="text-sage-800 dark:text-sage-400 truncate text-xs">
        {{ formattedDate }}
      </p>
    </div>

    <!-- Company Context Indicator -->
    <Badge
      v-if="conversation.companies && conversation.companies.length > 0"
      :number="String(conversation.companies.length)"
      fill
      size="sm"
    />

    <!-- Delete Button (visible on hover) -->
    <button
      v-if="deletable"
      class="bg-error-800 dark:hover:bg-error/20 dark:hover:text-error flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white opacity-0 transition-all group-hover:opacity-100"
      :title="$t('screen.chapse.deleteConversation')"
      @click.stop="$emit('delete', conversation.id)"
    >
      <i class="fa fa-trash text-xs"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import type { ChapseConversation } from '@/api/chapse'
import { Badge } from '@owlint/feathers-vue'
import { computed } from 'vue'

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
</script>
