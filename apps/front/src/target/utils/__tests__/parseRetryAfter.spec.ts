import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { parseRetryAfter } from '../parseRetryAfter'

function createMockResponse(retryAfter: string | null): Response {
  const headers = new Headers()
  if (retryAfter !== null) {
    headers.set('Retry-After', retryAfter)
  }
  return { headers } as Response
}

describe('parseRetryAfter', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-19T12:00:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns 60 when Retry-After header is missing', () => {
    const response = createMockResponse(null)
    expect(parseRetryAfter(response)).toBe(60)
  })

  it('parses Retry-After as seconds', () => {
    const response = createMockResponse('42')
    expect(parseRetryAfter(response)).toBe(42)
  })

  it('parses Retry-After as HTTP date', () => {
    const response = createMockResponse('Thu, 19 Mar 2026 12:00:30 GMT')
    expect(parseRetryAfter(response)).toBe(30)
  })

  it('returns 60 when Retry-After is 0', () => {
    const response = createMockResponse('0')
    expect(parseRetryAfter(response)).toBe(60)
  })

  it('returns 60 when Retry-After is negative', () => {
    const response = createMockResponse('-5')
    expect(parseRetryAfter(response)).toBe(60)
  })

  it('returns 60 when Retry-After date is in the past', () => {
    const response = createMockResponse('Thu, 19 Mar 2026 11:59:00 GMT')
    expect(parseRetryAfter(response)).toBe(60)
  })

  it('returns 60 when Retry-After is not a valid value', () => {
    const response = createMockResponse('invalid')
    expect(parseRetryAfter(response)).toBe(60)
  })
})
