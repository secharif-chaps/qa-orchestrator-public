import type { SourcedValue } from '@/types/company'

// Helper function to extract values from SourcedValue fields
export const getSourcedValue = <T>(
  sourcedValue: SourcedValue<T> | undefined | any,
): T | undefined => {
  if (!sourcedValue) return undefined
  return sourcedValue.value !== undefined ? sourcedValue.value : sourcedValue
}

// Helper function to extract sources from SourcedValue fields
export const getSourcedSource = <T>(
  sourcedValue: SourcedValue<T> | undefined,
): string | undefined => {
  return sourcedValue?.source
}

// Helper function to extract sources from SourcedValue fields
export const getSourcedSourceName = <T>(
  sourcedValue: SourcedValue<T> | undefined,
): string | undefined => {
  if (!sourcedValue?.source) return undefined

  //remove http and https and trailing slash
  let name = sourcedValue.source.replace(/^https?:\/\//, '').replace(/\/$/, '')
  // remove www.
  name = name?.replace(/^www\./, '')
  // remove everything after the first slash
  name = name?.split('/')[0]

  return name
}
