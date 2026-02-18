import type { DateValue } from 'reka-ui'
import type { Actor, Source } from './facet'
import type { WatchFileEventType } from './watchFile'

export interface DocumentFilter {
  value: string
  title: string
  count: number
  icon?: string
  empty?: boolean
}

export enum DatesPeriod {
  LAST_WEEK = 'last_week',
  LAST_MONTH = 'last_month',
  LAST_3_MONTH = 'last_3_month',
}

export enum FilterDates {
  PUBLICATION = 'publication',
  COLLECT = 'collect',
}

export type FilterDatesType = keyof typeof FilterDates | undefined

export interface DatePicker {
  start?: DateValue
  end?: DateValue
}

export interface Filter {
  datesPicker: DatePicker | undefined
  selectedPeriod?: DatesPeriod
  selectedDateType?: FilterDates
  status: string[]
  actors: Actor[]
  sources: Source[]
}

export interface ApiFilter {
  status: string[]
  actors: string[]
  sources: string[]
  search: string
  watchFileId?: string
  sortBy?: string
  sortOrder?: string
  page?: number
  itemsPerPage?: number
}

// Form filters types
export interface BaseFormFilters {
  datesPicker?: DatePicker
  selectedPeriod?: DatesPeriod
  actors: Actor[]
  sources: Source[]
}

export interface DocumentsFormFilters extends BaseFormFilters {
  selectedDateType?: FilterDates
  status: string[]
}

export interface AnalysisFormFilters extends BaseFormFilters {
  eventTypes: WatchFileEventType[]
}
