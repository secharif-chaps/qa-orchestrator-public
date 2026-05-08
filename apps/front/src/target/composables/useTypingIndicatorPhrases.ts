import type { MaybeRefOrGetter } from 'vue'
import { computed, onUnmounted, ref, toValue, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const PHRASE_ROTATION_INTERVAL_MS = 60_000 // 60 seconds
const TYPING_INDICATOR_COUNT = 20
const REASSURANCE_MESSAGE_COUNT = 20

function getRandomIndexExcluding(max: number, exclude: number): number {
  if (max <= 1) return 0
  let newIndex: number
  do {
    newIndex = Math.floor(Math.random() * max)
  } while (newIndex === exclude)
  return newIndex
}

export function useTypingIndicatorPhrases(showReassurance: MaybeRefOrGetter<boolean>) {
  const { t } = useI18n()

  // Current phrase index for each type
  const typingIndicatorIndex = ref(Math.floor(Math.random() * TYPING_INDICATOR_COUNT))
  const reassuranceMessageIndex = ref(Math.floor(Math.random() * REASSURANCE_MESSAGE_COUNT))

  // Track if we've switched to reassurance mode
  const isInReassuranceMode = ref(false)

  // Interval for phrase rotation
  let rotationIntervalId: ReturnType<typeof setInterval> | null = null

  const rotatePhrase = () => {
    if (isInReassuranceMode.value) {
      reassuranceMessageIndex.value = getRandomIndexExcluding(
        REASSURANCE_MESSAGE_COUNT,
        reassuranceMessageIndex.value,
      )
    } else {
      typingIndicatorIndex.value = getRandomIndexExcluding(
        TYPING_INDICATOR_COUNT,
        typingIndicatorIndex.value,
      )
    }
  }

  const startRotation = () => {
    if (rotationIntervalId) return
    rotationIntervalId = setInterval(rotatePhrase, PHRASE_ROTATION_INTERVAL_MS)
  }

  const stopRotation = () => {
    if (rotationIntervalId) {
      clearInterval(rotationIntervalId)
      rotationIntervalId = null
    }
  }

  // Watch for showReassurance changes to switch modes
  watch(
    () => toValue(showReassurance),
    (shouldShowReassurance) => {
      if (shouldShowReassurance && !isInReassuranceMode.value) {
        isInReassuranceMode.value = true
        // Pick a new random reassurance message when switching modes
        reassuranceMessageIndex.value = Math.floor(Math.random() * REASSURANCE_MESSAGE_COUNT)
      }
    },
    { immediate: true },
  )

  // Start rotation on mount
  startRotation()

  // Cleanup on unmount
  onUnmounted(() => {
    stopRotation()
  })

  const typingIndicatorPhrase = computed(() => {
    const phrases = [
      t('target.watchFiles.chat.typing_indicator.0'),
      t('target.watchFiles.chat.typing_indicator.1'),
      t('target.watchFiles.chat.typing_indicator.2'),
      t('target.watchFiles.chat.typing_indicator.3'),
      t('target.watchFiles.chat.typing_indicator.4'),
      t('target.watchFiles.chat.typing_indicator.5'),
      t('target.watchFiles.chat.typing_indicator.6'),
      t('target.watchFiles.chat.typing_indicator.7'),
      t('target.watchFiles.chat.typing_indicator.8'),
      t('target.watchFiles.chat.typing_indicator.9'),
      t('target.watchFiles.chat.typing_indicator.10'),
      t('target.watchFiles.chat.typing_indicator.11'),
      t('target.watchFiles.chat.typing_indicator.12'),
      t('target.watchFiles.chat.typing_indicator.13'),
      t('target.watchFiles.chat.typing_indicator.14'),
      t('target.watchFiles.chat.typing_indicator.15'),
      t('target.watchFiles.chat.typing_indicator.16'),
      t('target.watchFiles.chat.typing_indicator.17'),
      t('target.watchFiles.chat.typing_indicator.18'),
      t('target.watchFiles.chat.typing_indicator.19'),
    ]
    return phrases[typingIndicatorIndex.value] ?? ''
  })

  const reassurancePhrase = computed(() => {
    if (!toValue(showReassurance)) return undefined
    const phrases = [
      t('target.watchFiles.chat.reassurance_message.0'),
      t('target.watchFiles.chat.reassurance_message.1'),
      t('target.watchFiles.chat.reassurance_message.2'),
      t('target.watchFiles.chat.reassurance_message.3'),
      t('target.watchFiles.chat.reassurance_message.4'),
      t('target.watchFiles.chat.reassurance_message.5'),
      t('target.watchFiles.chat.reassurance_message.6'),
      t('target.watchFiles.chat.reassurance_message.7'),
      t('target.watchFiles.chat.reassurance_message.8'),
      t('target.watchFiles.chat.reassurance_message.9'),
      t('target.watchFiles.chat.reassurance_message.10'),
      t('target.watchFiles.chat.reassurance_message.11'),
      t('target.watchFiles.chat.reassurance_message.12'),
      t('target.watchFiles.chat.reassurance_message.13'),
      t('target.watchFiles.chat.reassurance_message.14'),
      t('target.watchFiles.chat.reassurance_message.15'),
      t('target.watchFiles.chat.reassurance_message.16'),
      t('target.watchFiles.chat.reassurance_message.17'),
      t('target.watchFiles.chat.reassurance_message.18'),
      t('target.watchFiles.chat.reassurance_message.19'),
    ]
    return phrases[reassuranceMessageIndex.value]
  })

  return {
    typingIndicatorPhrase,
    reassurancePhrase,
  }
}
