<template>
  <div class="relative p-0">
    <BaseTextarea
      id="chat-input"
      v-model="message"
      :placeholder="inputPlaceholder"
      :rows="5"
      max-height="200px"
      :input-class="textareaInputClass"
      autofocus
      tabindex="1"
      :aria-label="inputPlaceholder"
      @keydown="handleKeyDown"
    />
    <Button
      v-if="isWaitingForAI"
      variant="tertiary"
      class="absolute right-3 bottom-3"
      :aria-label="$t('watch_files.chat.input.cancel_button')"
      :title="$t('watch_files.chat.input.cancel_button')"
      rounded
      tabindex="2"
      icon="fa-circle-stop"
      @click="emit('cancel')"
    />
    <Button
      v-else
      :variant="disabled ? 'secondary' : 'tertiary'"
      class="absolute right-3 bottom-3"
      :disabled="disabled || !canSendMessage"
      :aria-label="$t('watch_files.chat.input.send_button')"
      rounded
      tabindex="2"
      icon="fa-paper-plane"
      @click="sendMessage"
    />
  </div>
</template>

<script setup lang="ts">
import { Button } from '@owlint/feathers-vue'
import BaseTextarea from '@target/components/global/BaseTextarea.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  disabled?: boolean
  isWaitingForAI?: boolean
  placeholder?: string
}

const { disabled = false, isWaitingForAI = false, placeholder = null } = defineProps<Props>()

const emit = defineEmits<{
  send: [message: string]
  cancel: []
}>()

const message = ref('')

const canSendMessage = computed(() => message.value.trim().length > 0 && !disabled)

const inputPlaceholder = computed(() => {
  return placeholder ?? t('watch_files.chat.input.placeholder')
})

const textareaInputClass = computed(() => {
  return `border border-gray-300 h-full pr-10 ${disabled ? 'cursor-not-allowed' : ''}`
})

const sendMessage = () => {
  if (message.value.trim().length > 0) {
    emit('send', message.value.trim())
    message.value = ''
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !disabled && !isWaitingForAI) {
    if (e.ctrlKey || e.shiftKey) {
      const textarea = e.target as HTMLTextAreaElement
      const start = textarea.selectionStart
      const end = textarea.selectionEnd

      message.value = message.value.substring(0, start) + '\n' + message.value.substring(end)

      requestAnimationFrame(() => {
        textarea.selectionStart = textarea.selectionEnd = start + 1
      })
    } else {
      e.preventDefault()
      sendMessage()
    }
  }
}
</script>
