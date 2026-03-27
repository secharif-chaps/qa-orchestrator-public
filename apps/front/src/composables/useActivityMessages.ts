import { ref, watchEffect, onUnmounted, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'

const MESSAGE_KEYS = [
  'screen.company.screening.messages.thinking',
  'screen.company.screening.messages.profile',
  'screen.company.screening.messages.team',
  'screen.company.screening.messages.products',
  'screen.company.screening.messages.press',
  'screen.company.screening.messages.csr',
  'screen.company.screening.messages.jobs',
  'screen.company.screening.messages.timeline',
] as const

export const useActivityMessages = (isActive: Ref<boolean>) => {
  const { t } = useI18n()
  const currentMessage = ref('')
  let index = 0
  let intervalId: ReturnType<typeof setInterval> | null = null

  const stop = () => {
    if (intervalId !== null) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  watchEffect(() => {
    stop()
    if (isActive.value) {
      index = 0
      currentMessage.value = t(MESSAGE_KEYS[index])
      intervalId = setInterval(() => {
        index = (index + 1) % MESSAGE_KEYS.length
        currentMessage.value = t(MESSAGE_KEYS[index])
      }, 3000)
    }
  })

  onUnmounted(stop)

  return { currentMessage }
}
