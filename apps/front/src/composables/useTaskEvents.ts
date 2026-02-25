/**
 * SSE (Server-Sent Events) composable for real-time task updates.
 *
 * This composable establishes a persistent SSE connection with the backend
 * to receive task status updates in real-time. It eliminates the need for
 * polling and provides instant feedback when tasks complete.
 *
 * Features:
 * - Automatic reconnection with exponential backoff
 * - Cache invalidation when tasks update
 * - Toast notifications when all tasks complete
 * - Works globally (even when user navigates away from company page)
 */

import { ref, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQueryCache } from '@pinia/colada'
import { useAuthStore } from '@/stores/auth'
import { useEndpointResolver } from '@/composables/useEndpointResolver'
import { TASK_QUERY_KEYS } from '@/queries/tasks'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import { toast } from '@/utils/toast'

// =============================================================================
// Types
// =============================================================================

interface TaskUpdateEvent {
  type: 'task_update'
  data: {
    company_id: number
    task_id: number
    status: string
    task_type: string
    error: string | null
  }
}

interface AllTasksCompletedEvent {
  type: 'all_tasks_completed'
  data: {
    company_id: number
    company_name: string
    folder_id: string | null
    success_count: number
    error_count: number
  }
}

interface ConnectedEvent {
  type: 'connected'
  data: {
    message: string
  }
}

type TaskEvent = TaskUpdateEvent | AllTasksCompletedEvent | ConnectedEvent

// =============================================================================
// Singleton State (shared across all component instances)
// =============================================================================

// Global state to ensure only one SSE connection exists
let globalAbortController: AbortController | null = null
let globalIsConnected = false
let globalReconnectAttempts = 0
let globalReconnectTimeout: ReturnType<typeof setTimeout> | null = null

const MAX_RECONNECT_ATTEMPTS = 5
const BASE_RECONNECT_DELAY = 1000 // 1 second

// =============================================================================
// Composable
// =============================================================================

export function useTaskEvents() {
  const authStore = useAuthStore()
  const queryCache = useQueryCache()
  const router = useRouter()
  const { t } = useI18n()
  const { endpoints } = useEndpointResolver()

  // Local reactive state (for component reactivity)
  const isConnected = ref(globalIsConnected)
  const connectionError = ref<string | null>(null)

  /**
   * Invalidate and refetch queries for a specific company's data.
   * Uses invalidateQueries with 'all' to refetch both active and inactive queries.
   */
  function refetchCompanyData(companyId: number) {
    const companyIdStr = companyId.toString()

    // Invalidate and refetch task queries
    queryCache.invalidateQueries({
      key: TASK_QUERY_KEYS.byCompanyId(companyIdStr),
    })

    // Invalidate and refetch company query (tasks are nested in company response)
    queryCache.invalidateQueries({
      key: COMPANY_QUERY_KEYS.byId(companyIdStr),
    })

    // Invalidate the companies list - will refresh on next access
    queryCache.invalidateQueries({
      key: COMPANY_QUERY_KEYS.root,
    })
  }

  /**
   * Handle incoming SSE events.
   */
  function handleEvent(event: TaskEvent) {
    switch (event.type) {
      case 'connected':
        console.log('[SSE] Connected to task events stream')
        break

      case 'task_update': {
        const { company_id } = event.data

        // Refetch data - if user is on the company page, they'll see the update immediately
        refetchCompanyData(company_id)

        console.log('[SSE] Task update received:', event.data)
        break
      }

      case 'all_tasks_completed': {
        const { company_id, company_name, folder_id, error_count } = event.data

        // Refetch data - if user is on the company page, they'll see the update immediately
        refetchCompanyData(company_id)

        // Build action to navigate to company page
        const action = folder_id
          ? {
              label: t('tasks.events.view'),
              onClick: () => {
                router.push(`/folders/${folder_id}/companies/${company_id}`)
              },
            }
          : undefined

        // Show toast notification with action button
        if (error_count === 0) {
          toast.success(t('tasks.events.ready', { companyName: company_name }), { action })
        } else {
          toast.warning(t('tasks.events.readyWithErrors', { companyName: company_name }), {
            action,
          })
        }

        console.log('[SSE] All tasks completed:', event.data)
        break
      }
    }
  }

  /**
   * Parse SSE data from the stream.
   */
  function parseSSEData(chunk: string): TaskEvent[] {
    const events: TaskEvent[] = []
    const lines = chunk.split('\n')

    for (const line of lines) {
      if (line.startsWith('data: ')) {
        const data = line.slice(6).trim()
        if (data) {
          try {
            const parsed = JSON.parse(data) as TaskEvent
            events.push(parsed)
          } catch {
            // Ignore parse errors (might be partial data)
          }
        }
      }
      // Ignore keepalive comments (lines starting with :)
    }

    return events
  }

  /**
   * Connect to the SSE endpoint.
   */
  async function connect() {
    // Don't connect if already connected or no auth token
    if (globalIsConnected || !authStore.accessToken) {
      return
    }

    // Abort any existing connection
    if (globalAbortController) {
      globalAbortController.abort()
    }

    globalAbortController = new AbortController()
    const apiUrl = endpoints.value.apiUrl

    console.log('[SSE] Connecting to task events stream...')

    try {
      const response = await fetch(`${apiUrl}/tasks/events/stream`, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${authStore.accessToken}`,
          Accept: 'text/event-stream',
        },
        signal: globalAbortController.signal,
      })

      if (!response.ok) {
        throw new Error(`SSE connection failed: ${response.status} ${response.statusText}`)
      }

      const reader = response.body?.getReader()
      if (!reader) {
        throw new Error('Response body is null')
      }

      // Connection successful
      globalIsConnected = true
      isConnected.value = true
      connectionError.value = null
      globalReconnectAttempts = 0

      console.log('[SSE] Connected successfully')

      const decoder = new TextDecoder()

      // Read the stream
      try {
        while (true) {
          const { done, value } = await reader.read()
          if (done) {
            console.log('[SSE] Stream ended')
            break
          }

          const chunk = decoder.decode(value, { stream: true })
          const events = parseSSEData(chunk)

          for (const event of events) {
            handleEvent(event)
          }
        }
      } finally {
        reader.releaseLock()
      }
    } catch (error) {
      // Don't log abort errors (normal disconnection)
      if (error instanceof Error && error.name === 'AbortError') {
        console.log('[SSE] Connection aborted')
        return
      }

      console.error('[SSE] Connection error:', error)
      connectionError.value = error instanceof Error ? error.message : 'Unknown error'
    } finally {
      globalIsConnected = false
      isConnected.value = false

      // Schedule reconnect if not intentionally disconnected
      if (globalAbortController && !globalAbortController.signal.aborted) {
        scheduleReconnect()
      }
    }
  }

  /**
   * Schedule a reconnection attempt with exponential backoff.
   */
  function scheduleReconnect() {
    if (globalReconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
      console.warn('[SSE] Max reconnection attempts reached')
      connectionError.value = 'Max reconnection attempts reached. Please refresh the page.'
      return
    }

    // Clear any existing timeout
    if (globalReconnectTimeout) {
      clearTimeout(globalReconnectTimeout)
    }

    const delay = BASE_RECONNECT_DELAY * Math.pow(2, globalReconnectAttempts)
    globalReconnectAttempts++

    console.log(`[SSE] Scheduling reconnect in ${delay}ms (attempt ${globalReconnectAttempts})`)

    globalReconnectTimeout = setTimeout(() => {
      if (!globalIsConnected && authStore.isAuthenticated) {
        connect()
      }
    }, delay)
  }

  /**
   * Disconnect from the SSE endpoint.
   */
  function disconnect() {
    if (globalAbortController) {
      globalAbortController.abort()
      globalAbortController = null
    }

    if (globalReconnectTimeout) {
      clearTimeout(globalReconnectTimeout)
      globalReconnectTimeout = null
    }

    globalIsConnected = false
    isConnected.value = false
    globalReconnectAttempts = 0

    console.log('[SSE] Disconnected')
  }

  /**
   * Force a reconnection (useful for manual retry).
   */
  function reconnect() {
    disconnect()
    globalReconnectAttempts = 0
    connectionError.value = null
    connect()
  }

  // Watch for authentication changes
  watch(
    () => authStore.isAuthenticated,
    (authenticated) => {
      if (authenticated && !globalIsConnected) {
        connect()
      } else if (!authenticated) {
        disconnect()
      }
    },
    { immediate: true },
  )

  // Cleanup on unmount (only for the last component using this)
  onUnmounted(() => {
    // Note: We don't disconnect here because this is a global connection
    // that should persist across component mounts/unmounts.
    // Only disconnect when the user logs out (handled by the watcher above)
  })

  return {
    isConnected,
    connectionError,
    reconnect,
    disconnect,
  }
}
