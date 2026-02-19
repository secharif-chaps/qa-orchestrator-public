<template>
  <div class="mb-4 flex w-full justify-start">
    <div class="flex items-end gap-2">
      <!-- AVATAR (chatbot) -->
      <img :src="chapse_head" alt="Chapse" class="size-6 shrink-0" />

      <!-- TYPING INDICATOR BUBBLE -->
      <div class="w-fit max-w-85 rounded-sm border border-gray-200 bg-white px-3 pt-3 pb-2">
        <!-- Typing text with animated dots -->
        <div class="flex items-center gap-1 text-sm text-gray-600">
          <span>{{ typingIndicatorPhrase }}</span>
          <div class="typing-dots flex gap-1">
            <span class="typing-dot" />
            <span class="typing-dot" />
            <span class="typing-dot" />
          </div>
        </div>

        <!-- Reassurance message when waiting too long -->
        <div v-if="reassurancePhrase" class="mt-2 flex items-center gap-1 text-xs text-gray-600">
          <span>{{ reassurancePhrase }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import chapse_head from '@target/assets/images/chapse_head.svg';
import { useTypingIndicatorPhrases } from '@target/composables/useTypingIndicatorPhrases';
import { toRef } from 'vue';

const { showReassurance = false } = defineProps<{
  showReassurance?: boolean
}>()

const { typingIndicatorPhrase, reassurancePhrase } = useTypingIndicatorPhrases(
  toRef(() => showReassurance),
)
</script>

<style scoped>
/* Typing dots animation */
.typing-dot {
  width: 6px;
  height: 6px;
  background-color: currentColor;
  border-radius: 50%;
  display: inline-block;
  animation: typingBounce 1.4s infinite ease-in-out both;
}

.typing-dot:nth-child(1) {
  animation-delay: -0.32s;
}

.typing-dot:nth-child(2) {
  animation-delay: -0.16s;
}

@keyframes typingBounce {
  0%,
  80%,
  100% {
    transform: scale(0);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
