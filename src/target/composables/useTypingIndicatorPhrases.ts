import type { MaybeRefOrGetter } from 'vue';
import { computed, onUnmounted, ref, toValue, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const PHRASE_ROTATION_INTERVAL_MS = 60_000; // 60 seconds
const TYPING_INDICATOR_COUNT = 20;
const REASSURANCE_MESSAGE_COUNT = 20;

/**
 * Get a random index different from the current one
 */
function getRandomIndexExcluding(max: number, exclude: number): number {
  if (max <= 1) return 0;
  let newIndex: number;
  do {
    newIndex = Math.floor(Math.random() * max);
  } while (newIndex === exclude);
  return newIndex;
}

/**
 * Composable for managing typing indicator phrases with random selection and rotation.
 * - Selects a random phrase on mount
 * - Rotates to a new random phrase every 60 seconds
 * - Switches to reassurance phrases after showReassurance becomes true
 */
export function useTypingIndicatorPhrases(
  showReassurance: MaybeRefOrGetter<boolean>,
) {
  const { t } = useI18n();

  // Current phrase index for each type
  const typingIndicatorIndex = ref(
    Math.floor(Math.random() * TYPING_INDICATOR_COUNT),
  );
  const reassuranceMessageIndex = ref(
    Math.floor(Math.random() * REASSURANCE_MESSAGE_COUNT),
  );

  // Track if we've switched to reassurance mode
  const isInReassuranceMode = ref(false);

  // Interval for phrase rotation
  let rotationIntervalId: ReturnType<typeof setInterval> | null = null;

  const rotatePhrase = () => {
    if (isInReassuranceMode.value) {
      reassuranceMessageIndex.value = getRandomIndexExcluding(
        REASSURANCE_MESSAGE_COUNT,
        reassuranceMessageIndex.value,
      );
    } else {
      typingIndicatorIndex.value = getRandomIndexExcluding(
        TYPING_INDICATOR_COUNT,
        typingIndicatorIndex.value,
      );
    }
  };

  const startRotation = () => {
    if (rotationIntervalId) return;
    rotationIntervalId = setInterval(rotatePhrase, PHRASE_ROTATION_INTERVAL_MS);
  };

  const stopRotation = () => {
    if (rotationIntervalId) {
      clearInterval(rotationIntervalId);
      rotationIntervalId = null;
    }
  };

  // Watch for showReassurance changes to switch modes
  watch(
    () => toValue(showReassurance),
    (shouldShowReassurance) => {
      if (shouldShowReassurance && !isInReassuranceMode.value) {
        isInReassuranceMode.value = true;
        // Pick a new random reassurance message when switching modes
        reassuranceMessageIndex.value = Math.floor(
          Math.random() * REASSURANCE_MESSAGE_COUNT,
        );
      }
    },
    { immediate: true },
  );

  // Start rotation on mount
  startRotation();

  // Cleanup on unmount
  onUnmounted(() => {
    stopRotation();
  });

  // Current typing indicator phrase
  const typingIndicatorPhrase = computed(() => {
    return t(`watch_files.chat.typing_indicator.${typingIndicatorIndex.value}`);
  });

  // Current reassurance phrase (only used when showReassurance is true)
  const reassurancePhrase = computed(() => {
    if (!toValue(showReassurance)) return undefined;
    return t(
      `watch_files.chat.reassurance_message.${reassuranceMessageIndex.value}`,
    );
  });

  return {
    typingIndicatorPhrase,
    reassurancePhrase,
  };
}
