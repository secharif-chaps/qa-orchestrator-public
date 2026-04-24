import { CalendarDate } from '@internationalized/date'
import { DatesPeriod } from '@target/types/filter'
import type { DateValue } from 'reka-ui'

/**
 * Pure date transformation helpers. No formatting concerns — for display use
 * `useDateTime` from `@/composables/useDateTime`.
 */

export const convertDateStringToDate = (dateString: string | undefined): Date | undefined => {
  if (!dateString) return undefined
  return new Date(dateString)
}

export const convertDateToDateValue = (date: Date): DateValue => {
  const year = date.getFullYear()
  const month = date.getMonth() + 1
  const day = date.getDate()
  return new CalendarDate(year, month, day) as unknown as DateValue
}

export const getTimezoneOffset = (): string => {
  const offset = new Date().getTimezoneOffset()
  const hours = Math.floor(Math.abs(offset) / 60)
  const minutes = Math.abs(offset) % 60
  const sign = offset <= 0 ? '+' : '-'
  return `${sign}${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`
}

export const formatToISOWithTimezone = (date: Date, isEndOfDay = false): string => {
  if (!date) return ''
  const newDate = new Date(date)
  if (isEndOfDay) {
    newDate.setHours(23, 59, 59, 999)
  } else {
    newDate.setHours(0, 0, 0, 0)
  }
  return newDate.toISOString().replace('Z', getTimezoneOffset())
}

export const getPeriodDates = (period: DatesPeriod): { start: Date; end: Date } => {
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
