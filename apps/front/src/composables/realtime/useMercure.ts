import { useMercureStore } from '@/stores/mercure'
import { apiClient } from '@/api/client'
import i18n from '@/i18n'
import { useOnline } from '@vueuse/core'
import { storeToRefs } from 'pinia'
import { ref, watch } from 'vue'
import { useToast } from '@/composables/useToast'

/**
 * Simple cookie helper to replace Nuxt's useCookie auto-import.
 * Returns an object with a reactive-like .value property for getting/setting a cookie.
 */
function useCookie(
  name: string,
  options?: { path?: string; secure?: boolean; sameSite?: string; maxAge?: number },
) {
  const getCookie = (): string | null => {
    const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`))
    return match ? decodeURIComponent(match[2]) : null
  }

  const setCookie = (val: string | null) => {
    if (val === null) {
      document.cookie = `${name}=; path=${options?.path ?? '/'}; max-age=0`
    } else {
      const parts = [`${name}=${encodeURIComponent(val)}`]
      if (options?.path) parts.push(`path=${options.path}`)
      if (options?.maxAge !== undefined) parts.push(`max-age=${options.maxAge}`)
      if (options?.secure) parts.push('secure')
      if (options?.sameSite) parts.push(`samesite=${options.sameSite}`)
      document.cookie = parts.join('; ')
    }
  }

  return {
    get value(): string | null {
      return getCookie()
    },
    set value(val: string | null) {
      setCookie(val)
    },
  }
}

export interface MercureSubscription {
  url: string
  topics: string[]
  subscribeKey: string
  options: MercureOptions<unknown>
}

export interface MercureOptions<T> {
  onUpdate: (data: T) => void
  onError?: (error: Error) => void
  onConnect?: () => void
  onDisconnect?: () => void
  retryDelay?: number
  maxRetries?: number
}

interface MercureTokenResponse {
  token: string
  expires_at: number
}

const CONNECTION_LOST_DELAY_MS = 3000

// Global state shared across all useMercure instances
const eventSources = new Map<string, EventSource>()
const retryCounts = new Map<string, number>()
const isOnline = useOnline()
let connectionTimer: ReturnType<typeof setTimeout> | null = null
// Guards the online/offline watcher so it is registered only once. Without
// this, every call to useMercure() (and apiClient triggers many) would attach
// a new watcher, leaking memory and multiplying resubscribeAll() at network
// recovery.
let onlineWatcherInitialized = false

export function useMercure() {
  const mercureStore = useMercureStore()
  const { activeSubscriptions, hasShownDisconnectToast } = storeToRefs(mercureStore)
  const isConnected = ref(false)
  const toast = useToast()
  // Use the global i18n instance: useMercure can be invoked from a promise chain
  // (e.g. apiClient.request after the fetch resolves), where Vue's setup-only
  // composables like useI18n() throw. The global instance works anywhere.
  const t = i18n.global.t

  // Resubscribe all previously active subscriptions
  const resubscribeAll = async () => {
    const subscriptionsToReconnect = [...activeSubscriptions.value]
    for (const sub of subscriptionsToReconnect) {
      await subscribe(sub.url, sub.topics, sub.options, sub.subscribeKey)
    }
  }

  // Proactive network status detection — registered once at first composable
  // invocation. The closure captures Pinia refs / module-level state, all of
  // which are singletons, so a single watcher serves every consumer correctly.
  if (!onlineWatcherInitialized) {
    onlineWatcherInitialized = true
    watch(isOnline, async (online) => {
      if (activeSubscriptions.value.length === 0) {
        return
      }

      if (!online) {
        handleConnectionLost()
      } else {
        for (const eventSource of eventSources.values()) {
          eventSource.close()
        }
        eventSources.clear()
        retryCounts.clear()

        await resubscribeAll()
      }
    })
  }

  function handleConnectionLost() {
    if (connectionTimer !== null) {
      clearTimeout(connectionTimer)
    }

    // Delay showing the banner and toast to avoid flickering on transient errors
    connectionTimer = setTimeout(() => {
      connectionTimer = null
      mercureStore.setDisconnected()
      toast.warning(t('target.watchFiles.chat.connection_lost'))
      hasShownDisconnectToast.value = true
    }, CONNECTION_LOST_DELAY_MS)
  }

  function handleConnectionRestored() {
    if (connectionTimer !== null) {
      clearTimeout(connectionTimer)
    }

    // Delay hiding the banner and showing the toast to mirror the lost behavior
    connectionTimer = setTimeout(() => {
      connectionTimer = null
      mercureStore.setConnected()

      if (hasShownDisconnectToast.value) {
        toast.info(t('target.watchFiles.chat.connection_restored'))
        hasShownDisconnectToast.value = false
      }
    }, CONNECTION_LOST_DELAY_MS)
  }

  function makeUrl(urlString: string, topics: string[]): URL {
    const url = new URL(urlString)
    for (const topic of topics) {
      url.searchParams.append('topic', topic)
    }
    return url
  }

  async function getMercureToken(refreshForce: boolean = false): Promise<string | null> {
    const mercureCookie = useCookie('mercureAuthorization', {
      path: '/',
      secure: true,
      sameSite: 'lax',
      maxAge: 3600, // 1 hour in seconds
    })

    // Check if we have a valid cookie
    if (mercureCookie.value && refreshForce === false) {
      return mercureCookie.value
    }

    try {
      const data = await apiClient.get<MercureTokenResponse>('/security/real-time/token', {
        silent: true,
      })

      // Store token in cookie
      mercureCookie.value = data.token

      return data.token
    } catch (error) {
      console.error('Failed to fetch Mercure token:', error)
      return null
    }
  }

  function waitForOnline(): Promise<void> {
    return new Promise((resolve) => {
      if (navigator.onLine) {
        resolve()
        return
      }
      const handleOnline = () => {
        window.removeEventListener('online', handleOnline)
        resolve()
      }
      window.addEventListener('online', handleOnline)
    })
  }

  async function subscribe<T = unknown>(
    url: string,
    topics: string[],
    options: MercureOptions<T>,
    subscribeKey: string,
  ): Promise<void> {
    // Initialize retry count for this subscription if it doesn't exist
    if (!retryCounts.has(subscribeKey)) {
      retryCounts.set(subscribeKey, 0)
    }

    const handleError = async (error: Error) => {
      if (options.onError) {
        options.onError(error)
      }

      const retryCount = retryCounts.get(subscribeKey) || 0
      const maxRetries = options.maxRetries ?? 5
      const initialDelay = options.retryDelay ?? 1000 // 1 second
      const maxDelay = 60000 // 60 seconds
      const logThreshold = 3 // Only log after this many failures

      const newRetryCount = retryCount + 1
      retryCounts.set(subscribeKey, newRetryCount)

      // Wait for network to be online before retrying
      if (!navigator.onLine) {
        if (newRetryCount >= logThreshold) {
          console.warn(`Mercure: waiting for network to come back online (${subscribeKey})`)
        }
        await waitForOnline()
        // Reset retry count after coming back online
        retryCounts.set(subscribeKey, 0)
        subscribe<T>(url, topics, options, subscribeKey)
        return
      }

      let delay: number

      if (retryCount < maxRetries) {
        // First maxRetries attempts: fixed delay (1s each)
        // Retry 1-5: 1s
        delay = initialDelay
      } else {
        // After maxRetries: exponential backoff starts
        // Retry 6: 2s (1s * 2^1), Retry 7: 4s (1s * 2^2), etc.
        const exponentialPower = retryCount - maxRetries + 1
        const calculatedDelay = initialDelay * Math.pow(2, exponentialPower)
        delay = Math.min(calculatedDelay, maxDelay)
      }

      // Only log warnings after multiple failures to reduce noise
      if (newRetryCount >= logThreshold) {
        console.warn(
          `Mercure connection failed, retrying in ${delay}ms... (attempt ${newRetryCount}, ${subscribeKey})`,
        )
      }

      setTimeout(() => {
        subscribe<T>(url, topics, options, subscribeKey)
      }, delay)
    }

    try {
      // Get Mercure JWT token for private subscription
      const token = await getMercureToken()

      if (!token) {
        console.warn('Failed to get Mercure token, skipping subscription')
        return
      }

      // Create URL with topics and authorization
      const mercureUrl = makeUrl(url, topics)

      const eventSource = new EventSource(mercureUrl.toString(), {
        withCredentials: true,
      })
      eventSources.set(mercureUrl.toString(), eventSource)

      eventSource.onopen = () => {
        isConnected.value = true
        // Reset retry count on successful connection
        retryCounts.delete(subscribeKey)
        handleConnectionRestored()
        options.onConnect?.()
      }

      eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data)
          options.onUpdate(data as T)
        } catch (err) {
          console.error('Failed to parse Mercure message:', err)
        }
      }

      eventSource.onerror = () => {
        handleConnectionLost()
        handleError(new Error('EventSource error'))
        eventSource.close()
        eventSources.delete(mercureUrl.toString())
        options.onDisconnect?.()
      }

      const findSubscription = activeSubscriptions.value.find(
        (sub) => sub.subscribeKey === subscribeKey,
      )
      if (!findSubscription) {
        activeSubscriptions.value.push({
          url,
          topics,
          subscribeKey,
          options: options as MercureOptions<unknown>,
        })
      }
    } catch (error) {
      handleError(error as Error)
    }
  }

  async function unsubscribe(subscribeKey: string): Promise<void> {
    const subscription = activeSubscriptions.value.find((sub) => sub.subscribeKey === subscribeKey)
    if (!subscription) {
      console.warn('Subscription not found, skipping unsubscribe')
      return
    }
    const mercureUrl = makeUrl(subscription.url, subscription.topics)
    const eventSource = eventSources.get(mercureUrl.toString())

    if (eventSource) {
      eventSource.close()
      eventSources.delete(mercureUrl.toString())
    }

    // Clear retry count when unsubscribing
    retryCounts.delete(subscribeKey)

    activeSubscriptions.value = activeSubscriptions.value.filter(
      (sub) => sub.subscribeKey !== subscribeKey,
    )

    if (!activeSubscriptions.value.length) {
      isConnected.value = false
    }
  }

  async function unsubscribeAll(): Promise<void> {
    for (const eventSource of eventSources.values()) {
      eventSource.close()
    }
    eventSources.clear()
    retryCounts.clear()
    activeSubscriptions.value = []
    isConnected.value = false
  }

  function clearMercureCookie(): void {
    const mercureCookie = useCookie('mercureAuthorization', {
      path: '/',
      secure: true,
      sameSite: 'lax',
      maxAge: 0,
    })
    mercureCookie.value = null
  }

  async function resetMercure(): Promise<void> {
    await unsubscribeAll()
    await clearMercureCookie()
  }

  return {
    getMercureToken,
    subscribe,
    unsubscribe,
    unsubscribeAll,
    isConnected,
    activeSubscriptions,
    resetMercure,
  }
}
