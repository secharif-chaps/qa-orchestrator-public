import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { useChatDateRef, useDateTime, useRelativeTimeRef } from './useDateTime'

const locale = ref('en-US')
const d = vi.fn((date: Date, format: string) => `[${format}:${date.toISOString()}]`)

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    d,
    locale,
  }),
}))

describe('useDateTime', () => {
  beforeEach(() => {
    locale.value = 'en-US'
    d.mockClear()
  })

  describe('formatDate', () => {
    it('delegates to vue-i18n d() with the requested named format', () => {
      const { formatDate } = useDateTime()
      const result = formatDate('2026-01-15T10:30:00Z', 'long')
      expect(d).toHaveBeenCalledWith(expect.any(Date), 'long')
      expect(result).toContain('long')
    })

    it('defaults to the "short" format', () => {
      const { formatDate } = useDateTime()
      formatDate(new Date('2026-01-15T10:30:00Z'))
      expect(d).toHaveBeenCalledWith(expect.any(Date), 'short')
    })

    it('returns empty string for falsy input', () => {
      const { formatDate } = useDateTime()
      expect(formatDate(null)).toBe('')
      expect(formatDate(undefined)).toBe('')
      expect(formatDate('')).toBe('')
    })

    it('returns empty string for invalid dates', () => {
      const { formatDate } = useDateTime()
      expect(formatDate('not a date')).toBe('')
    })
  })

  describe('formatRelativeTime', () => {
    beforeEach(() => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2026-04-23T12:00:00Z'))
    })
    afterEach(() => {
      vi.useRealTimers()
    })

    it('returns "now" for dates in the last minute', () => {
      const { formatRelativeTime } = useDateTime()
      expect(formatRelativeTime('2026-04-23T11:59:30Z')).toBe('now')
    })

    it('buckets minutes, hours, days, weeks, months, years', () => {
      const { formatRelativeTime } = useDateTime()
      expect(formatRelativeTime('2026-04-23T11:55:00Z')).toBe('5 minutes ago')
      expect(formatRelativeTime('2026-04-23T09:00:00Z')).toBe('3 hours ago')
      expect(formatRelativeTime('2026-04-21T12:00:00Z')).toBe('2 days ago')
      expect(formatRelativeTime('2026-04-02T12:00:00Z')).toBe('3 weeks ago')
      expect(formatRelativeTime('2026-01-23T12:00:00Z')).toBe('3 months ago')
      expect(formatRelativeTime('2024-04-23T12:00:00Z')).toBe('2 years ago')
    })

    it('uses calendar-exact months (February → same day in March is 1 month, not "4 weeks")', () => {
      const { formatRelativeTime } = useDateTime()
      vi.setSystemTime(new Date('2026-03-01T12:00:00Z'))
      // Feb 1 → Mar 1 is exactly 1 calendar month (28 days), should bucket as month
      expect(formatRelativeTime('2026-02-01T12:00:00Z')).toBe('last month')
      // Feb 2 → Mar 1 is 27 days and less than 1 full calendar month — still weeks
      expect(formatRelativeTime('2026-02-02T12:00:00Z')).toBe('3 weeks ago')
    })

    it('uses calendar-exact years (before anniversary rounds down)', () => {
      const { formatRelativeTime } = useDateTime()
      vi.setSystemTime(new Date('2026-04-23T12:00:00Z'))
      // Exactly 1 year before
      expect(formatRelativeTime('2025-04-23T12:00:00Z')).toBe('last year')
      // One day shy of 1 year
      expect(formatRelativeTime('2025-04-24T12:00:00Z')).toBe('11 months ago')
    })

    it('reflects the active i18n locale', () => {
      const { formatRelativeTime } = useDateTime()
      locale.value = 'fr-FR'
      expect(formatRelativeTime('2026-04-23T09:00:00Z')).toBe('il y a 3 heures')
    })

    it('returns empty string for falsy or invalid input', () => {
      const { formatRelativeTime } = useDateTime()
      expect(formatRelativeTime(null)).toBe('')
      expect(formatRelativeTime('garbage')).toBe('')
    })
  })

  describe('formatChatDate', () => {
    beforeEach(() => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2026-04-23T12:00:00Z'))
    })
    afterEach(() => {
      vi.useRealTimers()
    })

    it('uses "time" format when the date is today', () => {
      const { formatChatDate } = useDateTime()
      formatChatDate('2026-04-23T08:30:00Z')
      expect(d).toHaveBeenCalledWith(expect.any(Date), 'time')
    })

    it('uses "short" format when the date is another day', () => {
      const { formatChatDate } = useDateTime()
      formatChatDate('2026-04-20T08:30:00Z')
      expect(d).toHaveBeenCalledWith(expect.any(Date), 'short')
    })

    it('returns empty string for falsy input', () => {
      const { formatChatDate } = useDateTime()
      expect(formatChatDate(undefined)).toBe('')
    })
  })
})

describe('useChatDateRef', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    locale.value = 'en-US'
  })
  afterEach(() => {
    vi.useRealTimers()
  })

  it('switches from "time" to "short" when the day boundary is crossed', async () => {
    // Start just before midnight on 2026-04-23 local time
    vi.setSystemTime(new Date(2026, 3, 23, 23, 58))
    const date = new Date(2026, 3, 23, 23, 58)
    let label: string = ''

    const TestComponent = {
      setup() {
        const value = useChatDateRef(date)
        return () => {
          label = value.value
          return null
        }
      },
    }

    const { mount } = await import('@vue/test-utils')
    const wrapper = mount(TestComponent)
    expect(d).toHaveBeenLastCalledWith(expect.any(Date), 'time')
    expect(label).toContain('time')

    // Tick forward past midnight
    vi.advanceTimersByTime(5 * 60_000)
    vi.setSystemTime(new Date(2026, 3, 24, 0, 3))
    await nextTick()

    expect(d).toHaveBeenLastCalledWith(expect.any(Date), 'short')
    expect(label).toContain('short')
    wrapper.unmount()
  })
})

describe('useRelativeTimeRef', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-04-23T12:00:00Z'))
    locale.value = 'en-US'
  })
  afterEach(() => {
    vi.useRealTimers()
  })

  it('recomputes after the interval ticks', async () => {
    const date = ref('2026-04-23T11:59:30Z')
    let label: string = ''

    const TestComponent = {
      setup() {
        const value = useRelativeTimeRef(date)
        return () => {
          label = value.value
          return null
        }
      },
    }

    const { mount } = await import('@vue/test-utils')
    const wrapper = mount(TestComponent)
    expect(label).toBe('now')

    vi.advanceTimersByTime(65_000)
    vi.setSystemTime(new Date('2026-04-23T12:01:05Z'))
    await nextTick()

    expect(label).toBe('1 minute ago')
    wrapper.unmount()
  })
})
