<template>
  <div
    class="mb-4 flex gap-3"
    :class="{
      'justify-start': message.role === 'assistant',
      'justify-end': message.role === 'user',
    }"
  >
    <!-- Chaps-e Avatar (left side for assistant messages) -->
    <div
      v-if="message.role === 'assistant' && isFullscreen"
      class="bg-sage-900 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full"
    >
      <img v-if="chapseAvatar" :src="chapseAvatar" class="h-6 w-6" alt="Chaps-e" />
      <i v-else class="fa fa-robot text-secondary text-sm"></i>
    </div>

    <!-- Smart Action Message (special styling) -->
    <div
      v-if="message.isSmartAction"
      class="bg-accent-200 text-accent-800 flex max-w-[100%] items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium"
    >
      <i
        :class="message.smartActionIcon || 'fa-solid fa-wand-magic-sparkles'"
        class="text-base"
      ></i>
      <span>{{ message.smartActionLabel }}</span>
    </div>

    <!-- Regular Message Content -->
    <div v-else class="max-w-[100%] rounded-xl px-4 py-3 text-sm" :class="messageClasses">
      <div v-if="message.role === 'assistant' && formattedContent.length === 0">
        <i class="fa fa-circle-notch fa-spin text-secondary text-sm"></i>
      </div>
      <div v-html="formattedContent"></div>

      <!-- Timestamp -->
      <div v-if="showTimestamp" class="mt-2 text-[10px] opacity-60">
        {{ formattedTime }}
      </div>
    </div>

    <!-- User Avatar placeholder (right side for user messages) -->
    <div
      v-if="message.role === 'user'"
      class="bg-sage-300 dark:bg-sage-300 text-sage-950 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold"
    >
      <i class="fa fa-user"></i>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ChatMessage as ChatMessageType } from '@/stores/chapse'
import chapseHead from '@/assets/chapse/head.svg'
import { useSidebarStore } from '@/stores/sidebar'

const props = defineProps<{
  message: ChatMessageType
  showTimestamp?: boolean
}>()

const sidebarStore = useSidebarStore()

const isFullscreen = computed(() => sidebarStore.isFullscreen)

const chapseAvatar = computed(() => {
  if (props.message.role === 'assistant') {
    return chapseHead
  }
  return null
})

const messageClasses = computed(() => {
  if (props.message.role === 'assistant') {
    return 'bg-sage-900 dark:bg-sage-900 text-base dark:text-gray-100'
  } else {
    return 'bg-sage-300 text-sage-950 dark:bg-sage-300/20 dark:text-sage-300'
  }
})

const formattedContent = computed(() => {
  // Format markdown
  let formatted = props.message.content

  // Convert headings (must be done before bold to avoid conflicts)
  // ## Heading 2
  formatted = formatted.replace(/^## (.+)$/gm, '<h2 class="text-lg font-bold mt-4 mb-2">$1</h2>')
  // ### Heading 3
  formatted = formatted.replace(/^### (.+)$/gm, '<h3 class="text-base font-bold mt-3 mb-1">$1</h3>')
  // #### Heading 4 (including patterns like "#### 1." or just "####")
  formatted = formatted.replace(/^####\s*(\d+\.?\s*)?(.*)$/gm, (_, num, text) => {
    const content = (num || '') + (text || '')
    return content.trim() ? `<h4 class="text-sm font-bold mt-3 mb-1">${content.trim()}</h4>` : ''
  })

  // Convert --- to horizontal divider
  formatted = formatted.replace(/^---$/gm, '<hr class="border-sage-700 my-2">')

  // Convert **text** to <strong>text</strong>
  formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert *text* to <em>text</em>
  formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // Convert bullet lists (- item) - must be done before numbered lists
  formatted = formatted.replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')

  // Wrap consecutive <li> elements in <ul>
  formatted = formatted.replace(
    /((?:<li class="ml-4 list-disc">.*?<\/li>\n?)+)/g,
    '<ul class="my-2">$1</ul>',
  )

  // Convert numbered lists (1. item)
  formatted = formatted.replace(/^\d+\.\s+(.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')

  // Wrap consecutive numbered <li> elements in <ol>
  formatted = formatted.replace(
    /((?:<li class="ml-4 list-decimal">.*?<\/li>\n?)+)/g,
    '<ol class="my-2">$1</ol>',
  )

  // Convert links [text](url)
  formatted = formatted.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a class="text-blue-400 underline" href="$2" target="_blank">$1</a>',
  )

  // Convert newlines to <br> for proper spacing (but not inside block elements)
  formatted = formatted.replace(/\n(?!<)/g, '<br>')

  // Clean up multiple <br> tags
  formatted = formatted.replace(/(<br>){3,}/g, '<br><br>')

  return formatted
})

const formattedTime = computed(() => {
  const date = new Date(props.message.timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
})
</script>
