import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useRateLimit } from '../useRateLimit'

describe('useRateLimit', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-19T12:00:00Z'))

    // Reset global state between tests
    const { setRateLimit } = useRateLimit()
    vi.advanceTimersByTime(999_999)
    // Force clear any lingering state
    setRateLimit(0)
    vi.advanceTimersByTime(1)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('is not rate limited by default', () => {
    const { isRateLimited, getRemainingSeconds } = useRateLimit()

    expect(isRateLimited.value).toBe(false)
    expect(getRemainingSeconds()).toBe(0)
  })

  it('becomes rate limited after setRateLimit', () => {
    const { isRateLimited, getRemainingSeconds, setRateLimit } = useRateLimit()

    setRateLimit(30)

    expect(isRateLimited.value).toBe(true)
    expect(getRemainingSeconds()).toBe(30)
  })

  it('counts down remaining seconds', () => {
    const { getRemainingSeconds, setRateLimit } = useRateLimit()

    setRateLimit(30)
    vi.advanceTimersByTime(10_000)

    expect(getRemainingSeconds()).toBe(20)
  })

  it('auto-clears after cooldown expires', () => {
    const { isRateLimited, getRemainingSeconds, setRateLimit } = useRateLimit()

    setRateLimit(30)
    vi.advanceTimersByTime(30_000)

    expect(isRateLimited.value).toBe(false)
    expect(getRemainingSeconds()).toBe(0)
  })

  it('extends cooldown when setRateLimit is called again', () => {
    const { getRemainingSeconds, setRateLimit } = useRateLimit()

    setRateLimit(30)
    vi.advanceTimersByTime(10_000)
    setRateLimit(60)

    expect(getRemainingSeconds()).toBe(60)
  })

  it('shares state across multiple useRateLimit calls', () => {
    const first = useRateLimit()
    const second = useRateLimit()

    first.setRateLimit(30)

    expect(second.isRateLimited.value).toBe(true)
    expect(second.getRemainingSeconds()).toBe(30)
  })
})
