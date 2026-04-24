import { computed, onMounted, onUnmounted, ref, toValue, type MaybeRefOrGetter } from 'vue'
import { useI18n } from 'vue-i18n'

export type DateInput = string | number | Date | null | undefined
export type DateTimeFormat = 'short' | 'long' | 'time' | 'eventDate' | 'eventDateTime' | 'fullDate'

const toDate = (input: DateInput): Date | null => {
  if (input === null || input === undefined) return null
  const date = input instanceof Date ? input : new Date(input)
  return Number.isNaN(date.getTime()) ? null : date
}

const isSameDay = (a: Date, b: Date): boolean =>
  a.getFullYear() === b.getFullYear() &&
  a.getMonth() === b.getMonth() &&
  a.getDate() === b.getDate()

// Calendar-exact month count between two dates (past → now). Avoids the
// 28/29/30/31-day drift of a naive `diffDays / 30`.
const diffInCalendarMonths = (past: Date, now: Date): number => {
  let months = (now.getFullYear() - past.getFullYear()) * 12 + (now.getMonth() - past.getMonth())
  if (now.getDate() < past.getDate()) months--
  return months
}

const diffInCalendarYears = (past: Date, now: Date): number => {
  let years = now.getFullYear() - past.getFullYear()
  const beforeAnniversary =
    now.getMonth() < past.getMonth() ||
    (now.getMonth() === past.getMonth() && now.getDate() < past.getDate())
  if (beforeAnniversary) years--
  return years
}

/**
 * Locale-aware date/time formatting backed by vue-i18n's `d()` and
 * `Intl.RelativeTimeFormat`. Output reacts to the active i18n locale.
 *
 * Named formats (`short`, `long`, `time`, `eventDate`, `eventDateTime`, `fullDate`)
 * are declared in src/i18n/datetime-formats.ts.
 */
export const useDateTime = () => {
  const { d, locale } = useI18n()

  const formatDate = (date: DateInput, format: DateTimeFormat = 'short'): string => {
    const resolved = toDate(date)
    return resolved ? d(resolved, format) : ''
  }

  const formatRelativeTime = (date: DateInput): string => {
    const resolved = toDate(date)
    if (!resolved) return ''

    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
    const diffSeconds = Math.floor((Date.now() - resolved.getTime()) / 1000)

    if (diffSeconds < 60) return rtf.format(0, 'second')

    const diffMinutes = Math.floor(diffSeconds / 60)
    if (diffMinutes < 60) return rtf.format(-diffMinutes, 'minute')

    const diffHours = Math.floor(diffMinutes / 60)
    if (diffHours < 24) return rtf.format(-diffHours, 'hour')

    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 7) return rtf.format(-diffDays, 'day')

    const diffWeeks = Math.floor(diffDays / 7)
    if (diffWeeks < 4) return rtf.format(-diffWeeks, 'week')

    const now = new Date()
    const diffMonths = diffInCalendarMonths(resolved, now)
    if (diffMonths < 12) return rtf.format(-diffMonths, 'month')

    return rtf.format(-diffInCalendarYears(resolved, now), 'year')
  }

  const formatChatDate = (date: DateInput): string => {
    const resolved = toDate(date)
    if (!resolved) return ''
    return d(resolved, isSameDay(resolved, new Date()) ? 'time' : 'short')
  }

  return { formatDate, formatRelativeTime, formatChatDate }
}

const useTick = (intervalMs: number) => {
  const tick = ref(0)
  let intervalId: ReturnType<typeof setInterval> | null = null

  onMounted(() => {
    intervalId = setInterval(() => {
      tick.value++
    }, intervalMs)
  })

  onUnmounted(() => {
    if (intervalId) clearInterval(intervalId)
  })

  return tick
}

/**
 * Opt-in auto-refreshing relative-time ref. Ticks every `intervalMs` so labels
 * like "5 minutes ago" stay fresh. Only use it where the value is displayed long
 * enough to drift — elsewhere call `formatRelativeTime` directly.
 */
export const useRelativeTimeRef = (date: MaybeRefOrGetter<DateInput>, intervalMs = 60_000) => {
  const { formatRelativeTime } = useDateTime()
  const tick = useTick(intervalMs)

  return computed(() => {
    void tick.value
    return formatRelativeTime(toValue(date))
  })
}

/**
 * Opt-in auto-refreshing chat-date ref. Needed over `formatChatDate` whenever the
 * host component stays mounted across a day boundary — otherwise a message sent
 * at 23:58 would keep showing only the time well into the next day.
 */
export const useChatDateRef = (date: MaybeRefOrGetter<DateInput>, intervalMs = 60_000) => {
  const { formatChatDate } = useDateTime()
  const tick = useTick(intervalMs)

  return computed(() => {
    void tick.value
    return formatChatDate(toValue(date))
  })
}
