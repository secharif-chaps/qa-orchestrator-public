import i18n, { getLocale } from '@/i18n'

/**
 * Format a timestamp as a locale-aware relative time string using Intl.RelativeTimeFormat.
 * Reads the current locale from the i18n singleton.
 *
 * Prefer the `useRelativeTime` composable inside Vue components for reactive locale support.
 *
 * @param timestamp - ISO 8601 timestamp string or Date object
 * @returns Human-readable relative time string (e.g., "3 hours ago", "il y a 3 heures")
 */
export function formatRelativeTime(timestamp: string | Date): string {
  const localeCode = getLocale()
  const rtf = new Intl.RelativeTimeFormat(localeCode, { numeric: 'auto' })

  const date = typeof timestamp === 'string' ? new Date(timestamp) : timestamp
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffSeconds = Math.floor(diffMs / 1000)

  if (diffSeconds < 60) return rtf.format(0, 'second')

  const diffMinutes = Math.floor(diffSeconds / 60)
  if (diffMinutes < 60) return rtf.format(-diffMinutes, 'minute')

  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) return rtf.format(-diffHours, 'hour')

  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return rtf.format(-diffDays, 'day')

  const diffWeeks = Math.floor(diffDays / 7)
  if (diffWeeks < 4) return rtf.format(-diffWeeks, 'week')

  const diffMonths = Math.floor(diffDays / 30)
  return rtf.format(-diffMonths, 'month')
}

/**
 * Format a date string to localized date and time
 * @param dateString - ISO 8601 date string
 * @returns Localized date and time string (e.g., "Jan 15, 2024, 10:30 AM" or "15 janv. 2024, 10:30")
 */
export function formatDateTime(dateString: string): string {
  if (!dateString) return i18n.global.t('common.na')
  const localeCode = getLocale()

  return new Date(dateString).toLocaleDateString(localeCode, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format a date string to localized date
 * @param dateString - ISO 8601 date string
 * @returns Localized date string (e.g., "1/15/2024" or "15/01/2024")
 */
export function formatDate(dateString: string): string {
  if (!dateString) return i18n.global.t('common.na')
  const localeCode = getLocale()

  return new Date(dateString).toLocaleDateString(localeCode)
}

/**
 * Format a date string to localized full date with month name
 * @param dateString - ISO 8601 date string
 * @returns Localized full date string (e.g., "February 3, 2026" or "3 février 2026")
 */
export function formatFullDate(dateString: string): string {
  if (!dateString) return i18n.global.t('common.na')
  const localeCode = getLocale()

  return new Date(dateString).toLocaleDateString(localeCode, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
