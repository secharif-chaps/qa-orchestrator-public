<template>
  <div
    class="group text-sage-900 inset-ring-sage-300 flex cursor-pointer items-center gap-2 rounded-sm px-3 py-2 inset-ring transition-colors"
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
import { useChatDateRef } from '@/composables/useDateTime'
import { Badge } from '@owlint/feathers-vue'

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

const formattedDate = useChatDateRef(() => new Date(props.conversation.updated_at * 1000))
</script>
