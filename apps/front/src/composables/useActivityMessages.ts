import { ref, watchEffect, onUnmounted, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'

export const useActivityMessages = (isActive: Ref<boolean>) => {
  const { t } = useI18n()

  const getMessages = (): string[] => [
    t('screen.company.screening.messages.thinking'),
    t('screen.company.screening.messages.profile'),
    t('screen.company.screening.messages.team'),
    t('screen.company.screening.messages.products'),
    t('screen.company.screening.messages.press'),
    t('screen.company.screening.messages.csr'),
    t('screen.company.screening.messages.jobs'),
    t('screen.company.screening.messages.timeline'),
  ]

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
      const messages = getMessages()
      currentMessage.value = messages[index]
      intervalId = setInterval(() => {
        index = (index + 1) % messages.length
        currentMessage.value = getMessages()[index]
      }, 3000)
    }
  })

  onUnmounted(stop)

  return { currentMessage }
}
