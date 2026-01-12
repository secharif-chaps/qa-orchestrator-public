import type { useQueryCache } from '@pinia/colada'

/**
 * Rollback cache changes using previous states stored as a Map
 */
export function rollbackCacheChanges(
  queryCache: ReturnType<typeof useQueryCache>,
  previousStates: Map<string, unknown>,
): void {
  previousStates.forEach((value, key) => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    queryCache.setQueryData(JSON.parse(key) as any, value)
  })
}
