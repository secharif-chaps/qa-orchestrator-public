<template>
  <div
    class="flex flex-col h-[calc(80vh-5rem)] overflow-y-auto rounded-xl bg-bg1 p-4"
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
            'bg-bg3 dark:bg-slate-900': message.from === 'ai',
            'bg-primary/10 text-primary dark:bg-primary/10 dark:text-primary': message.from === 'user',
          }"
          v-html="formatMarkdown(message.text)"
        ></div>
      </div>
      <div v-if="isLoading">
        <div class="text-xs p-4 inline-block rounded-xl bg-bg3 mr-auto">
          <i class="fa fa-spinner fa-spin"></i> Thinking...
        </div>
      </div>
    </div>
    <div class="relative pt-2">
      <textarea
        @keyup.enter="sendMessage"
        v-model="question"
        placeholder="Write a message..."
        class="w-full h-32 bg-bg3 dark:bg-slate-900 border border-border-2 dark:border-slate-700 rounded-lg p-2 text-sm focus-within:outline-primary"
        @keydown.enter.ctrl.prevent="sendMessage"
        :disabled="isLoading"
      ></textarea>
      <OButton
        icon="fa-send"
        class="absolute right-2 bottom-4 cursor-pointer"
        rounded
        @click="sendMessage"
        :disabled="isLoading || !question.trim()"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { OButton } from '@owlint/feathers-vue'
import { ref, nextTick, watch } from 'vue'

const emit = defineEmits(['hide'])

const { companyName } = useCompanyData()
const companyStore = useCompanyStore()
const runtimeConfig = useRuntimeConfig()

// Chat state
const messages = ref([
  {
    text: 'Hello! I am Basil, your assistant. I can help you with questions about this company. What would you like to know?',
    from: 'ai',
  },
])

const question = ref('')
const isLoading = ref(false)
const messagesContainer = ref(null)

// Direct call to n8n webhook
const webhookUrl = `http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678/webhook/${runtimeConfig.public.n8nWebhookIdChat}/chat`

// Auto-scroll to bottom when messages change
watch(
  messages,
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  },
  { deep: true }
)

const { company } = useCompanyData()


const sendMessage = async () => {
  if (!question.value.trim() || isLoading.value) return

  const userQuestion = question.value.trim()
  question.value = ''

  // Add user message
  messages.value.push({
    text: userQuestion,
    from: 'user',
  })

  isLoading.value = true

  try {
    
    // Send to n8n webhook
    const response = await $fetch(webhookUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: {
        message: userQuestion,
        companyContext: company.value,
        chatHistory: messages.value.slice(0, -1) // Exclude the current user message
      }
    })

    // Add AI response
    messages.value.push({
      text: response.response || response.output || 'I received your message but couldn\'t generate a response.',
      from: 'ai',
    })

  } catch (error) {
    console.error('Error sending message to n8n:', error)
    messages.value.push({
      text: 'Sorry, I encountered an error processing your request. Please try again.',
      from: 'ai',
    })
  } finally {
    isLoading.value = false
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
