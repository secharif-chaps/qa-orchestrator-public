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
      return 'à l\'instant'
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
