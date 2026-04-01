import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export interface TimeDisplayOptions {
  /**
   * Automatically update display every minute
   * @default true
   */
  autoUpdate?: boolean
  /**
   * Threshold in hours to consider a date as "old"
   * Beyond this threshold, display the full date
   * @default 24
   */
  oldDateThresholdHours?: number
}

export function useTimeDisplay(options: TimeDisplayOptions = {}) {
  const { autoUpdate = true, oldDateThresholdHours = 24 } = options

  const { d, t } = useI18n()
  const currentTime = ref(new Date())
  let intervalId: ReturnType<typeof setInterval> | null = null

  // Update current time every minute if autoUpdate is enabled
  onMounted(() => {
    if (autoUpdate) {
      intervalId = setInterval(() => {
        currentTime.value = new Date()
      }, 60000) // 60 seconds
    }
  })

  onUnmounted(() => {
    if (intervalId) {
      clearInterval(intervalId)
    }
  })

  /**
   * Format a date according to business rules
   */
  const formatTime = (dateString: string, format: string = 'short'): string => {
    try {
      const targetDate = new Date(dateString)
      const now = currentTime.value
      const diffMs = now.getTime() - targetDate.getTime()
      const diffMinutes = Math.floor(diffMs / (1000 * 60))
      const diffHours = Math.floor(diffMs / (1000 * 60 * 60))

      // Check if it's the same day
      const isSameDay = targetDate.toDateString() === now.toDateString()

      // If the difference is greater than the defined threshold, display full date
      if (diffHours >= oldDateThresholdHours) {
        return d(targetDate, format)
      }

      // Relative display for recent dates
      if (diffMinutes < 1) {
        return t('common.composables.useTimeDisplay.justNow')
      } else if (diffMinutes < 60) {
        return t('common.composables.useTimeDisplay.minutesAgo', { count: diffMinutes })
      } else if (isSameDay) {
        return t('common.composables.useTimeDisplay.hoursAgo', { count: diffHours })
      } else {
        // For dates yesterday or day before but within threshold
        return d(targetDate, format)
      }
    } catch (error) {
      console.warn('Error formatting date:', error)
      return t('common.composables.useTimeDisplay.invalidDate')
    }
  }

  /**
   * Format a date reactively
   */
  const formatTimeReactive = (dateString: string, format: string = 'short') => {
    return computed(() => formatTime(dateString, format))
  }

  /**
   * Check if a date is considered recent
   */
  const isRecentDate = (dateString: string): boolean => {
    try {
      const targetDate = new Date(dateString)
      const now = currentTime.value
      const diffHours = Math.floor((now.getTime() - targetDate.getTime()) / (1000 * 60 * 60))
      return diffHours < oldDateThresholdHours
    } catch {
      return false
    }
  }

  /**
   * Get the difference in minutes between now and the given date
   */
  const getMinutesDiff = (dateString: string): number => {
    try {
      const targetDate = new Date(dateString)
      const now = currentTime.value
      return Math.floor((now.getTime() - targetDate.getTime()) / (1000 * 60))
    } catch {
      return -1
    }
  }

  return {
    formatTime,
    formatTimeReactive,
    isRecentDate,
    getMinutesDiff,
    currentTime: computed(() => currentTime.value),
  }
}
