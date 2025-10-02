<template>
  <div
    class="flex gap-3 mb-4"
    :class="{
      'justify-start': message.role === 'assistant',
      'justify-end': message.role === 'user'
    }"
  >
    <!-- Chaps-e Avatar (left side for assistant messages) -->
    <div
      v-if="message.role === 'assistant'"
      class="flex-shrink-0 w-8 h-8 rounded-full bg-almond-300/50 flex items-center justify-center"
    >
      <img v-if="chapseAvatar" :src="chapseAvatar" class="w-6 h-6" alt="Chaps-e" />
      <i v-else class="fa fa-robot text-primary text-sm"></i>
    </div>

    <!-- Message Content -->
    <div
      class="max-w-[90%] rounded-xl px-4 py-3 text-sm"
      :class="messageClasses"
    >
      <div v-html="formattedContent"></div>

      <!-- Timestamp -->
      <div
        v-if="showTimestamp"
        class="text-[10px] mt-2 opacity-60"
      >
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
import type { ChapseMessage } from '@/composables/useChapseChat'
import chapseHead from '@/assets/chapse/head.svg'

const props = defineProps<{
  message: ChapseMessage
  showTimestamp?: boolean
}>()

const chapseAvatar = computed(() => {
  if (props.message.role === 'assistant' && props.message.avatar === 'chapse') {
    return chapseHead
  }
  return null
})

const messageClasses = computed(() => {
  if (props.message.role === 'assistant') {
    return 'bg-almond-300 dark:bg-slate-900 text-base dark:text-gray-100'
  } else {
    return 'bg-sage-300 text-sage-950 dark:bg-sage-300/20 dark:text-sage-300'
  }
})

const formattedContent = computed(() => {
  // Format markdown - keep it simple for now
  let formatted = props.message.content

  // Convert **text** to <strong>text</strong>
  formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert *text* to <em>text</em>
  formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // Convert numbered lists
  formatted = formatted.replace(/(\d+\.\s.*?)(?=\n\d+\.|$)/gs, '<li>$1</li>')
  formatted = formatted.replace(/(<li>.*?<\/li>)+/gs, '<ol class="list-decimal ml-4 my-2">$&</ol>')

  // Convert bullet lists
  formatted = formatted.replace(/(- .*?)(?=\n-|$)/gs, '<li>$1</li>')
  formatted = formatted.replace(/(<li>.*?<\/li>)+/gs, '<ul class="list-disc ml-4 my-2">$&</ul>')

  // Convert newlines to <br>
  formatted = formatted.replace(/\n/g, '<br>')

  return formatted
})

const formattedTime = computed(() => {
  const date = new Date(props.message.timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
})
</script>
