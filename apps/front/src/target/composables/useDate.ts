import { CalendarDate } from '@internationalized/date'
import { DatesPeriod } from '@target/types/filter'
import type { DateValue } from 'reka-ui'

export function useDate() {
  /**
   * Converts a DateValue object to a JavaScript Date object.
   *
   * @param {string | undefined} dateString - Object containing eg: 2025-09-10
   * @returns {Date | undefined} JavaScript Date object or undefined if input is falsy
   *
   * Takes a DateValue with separate year, month, and day properties and creates a standard
   * Date object. Month is adjusted from 1-based to 0-based indexing for JavaScript Date constructor.
   */
  const convertDateStringToDate = (dateString: string | undefined): Date | undefined => {
    if (!dateString) return undefined

    return new Date(dateString)
  }

  /**
   * Converts a JavaScript Date object to a DateValue object.
   *
   * @param {Date | undefined} date - JavaScript Date object
   * @returns {DateValue | undefined} Object containing year, month, and day properties or undefined if input is falsy
   *
   * Takes a standard Date object and extracts year, month, and day into a DateValue object.
   * Month is adjusted from 0-based to 1-based indexing to match DateValue format.
   */
  const convertDateToDateValue = (date: Date): DateValue => {
    const year = date.getFullYear()
    const month = date.getMonth() + 1 // Convert from 0-based to 1-based
    const day = date.getDate()

    return new CalendarDate(year, month, day) as unknown as DateValue
  }

  /**
   * Formats a Date object to ISO string with timezone offset instead of 'Z'.
   *
   * @param {Date} date - The date to format
   * @param {boolean} isEndOfDay - If true, sets time to 23:59:59.999, otherwise 00:00:00.000
   * @returns {string} ISO formatted date string with timezone offset or empty string if date is falsy
   *
   * Converts a Date to ISO format but replaces the 'Z' timezone indicator with the actual
   * local timezone offset. Optionally sets the time to either start or end of day.
   */
  const formatToISOWithTimezone = (date: Date, isEndOfDay = false): string => {
    if (!date) return ''

    const newDate = new Date(date)

    if (isEndOfDay) {
      newDate.setHours(23, 59, 59, 999)
    } else {
      newDate.setHours(0, 0, 0, 0)
    }

    return newDate.toISOString().replace('Z', getTimezoneOffset())
  }

  /**
   * Gets the current timezone offset in ±HH:MM format.
   *
   * @returns {string} Timezone offset string in format like "+02:00" or "-05:30"
   *
   * Calculates the local timezone offset from UTC and formats it as a string
   * with proper sign, zero-padded hours and minutes separated by colon.
   */
  const getTimezoneOffset = (): string => {
    const offset = new Date().getTimezoneOffset()
    const hours = Math.floor(Math.abs(offset) / 60)
    const minutes = Math.abs(offset) % 60
    const sign = offset <= 0 ? '+' : '-'

    return `${sign}${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`
  }

  /**
   * Calculates start and end dates for predefined time periods relative to today.
   *
   * @param {DatesPeriod} period - Enum value representing the desired time period
   * @returns {{ start: Date; end: Date }} Object with start and end Date objects
   *
   * Takes a period enum (LAST_WEEK, LAST_MONTH, LAST_3_MONTH) and returns the corresponding
   * date range. Start date is calculated by subtracting the period from today, end date is today.
   * Returns current date for both start and end if period is not recognized.
   */
  const getPeriodDates = (period: DatesPeriod): { start: Date; end: Date } => {
    const today = new Date()
    const start = new Date()

    switch (period) {
      case DatesPeriod.LAST_WEEK:
        start.setDate(today.getDate() - 7)
        break
      case DatesPeriod.LAST_MONTH:
        start.setMonth(today.getMonth() - 1)
        break
      case DatesPeriod.LAST_3_MONTH:
        start.setMonth(today.getMonth() - 3)
        break
      default:
        return { start: new Date(), end: new Date() }
    }

    return { start, end: today }
  }

  return {
    convertDateStringToDate,
    formatToISOWithTimezone,
    getPeriodDates,
    convertDateToDateValue,
  }
}
