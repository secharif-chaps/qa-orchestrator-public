<template>
  <div
    class="flex flex-col h-[calc(80vh-5rem)] overflow-y-auto rounded-xl bg-white p-4"
  >
    <div
      class="flex gap-4 items-center justify-between border-b pb-4 border-primary text-secondary"
    >
      <div class="flex gap-4 items-center">
        <i
          class="fa fa-chevrons-right cursor-pointer icon-secondary"
          @click="$emit('hide')"
        ></i>
        <span class="text-sm">Ask our AI</span>
      </div>
      <div>
        <!-- <i class="fa fa-up-right-and-down-left-from-center"></i> -->
      </div>
    </div>
    <div
      class="grow py-2 flex flex-col gap-2 overflow-y-auto"
      ref="messagesContainer"
    >
      <div
        v-for="(message, index) in messages"
        :key="index"
        :class="{
          'mr-auto': message.from === 'ai',
          'ml-auto': message.from === 'user',
        }"
      >
        <div
          class="text-xs p-4 inline-block rounded-xl"
          :class="{
            'bg-bg3': message.from === 'ai',
            'bg-emerald-100 text-primary': message.from === 'user',
          }"
          v-html="formatMarkdown(message.text)"
        ></div>
      </div>
      <div v-if="isStreaming">
        <div
          class="text-xs p-4 inline-block rounded-xl bg-bg3 mr-auto"
          v-html="formatMarkdown(streamingText)"
        ></div>
      </div>
    </div>
    <div class="relative pt-2">
      <textarea
        @keyup.enter="askAgent"
        v-model="question"
        placeholder="Write a message..."
        class="w-full h-32 bg-bg3 border border-border-2 rounded-lg p-2 text-sm focus-within:outline-primary"
        @keydown.enter.ctrl.prevent="askAgent"
      ></textarea>
      <OButton
        icon="fa-send"
        class="absolute right-2 bottom-4 cursor-pointer"
        rounded
        @click="askAgent"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import { nextTick, ref, watch } from 'vue'

const messages = ref([
  {
    text: 'Hello, how can I help you?',
    from: 'ai',
  },
])

const loading = ref(false)
const isStreaming = ref(false)
const streamingText = ref('')
const messagesContainer = ref(null)

const { ask } = useAgent()
const { companyName } = useCompanyData()

const question = ref('')

const emit = defineEmits(['hide'])

// Automatically scroll to bottom when messages change
watch(
  [messages, streamingText],
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  },
  { deep: true }
)

const askAgent = async () => {
  if (!question.value) return

  const userQuestion = question.value
  question.value = ''

  // Add user message
  messages.value.push({
    text: userQuestion,
    from: 'user',
  })

  // Start streaming
  isStreaming.value = true
  streamingText.value = ''

  // Process the chunks as they come in
  const handleChunk = (chunk) => {
    streamingText.value += chunk
  }

  const context = messages.value

  // Call the streaming version
  const response = await ask(
    userQuestion,
    companyName.value,
    context,
    handleChunk
  )

  // Stop streaming and add the final message
  if (response) {
    isStreaming.value = false
    messages.value.push({
      text: response,
      from: 'ai',
    })
  } else {
    // In case of error
    isStreaming.value = false
    messages.value.push({
      text: "Sorry, I couldn't process your request.",
      from: 'ai',
    })
  }
}

// Function to format markdown text
const formatMarkdown = (text: string): string => {
  // Convert **text** to <strong>text</strong>
  const boldFormatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert *text* to <em>text</em> for italics
  const italicsFormatted = boldFormatted.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // Convert numbered lists (1. Item) to HTML ordered lists
  const listFormatted = italicsFormatted
    .replace(/(\d+\.\s.*?)(?=\n\d+\.|$)/gs, '<li>$1</li>')
    .replace(/(<li>.*?<\/li>)+/gs, '<ol>$&</ol>')

  // Convert newlines to <br> tags
  return listFormatted.replace(/\n/g, '<br>')
}
</script>
