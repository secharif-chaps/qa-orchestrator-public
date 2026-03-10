<template>
  <!-- Floating chat bubble button -->
  <Transition name="bubble">
    <button
      v-if="!isOpen"
      @click="toggleChat"
      class="from-primary to-primary-600 hover:from-primary-600 hover:to-primary-700 group animate-float fixed right-6 bottom-6 z-50 flex h-14 w-14 transform items-center justify-center rounded-full bg-gradient-to-br text-white shadow-lg transition-all duration-300 hover:scale-110 hover:shadow-xl"
    >
      <i class="fa fa-comments text-xl transition-transform duration-300 group-hover:rotate-12"></i>
    </button>
  </Transition>

  <!-- Floating chat window -->
  <Transition name="chat-window">
    <div
      v-if="isOpen"
      class="bg-base-100 border-primary-stroke animate-slideUp fixed right-6 bottom-6 z-50 flex h-[600px] max-h-[80vh] w-96 flex-col overflow-hidden rounded-2xl border shadow-2xl dark:border-slate-700 dark:bg-slate-900"
    >
      <!-- Chat header with glass effect -->
      <div
        class="from-primary/90 to-primary/70 relative flex items-center justify-between bg-gradient-to-r p-4 text-white backdrop-blur-sm"
      >
        <!-- AI Assistant info -->
        <div class="flex items-center gap-3">
          <div class="relative">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/20">
              <i class="fa fa-robot text-lg"></i>
            </div>
            <!-- Online indicator -->
            <span
              class="absolute right-0 bottom-0 h-3 w-3 animate-pulse rounded-full border-2 border-white bg-green-400"
            ></span>
          </div>
          <div>
            <h3 class="font-semibold">{{ t('company.chat.assistant.name') }}</h3>
            <p class="flex items-center gap-1 text-xs text-white/80">
              <span class="h-2 w-2 animate-pulse rounded-full bg-green-400"></span>
              {{ t('company.chat.assistant.online') }}
            </p>
          </div>
        </div>

        <!-- Close/minimize buttons -->
        <div class="flex gap-2">
          <button
            @click="minimizeChat"
            class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20"
          >
            <i class="fa fa-minus text-sm"></i>
          </button>
          <button
            @click="closeChat"
            class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20"
          >
            <i class="fa fa-times"></i>
          </button>
        </div>
      </div>

      <!-- Chat component -->
      <div class="flex-1 overflow-hidden">
        <Chat @hide="closeChat" :is-floating="true" :company-id="companyId" />
      </div>
    </div>
  </Transition>

  <!-- Minimized state -->
  <Transition name="minimized">
    <div
      v-if="isMinimized"
      @click="restoreChat"
      class="from-primary to-primary-600 fixed right-6 bottom-6 z-50 flex transform cursor-pointer items-center gap-3 rounded-full bg-gradient-to-r px-4 py-2 text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-xl"
    >
      <div class="relative">
        <i class="fa fa-robot"></i>
        <span
          class="absolute -top-1 -right-1 h-2 w-2 animate-pulse rounded-full bg-green-400"
        ></span>
      </div>
      <span class="text-sm font-medium">{{ t('company.chat.assistant.shortName') }}</span>
      <i class="fa fa-chevron-up text-xs"></i>
    </div>
  </Transition>
</template>

<script lang="ts" setup>
import Chat from '@/components/company/Chat.vue'
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const companyId = computed(() => String((route.params as Record<string, string>).companyId || ''))

// Chat state
const isOpen = ref(false)
const isMinimized = ref(false)

// Local storage key for chat state
const CHAT_STATE_KEY = `chat_state_${companyId.value}`

// Load chat state from localStorage
onMounted(() => {
  const savedState = localStorage.getItem(CHAT_STATE_KEY)
  if (savedState) {
    const state = JSON.parse(savedState)
    isOpen.value = state.isOpen || false
    isMinimized.value = state.isMinimized || false
  }
})

// Save chat state to localStorage
const saveChatState = () => {
  localStorage.setItem(
    CHAT_STATE_KEY,
    JSON.stringify({
      isOpen: isOpen.value,
      isMinimized: isMinimized.value,
    }),
  )
}

// Watch for state changes and save
watch([isOpen, isMinimized], saveChatState)

// Chat controls
const toggleChat = () => {
  isOpen.value = true
  isMinimized.value = false
}

const closeChat = () => {
  isOpen.value = false
  isMinimized.value = false
}

const minimizeChat = () => {
  isOpen.value = false
  isMinimized.value = true
}

const restoreChat = () => {
  isOpen.value = true
  isMinimized.value = false
}
</script>

<style scoped>
/* Floating animation for the chat bubble */
@keyframes float {
  0%,
  100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-float {
  animation: float 3s ease-in-out infinite;
}

/* Slide up animation for chat window */
@keyframes slideUp {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slideUp {
  animation: slideUp 0.3s ease-out;
}

/* Vue transitions */
.bubble-enter-active,
.bubble-leave-active {
  transition: all 0.3s ease;
}

.bubble-enter-from {
  transform: scale(0) rotate(-180deg);
  opacity: 0;
}

.bubble-leave-to {
  transform: scale(0) rotate(180deg);
  opacity: 0;
}

.chat-window-enter-active,
.chat-window-leave-active {
  transition: all 0.3s ease;
}

.chat-window-enter-from {
  transform: translateY(20px) scale(0.95);
  opacity: 0;
}

.chat-window-leave-to {
  transform: translateY(20px) scale(0.95);
  opacity: 0;
}

.minimized-enter-active,
.minimized-leave-active {
  transition: all 0.3s ease;
}

.minimized-enter-from,
.minimized-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
