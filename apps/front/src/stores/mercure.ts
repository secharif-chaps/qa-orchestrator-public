import type { MercureSubscription } from '@/composables/realtime/useMercure'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type ConnectionStatus = 'connected' | 'disconnected'

export const useMercureStore = defineStore('mercure', () => {
  const activeSubscriptions = ref<MercureSubscription[]>([])
  const connectionStatus = ref<ConnectionStatus>('connected')
  const hasShownDisconnectToast = ref(false)

  const isConnected = computed(() => connectionStatus.value === 'connected')
  const isDisconnected = computed(() => connectionStatus.value === 'disconnected')

  function setConnected() {
    connectionStatus.value = 'connected'
  }

  function setDisconnected() {
    connectionStatus.value = 'disconnected'
  }

  return {
    activeSubscriptions,
    connectionStatus,
    hasShownDisconnectToast,
    isConnected,
    isDisconnected,
    setConnected,
    setDisconnected,
  }
})
