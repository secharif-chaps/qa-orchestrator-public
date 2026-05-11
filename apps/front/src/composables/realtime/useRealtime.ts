/**
 * Transport-agnostic realtime subscription entry point.
 *
 * Dispatches to the right backend based on `descriptor.type`. Today only
 * Mercure is implemented; SSE direct is reserved for when Screen needs
 * push-per-resource (currently it uses a global stream via useTaskEvents).
 */
import { useMercure } from '@/composables/realtime/useMercure'
import type {
  MercurePushDescriptor,
  PushDescriptor,
  RealtimeHandlers,
} from '@/composables/realtime/types'

// Parses an HTTP `Link` header and returns a Mercure descriptor when both a
// hub URL (`rel="mercure"`) and at least one topic (`rel="topic"`) are present.
export function parseMercureFromLink(linkHeader: string | null): MercurePushDescriptor | undefined {
  if (!linkHeader) return undefined

  const hubMatch = linkHeader.match(/<([^>]+)>;\s*rel="mercure"/)
  const hubUrl = hubMatch?.[1]
  if (!hubUrl) return undefined

  const topicRegex = /<([^>]+)>;\s*rel="topic"/g
  const topics: string[] = []
  let topicMatch
  while ((topicMatch = topicRegex.exec(linkHeader)) !== null) {
    if (topicMatch[1]) topics.push(topicMatch[1])
  }

  return topics.length > 0 ? { type: 'mercure', hubUrl, topics } : undefined
}

export function useRealtime() {
  const mercure = useMercure()

  async function subscribe<T>(
    descriptor: PushDescriptor,
    handlers: RealtimeHandlers<T>,
    key: string,
  ): Promise<void> {
    if (descriptor.type === 'mercure') {
      return subscribeMercure<T>(descriptor, handlers, key)
    }
    // SSE direct: not yet wired. Throw a clear error so callers know what's
    // missing rather than silently no-op'ing. Implement when first needed.
    throw new Error(
      `useRealtime: SSE direct backend is not implemented yet (endpoint=${descriptor.endpoint})`,
    )
  }

  async function subscribeMercure<T>(
    descriptor: MercurePushDescriptor,
    handlers: RealtimeHandlers<T>,
    key: string,
  ): Promise<void> {
    return mercure.subscribe<T>(
      descriptor.hubUrl,
      descriptor.topics,
      {
        onUpdate: handlers.onMessage,
        onError: handlers.onError,
        onConnect: handlers.onConnect,
        onDisconnect: handlers.onDisconnect,
        retryDelay: handlers.retryDelay,
        maxRetries: handlers.maxRetries,
      },
      key,
    )
  }

  // Discovers a push channel from response headers and subscribes to it.
  // No-ops silently when no channel is advertised — callers can blindly
  // request a subscription on any endpoint without having to check first.
  async function subscribeFromHeaders<T>(
    headers: Headers,
    key: string,
    handlers: RealtimeHandlers<T>,
  ): Promise<void> {
    const descriptor = parseMercureFromLink(headers.get('Link'))
    if (!descriptor) return
    return subscribe<T>(descriptor, handlers, key)
  }

  return {
    subscribe,
    subscribeFromHeaders,
    unsubscribe: mercure.unsubscribe,
    unsubscribeAll: mercure.unsubscribeAll,
    isConnected: mercure.isConnected,
    activeSubscriptions: mercure.activeSubscriptions,
  }
}
