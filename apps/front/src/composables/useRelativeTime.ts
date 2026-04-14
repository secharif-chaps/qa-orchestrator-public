import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Composable that provides locale-aware relative time formatting
 * using the native Intl.RelativeTimeFormat API.
 *
 * Returns a `formatRelativeTime` function that automatically uses
 * the current i18n locale, so output updates when the user switches language.
 */
export const useRelativeTime = () => {
  const { locale } = useI18n()

  const formatter = computed(() => new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }))

  const formatRelativeTime = (timestamp: string | Date): string => {
    const date = typeof timestamp === 'string' ? new Date(timestamp) : timestamp
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffSeconds = Math.floor(diffMs / 1000)

    if (diffSeconds < 60) return formatter.value.format(0, 'second') // "now" / "maintenant"

    const diffMinutes = Math.floor(diffSeconds / 60)
    if (diffMinutes < 60) return formatter.value.format(-diffMinutes, 'minute')

    const diffHours = Math.floor(diffMinutes / 60)
    if (diffHours < 24) return formatter.value.format(-diffHours, 'hour')

    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 7) return formatter.value.format(-diffDays, 'day')

    const diffWeeks = Math.floor(diffDays / 7)
    if (diffWeeks < 4) return formatter.value.format(-diffWeeks, 'week')

    const diffMonths = Math.floor(diffDays / 30)
    return formatter.value.format(-diffMonths, 'month')
  }

  return { formatRelativeTime }
}
