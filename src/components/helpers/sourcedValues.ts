import type { SourcedValue } from '@/types/company'

// Helper function to extract values from SourcedValue fields
export const getSourcedValue = <T>(
  sourcedValue: SourcedValue<T> | undefined | any,
): T | undefined => {
  if (!sourcedValue) return undefined
  return sourcedValue.value !== undefined ? sourcedValue.value : sourcedValue
}
