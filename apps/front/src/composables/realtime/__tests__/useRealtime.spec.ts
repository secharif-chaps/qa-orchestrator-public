/**
 * Tests for src/composables/realtime/useRealtime.ts.
 *
 * useRealtime is a thin dispatcher: it routes a PushDescriptor to the right
 * backend (Mercure today; SSE direct reserved).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

// ── Mocks ────────────────────────────────────────────────────────────────────

const mockMercureSubscribe = vi.fn(async () => undefined)
const mockMercureUnsubscribe = vi.fn(async () => undefined)
const mockMercureUnsubscribeAll = vi.fn(async () => undefined)
const mockIsConnected = { value: true }
const mockActiveSubscriptions = { value: [] as unknown[] }

vi.mock('@/composables/realtime/useMercure', () => ({
  useMercure: () => ({
    subscribe: mockMercureSubscribe,
    unsubscribe: mockMercureUnsubscribe,
    unsubscribeAll: mockMercureUnsubscribeAll,
    isConnected: mockIsConnected,
    activeSubscriptions: mockActiveSubscriptions,
  }),
}))

import { useRealtime } from '@/composables/realtime/useRealtime'
import type { RealtimeHandlers } from '@/composables/realtime/types'

beforeEach(() => {
  mockMercureSubscribe.mockClear()
  mockMercureUnsubscribe.mockClear()
  mockMercureUnsubscribeAll.mockClear()
})

// ─────────────────────────────────────────────────────────────────────────────
// subscribe()
// ─────────────────────────────────────────────────────────────────────────────

describe('subscribe', () => {
  it('routes a Mercure descriptor to useMercure.subscribe with hubUrl, topics, mapped handlers, key', async () => {
    const handlers: RealtimeHandlers<{ id: string }> = {
      onMessage: vi.fn(),
      onError: vi.fn(),
      onConnect: vi.fn(),
      onDisconnect: vi.fn(),
      retryDelay: 500,
      maxRetries: 7,
    }

    await useRealtime().subscribe(
      {
        type: 'mercure',
        hubUrl: 'https://hub.example.com/.well-known/mercure',
        topics: ['/topics/foo', '/topics/bar'],
      },
      handlers,
      'my-key',
    )

    expect(mockMercureSubscribe).toHaveBeenCalledTimes(1)
    const [hubUrl, topics, mappedHandlers, key] = mockMercureSubscribe.mock.calls[0] as unknown as [
      string,
      string[],
      Record<string, unknown>,
      string,
    ]
    expect(hubUrl).toBe('https://hub.example.com/.well-known/mercure')
    expect(topics).toEqual(['/topics/foo', '/topics/bar'])
    expect(key).toBe('my-key')
    // useRealtime renames onMessage → onUpdate to match the legacy useMercure API.
    expect(mappedHandlers).toMatchObject({
      onUpdate: handlers.onMessage,
      onError: handlers.onError,
      onConnect: handlers.onConnect,
      onDisconnect: handlers.onDisconnect,
      retryDelay: 500,
      maxRetries: 7,
    })
  })

  it('throws when given an SSE descriptor (backend not implemented yet)', async () => {
    await expect(
      useRealtime().subscribe(
        { type: 'sse', endpoint: '/tasks/events/stream' },
        { onMessage: vi.fn() },
        'sse-key',
      ),
    ).rejects.toThrow(/SSE direct backend is not implemented yet/)

    expect(mockMercureSubscribe).not.toHaveBeenCalled()
  })

  it('does not invoke onError handler when the SSE backend throws (caller handles it)', async () => {
    const onError = vi.fn()
    await expect(
      useRealtime().subscribe(
        { type: 'sse', endpoint: '/tasks/events/stream' },
        { onMessage: vi.fn(), onError },
        'sse-key',
      ),
    ).rejects.toThrow()

    expect(onError).not.toHaveBeenCalled()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// subscribeFromHeaders()
// ─────────────────────────────────────────────────────────────────────────────

describe('subscribeFromHeaders', () => {
  let handlers: RealtimeHandlers

  beforeEach(() => {
    handlers = { onMessage: vi.fn() }
  })

  it('no-ops silently when the Link header is missing', async () => {
    const headers = new Headers({ 'content-type': 'application/json' })

    await useRealtime().subscribeFromHeaders(headers, 'k', handlers)

    expect(mockMercureSubscribe).not.toHaveBeenCalled()
  })

  it('no-ops when the Link header has no rel="mercure" descriptor', async () => {
    const headers = new Headers({
      Link: '</topics/foo>; rel="topic"', // rel="topic" only, no mercure hub
    })

    await useRealtime().subscribeFromHeaders(headers, 'k', handlers)

    expect(mockMercureSubscribe).not.toHaveBeenCalled()
  })

  it('subscribes via Mercure when Link advertises hub + topics', async () => {
    const headers = new Headers({
      Link:
        '<https://hub/.well-known/mercure>; rel="mercure", ' +
        '</topics/wf-1>; rel="topic", ' +
        '</topics/wf-2>; rel="topic"',
    })

    await useRealtime().subscribeFromHeaders(headers, 'wf-key', handlers)

    expect(mockMercureSubscribe).toHaveBeenCalledTimes(1)
    const [hubUrl, topics, , key] = mockMercureSubscribe.mock.calls[0] as unknown as [
      string,
      string[],
      unknown,
      string,
    ]
    expect(hubUrl).toBe('https://hub/.well-known/mercure')
    expect(topics).toEqual(['/topics/wf-1', '/topics/wf-2'])
    expect(key).toBe('wf-key')
  })

  it('propagates the typed onMessage handler via onUpdate', async () => {
    const onMessage = vi.fn<(data: { id: string }) => void>()
    const headers = new Headers({
      Link: '<https://hub/.well-known/mercure>; rel="mercure", </topics/x>; rel="topic"',
    })

    await useRealtime().subscribeFromHeaders<{ id: string }>(headers, 'k', { onMessage })

    const [, , mapped] = mockMercureSubscribe.mock.calls[0] as unknown as [
      string,
      string[],
      { onUpdate: typeof onMessage },
    ]
    expect(mapped.onUpdate).toBe(onMessage)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Re-exports from useMercure
// ─────────────────────────────────────────────────────────────────────────────

describe('re-exports', () => {
  it('exposes unsubscribe from useMercure', async () => {
    await useRealtime().unsubscribe('some-key')

    expect(mockMercureUnsubscribe).toHaveBeenCalledWith('some-key')
  })

  it('exposes unsubscribeAll from useMercure', async () => {
    await useRealtime().unsubscribeAll()

    expect(mockMercureUnsubscribeAll).toHaveBeenCalledTimes(1)
  })

  it('exposes isConnected and activeSubscriptions from useMercure', () => {
    const r = useRealtime()

    expect(r.isConnected).toBe(mockIsConnected)
    expect(r.activeSubscriptions).toBe(mockActiveSubscriptions)
  })
})
