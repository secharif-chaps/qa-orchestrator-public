import { computed, onUnmounted, ref, watch } from 'vue';
import { useConversationStore } from '~/stores/conversation';

export function useConversationTimeout() {
  const conversationStore = useConversationStore();

  // Track elapsed time (updated every second)
  const elapsedSeconds = ref(0);

  // Track if reassurance has been shown (stays true until waiting ends)
  const reassuranceShown = ref(false);

  // Compute elapsed time directly from store's waitingStartTime
  const updateElapsedTime = () => {
    if (
      !conversationStore.isWaitingForAI ||
      !conversationStore.waitingStartTime
    ) {
      elapsedSeconds.value = 0;
      return;
    }
    elapsedSeconds.value = Math.floor(
      (Date.now() - conversationStore.waitingStartTime) / 1000,
    );
  };

  // Set up interval to update elapsed time every second
  const intervalId = setInterval(updateElapsedTime, 1000);

  // Reset reassuranceShown when we stop waiting for AI
  watch(
    () => conversationStore.isWaitingForAI,
    (isWaiting) => {
      if (!isWaiting) {
        reassuranceShown.value = false;
      }
    },
  );

  // Computed: Show reassurance message after 60 seconds (once shown, stays shown)
  const showReassurance = computed(() => {
    if (!conversationStore.isWaitingForAI) {
      return false;
    }
    // Once shown, keep it shown until waiting ends
    if (reassuranceShown.value) {
      return true;
    }
    // Check if we should show it now
    if (elapsedSeconds.value >= 60) {
      reassuranceShown.value = true;
      return true;
    }
    return false;
  });

  // Computed: Should cancel after 180 seconds
  const shouldCancel = computed(
    () => conversationStore.isWaitingForAI && elapsedSeconds.value >= 180,
  );

  // Clean up interval on component unmount
  onUnmounted(() => {
    clearInterval(intervalId);
  });

  return {
    elapsedSeconds,
    showReassurance,
    shouldCancel,
  };
}
