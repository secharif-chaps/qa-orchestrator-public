<template>
  <div
    class="mb-4 flex gap-3"
    :class="{
      'items-start justify-start': message.role === 'assistant',
      'items-end justify-end': message.role === 'user',
    }"
  >
    <!-- Chaps-e Avatar (left side for assistant messages) -->
    <div
      v-if="message.role === 'assistant' && isFullscreen"
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
    >
      <img v-if="chapseAvatar" :src="chapseAvatar" class="h-6 w-6" alt="Chaps-e" />
      <Icon icon="fa-robot" v-else class="text-neutral-black-font text-sm" />
    </div>

    <!-- Smart Action Message (special styling) -->
    <div
      v-if="message.isSmartAction"
      class="bg-accent-200 text-accent-800 flex max-w-full items-center gap-2 rounded-md px-4 py-3 text-sm font-medium"
    >
      <Icon :icon="message.smartActionIcon || 'fa-wand-magic-sparkles'" class="text-base" />
      <span>{{ message.smartActionLabel }}</span>
    </div>

    <!-- Regular Message Content -->
    <div v-else class="max-w-full rounded-md text-sm" :class="messageClasses">
      <div v-if="message.role === 'assistant' && formattedContent.length === 0">
        <Icon icon="fa-circle-notch" class="fa-spin text-neutral-black-font text-sm" />
      </div>
      <div v-sanitize-html="formattedContent"></div>

      <!-- Timestamp -->
      <div v-if="showTimestamp" class="mt-2 text-[10px] opacity-60">
        {{ formattedTime }}
      </div>
    </div>

    <!-- User Avatar placeholder (right side for user messages) -->
    <Avatar v-if="message.role === 'user'" :label="authStore.username" color="almond" size="sm" />
  </div>
</template>

<script setup lang="ts">
import chapseHead from '@/assets/chapse/head.svg'
import { useAuthStore } from '@/stores/auth'
import type { ChatMessage as ChatMessageType } from '@/stores/chapse'
import { useSidebarStore } from '@/stores/sidebar'
import { Avatar, Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

const authStore = useAuthStore()

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
    return 'text-sage-950 dark:text-sage-50'
  } else {
    return 'bg-white text-sage-950 dark:bg-sage-700 dark:text-sage-100 p-4'
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
