import type { MercureSubscription } from '@target/composables/useMercure'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type ConnectionStatus = 'connected' | 'disconnected' | 'reconnecting'

export const useMercureStore = defineStore('mercure', () => {
  const activeSubscriptions = ref<MercureSubscription[]>([])
  const connectionStatus = ref<ConnectionStatus>('connected')
  const hasShownDisconnectToast = ref(false)
  const disconnectDebounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)

  const isConnected = computed(() => connectionStatus.value === 'connected')
  const isDisconnected = computed(() => connectionStatus.value === 'disconnected')
  const isReconnecting = computed(() => connectionStatus.value === 'reconnecting')

  function setConnected() {
    connectionStatus.value = 'connected'
  }

  function setDisconnected() {
    connectionStatus.value = 'disconnected'
  }

  function setReconnecting() {
    connectionStatus.value = 'reconnecting'
  }

  function clearDisconnectTimer() {
    if (disconnectDebounceTimer.value) {
      clearTimeout(disconnectDebounceTimer.value)
      disconnectDebounceTimer.value = null
    }
  }

  return {
    activeSubscriptions,
    connectionStatus,
    hasShownDisconnectToast,
    disconnectDebounceTimer,
    isConnected,
    isDisconnected,
    isReconnecting,
    setConnected,
    setDisconnected,
    setReconnecting,
    clearDisconnectTimer,
  }
})
