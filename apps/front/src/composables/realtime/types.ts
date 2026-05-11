/**
 * Realtime push subscription types — agnostic to the underlying transport.
 *
 * Two transports coexist in the codebase:
 * - Mercure: a hub-based pub/sub used by Target. Channels are discovered from
 *   the `Link` header of any REST response (`rel="mercure"` + `rel="topic"`),
 *   and consumed via `EventSource`.
 * - SSE direct: a FastAPI `StreamingResponse` endpoint used by Screen. The
 *   client connects to a known URL with `Authorization` headers via
 *   `fetch` + `ReadableStream`.
 *
 * Callers describe what they want to subscribe to with a `PushDescriptor`,
 * and `useRealtime()` dispatches to the matching backend.
 */

export interface MercurePushDescriptor {
  type: 'mercure'
  hubUrl: string
  topics: string[]
}

export interface SsePushDescriptor {
  type: 'sse'
  endpoint: string
  lastEventId?: string
}

export type PushDescriptor = MercurePushDescriptor | SsePushDescriptor

export interface RealtimeHandlers<T = unknown> {
  onMessage: (data: T) => void
  onError?: (error: Error) => void
  onConnect?: () => void
  onDisconnect?: () => void
  retryDelay?: number
  maxRetries?: number
}
