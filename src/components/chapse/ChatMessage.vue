<template>
  <div
    class="flex gap-3 mb-4"
    :class="{
      'justify-start': message.role === 'assistant',
      'justify-end': message.role === 'user',
    }"
  >
    <!-- Chaps-e Avatar (left side for assistant messages) -->
    <div
      v-if="message.role === 'assistant' && isFullscreen"
      class="flex-shrink-0 w-8 h-8 rounded-full bg-sage-900 flex items-center justify-center"
    >
      <img v-if="chapseAvatar" :src="chapseAvatar" class="w-6 h-6" alt="Chaps-e" />
      <i v-else class="fa fa-robot text-secondary text-sm"></i>
    </div>

    <!-- Message Content -->
    <div class="max-w-[100%] rounded-xl px-4 py-3 text-sm" :class="messageClasses">
      <div v-if="message.role === 'assistant' && formattedContent.length === 0">
        <i class="fa fa-circle-notch fa-spin text-secondary text-sm"></i>
      </div>
      <div v-html="formattedContent"></div>

      <!-- Timestamp -->
      <div v-if="showTimestamp" class="text-[10px] mt-2 opacity-60">
        {{ formattedTime }}
      </div>
    </div>

    <!-- User Avatar placeholder (right side for user messages) -->
    <div
      v-if="message.role === 'user'"
      class="flex-shrink-0 w-8 h-8 rounded-full bg-sage-300 dark:bg-sage-300 flex items-center justify-center text-sage-950 text-xs font-semibold"
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
  // Format markdown - keep it simple for now
  let formatted = props.message.content

  // Convert ### Heading to bold heading
  formatted = formatted.replace(/^### (.+)$/gm, '<strong class="block text-base mt-1 mb-1">$1</strong>')

  // Convert --- to horizontal divider
  formatted = formatted.replace(/^---$/gm, '<hr class="border-sage-700 my-1">')

  // Convert **text** to <strong>text</strong>
  formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert *text* to <em>text</em>
  formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // Convert numbered lists (remove "1. " from content)
  formatted = formatted.replace(/\d+\.\s(.*?)(?=\n\d+\.|$)/gs, '<li>$1</li>')
  formatted = formatted.replace(/(<li>.*?<\/li>)+/gs, '<ol class="list-decimal ml-2 my-2">$&</ol>')

  // Convert bullet lists (remove "- " from content)
  formatted = formatted.replace(/- (.*?)(?=\n-|$)/gs, '<li>$1</li>')
  formatted = formatted.replace(/(<li>.*?<\/li>)+/gs, '<ul class="list-disc ml-2 my-2">$&</ul>')

  // Convert newlines to <br>
  // formatted = formatted.replace(/\n/g, '<br>')

  //convert link  [ChapsVision](https://www.chapsvision.com/about-us/). to <a href="https://www.chapsvision.com/about-us/" target="_blank">ChapsVision</a>
  formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a class="text-blue-400 underline" href="$2" target="_blank">$1</a>')

  return formatted
})

const formattedTime = computed(() => {
  const date = new Date(props.message.timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
})
</script>
