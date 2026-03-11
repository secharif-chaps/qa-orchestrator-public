import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Composable for contextual date display in chat messages
 * - Shows time only if message is from today
 * - Shows date only if message is from another day
 * - Always provides full date+time for tooltip
 */
export function useChatDateDisplay() {
  const { d } = useI18n()
  const currentTime = ref(new Date())
  let intervalId: ReturnType<typeof setInterval>

  // Update current time every minute to handle day changes
  onMounted(() => {
    intervalId = setInterval(() => {
      currentTime.value = new Date()
    }, 60000) // 60 seconds
  })

  onUnmounted(() => {
    if (intervalId) {
      clearInterval(intervalId)
    }
  })

  /**
   * Check if a date is from today
   */
  const isToday = (date: Date): boolean => {
    const now = currentTime.value
    return (
      date.getDate() === now.getDate() &&
      date.getMonth() === now.getMonth() &&
      date.getFullYear() === now.getFullYear()
    )
  }

  /**
   * Format date contextually for display
   * - Returns time only if date is today
   * - Returns date only if date is from another day
   */
  const formatContextualDate = (dateString: string): string => {
    try {
      const date = new Date(dateString)
      if (isToday(date)) {
        // Show time only for today's messages
        return d(date, 'time')
      } else {
        // Show date only for other days
        return d(date, 'short')
      }
    } catch (error) {
      console.warn('Error formatting contextual date:', error)
      return d(new Date(dateString), 'short')
    }
  }

  /**
   * Format full date and time for tooltip
   * Always returns date + time in long format
   */
  const formatFullDateTime = (dateString: string): string => {
    try {
      const date = new Date(dateString)
      return d(date, 'long')
    } catch (error) {
      console.warn('Error formatting full date time:', error)
      return dateString
    }
  }

  const getContextualDate = (dateString: string) => {
    return computed(() => formatContextualDate(dateString))
  }

  const getFullDateTime = (dateString: string) => {
    return computed(() => formatFullDateTime(dateString))
  }

  return {
    formatContextualDate,
    formatFullDateTime,
    getContextualDate,
    getFullDateTime,
    isToday: (dateString: string) => {
      try {
        return isToday(new Date(dateString))
      } catch {
        return false
      }
    },
    currentTime: computed(() => currentTime.value),
  }
}
