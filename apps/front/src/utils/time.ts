import i18n from '@/i18n'

/**
 * Format a timestamp as a relative time string
 * @param timestamp - ISO 8601 timestamp string or Date object
 * @param locale - Language locale ('en' or 'fr')
 * @returns Human-readable relative time string (e.g., "3 days ago", "just now")
 */
export function formatRelativeTime(timestamp: string | Date, locale: 'en' | 'fr' = 'fr'): string {
  const date = typeof timestamp === 'string' ? new Date(timestamp) : timestamp
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()

  // Convert to different time units
  const diffSeconds = Math.floor(diffMs / 1000)
  const diffMinutes = Math.floor(diffSeconds / 60)
  const diffHours = Math.floor(diffMinutes / 60)
  const diffDays = Math.floor(diffHours / 24)
  const diffWeeks = Math.floor(diffDays / 7)
  const diffMonths = Math.floor(diffDays / 30)

  // French translations
  if (locale === 'fr') {
    if (diffSeconds < 60) {
      return "à l'instant"
    } else if (diffMinutes < 60) {
      return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''}`
    } else if (diffHours < 24) {
      return `${diffHours} heure${diffHours > 1 ? 's' : ''}`
    } else if (diffDays < 7) {
      return `${diffDays} jour${diffDays > 1 ? 's' : ''}`
    } else if (diffWeeks < 4) {
      return `${diffWeeks} semaine${diffWeeks > 1 ? 's' : ''}`
    } else {
      return `${diffMonths} mois`
    }
  }

  // English (default)
  if (diffSeconds < 60) {
    return 'just now'
  } else if (diffMinutes < 60) {
    return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`
  } else if (diffHours < 24) {
    return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
  } else if (diffDays < 7) {
    return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`
  } else if (diffWeeks < 4) {
    return `${diffWeeks} week${diffWeeks > 1 ? 's' : ''} ago`
  } else {
    return `${diffMonths} month${diffMonths > 1 ? 's' : ''} ago`
  }
}

/**
 * Format a date string to localized date and time
 * @param dateString - ISO 8601 date string
 * @returns Localized date and time string (e.g., "Jan 15, 2024, 10:30 AM" or "15 janv. 2024, 10:30")
 */
export function formatDateTime(dateString: string): string {
  if (!dateString) return i18n.global.t('common.na')
  const localeCode = i18n.global.locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'

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
  const localeCode = i18n.global.locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'

  return new Date(dateString).toLocaleDateString(localeCode)
}

/**
 * Format a date string to localized full date with month name
 * @param dateString - ISO 8601 date string
 * @returns Localized full date string (e.g., "February 3, 2026" or "3 février 2026")
 */
export function formatFullDate(dateString: string): string {
  if (!dateString) return i18n.global.t('common.na')
  const localeCode = i18n.global.locale.value === 'fr-FR' ? 'fr-FR' : 'en-US'

  return new Date(dateString).toLocaleDateString(localeCode, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
