<template>
  <div
    class="flex flex-col overflow-y-auto"
    :class="isFloating ? 'h-full bg-transparent' : 'h-[calc(80vh-5rem)] rounded-md bg-white p-4'"
  >
    <div
      v-if="!isFloating"
      class="border-primary text-neutral-black-font relative flex items-center justify-between gap-4 border-b pb-4"
    >
      <div class="flex items-center gap-4">
        <i class="fa fa-chevrons-right icon-secondary cursor-pointer" @click="$emit('hide')"></i>
        <span class="text-sm">{{ t('screen.company.chat.askOurAi') }}</span>
      </div>
      <div class="from-bg1 absolute -bottom-6 h-6 w-full bg-gradient-to-b to-transparent">
        <!-- <i class="fa fa-up-right-and-down-left-from-center"></i> -->
      </div>
    </div>
    <div
      class="flex grow flex-col gap-2 overflow-y-auto"
      :class="isFloating ? 'p-4' : 'py-2'"
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
          class="inline-block rounded-md p-4 text-xs"
          :class="{
            'bg-primary-lighter dark:bg-slate-900': message.from === 'ai',
            'bg-primary/10 text-neutral-black-font dark:bg-primary/10 dark:text-sage-content':
              message.from === 'user',
          }"
          v-sanitize-html="formatMarkdown(message.text)"
        ></div>
      </div>
      <div v-if="isLoading">
        <div class="bg-primary-lighter mr-auto inline-block rounded-md p-4 text-xs">
          <i class="fa fa-spinner fa-spin"></i> {{ t('screen.company.chat.thinking') }}
        </div>
      </div>
    </div>
    <div
      class="relative"
      :class="isFloating ? 'border-primary-lighter-stroke border-t p-4' : 'pt-2'"
    >
      <Textarea
        id="chat-question"
        v-model="question"
        :placeholder="t('screen.company.chat.placeholder')"
        :class="isFloating ? 'h-20' : 'h-32'"
        :disabled="isLoading"
        class="w-full"
        @keyup.enter="sendMessage"
        @keydown.enter.ctrl.prevent="sendMessage"
      />
      <Button
        variant="primary"
        icon="fa-send"
        icon-only
        size="sm"
        class="absolute right-2 bottom-4"
        rounded
        @click="sendMessage"
        :disabled="isLoading || !question.trim()"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { apiClient } from '@/api/client'
import { companyByIdQuery } from '@/queries/companies'
import { Button, Textarea } from '@owlint/feathers-vue'
import { useQuery } from '@pinia/colada'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()

const props = defineProps<{
  isFloating?: boolean
  companyId?: string
}>()

defineEmits(['hide'])

const route = useRoute()

// Chat state
const messages = ref([
  {
    text: t('screen.company.chat.welcomeMessage'),
    from: 'ai',
  },
])

const question = ref('')
const isLoading = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)

const companyId = computed(
  () => props.companyId || String((route.params as Record<string, string>).companyId || ''),
)

// Auto-scroll to bottom when messages change
watch(
  messages,
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  },
  { deep: true },
)

const { data: company } = useQuery(() =>
  companyByIdQuery({
    id: companyId.value,
  }),
)

interface ChatResponse {
  response: string
  status: string
}

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
    // Convert chat history to the format expected by the backend
    const chatHistory = messages.value.slice(0, -1).map((msg) => ({
      role: msg.from === 'user' ? 'user' : 'assistant',
      content: msg.text,
    }))

    // Send to mint-backend API with authentication.
    // silent: errors are rendered as a chat message instead of a toast.
    const response: ChatResponse = await apiClient.post(
      `/companies/${companyId.value}/chatbot`,
      {
        message: userQuestion,
        company_context: company.value,
        chat_history: chatHistory,
      },
      { silent: true },
    )

    // Parse the response to extract actual content from stringified format
    let responseText = response.response || t('screen.company.chat.noResponse')

    // Check if response contains stringified JSON with 'output' field
    if (
      typeof responseText === 'string' &&
      responseText.startsWith('{') &&
      responseText.endsWith('}')
    ) {
      try {
        // Try to parse the stringified response
        const parsed = eval(`(${responseText})`)
        if (parsed && typeof parsed === 'object' && 'output' in parsed) {
          responseText = parsed.output
        }
      } catch (parseError) {
        console.warn('Could not parse stringified response:', parseError)
        // Keep original response if parsing fails
      }
    }

    // Add AI response
    messages.value.push({
      text: responseText,
      from: 'ai',
    })
  } catch (error) {
    console.error('Error sending message to backend:', error)
    messages.value.push({
      text: t('screen.company.chat.errorMessage'),
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
