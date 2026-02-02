import type { PaginationMeta } from '@/types/pagination'

/**
 * Raw API pagination format (various backends may use different field names)
 * This interface covers common variations:
 * - page/current_page for current page number
 * - per_page/limit/size for items per page
 * - total_pages/last_page/pages for total page count
 */
export interface ApiPaginationRaw {
  page?: number
  current_page?: number
  per_page?: number
  limit?: number
  size?: number
  total?: number
  total_pages?: number
  last_page?: number
  pages?: number
}

/**
 * Transforms various API pagination formats to the standard PaginationMeta format
 * used by the Pagination component.
 *
 * Handles both formats:
 * - Organizations API: { page, per_page, total, total_pages }
 * - Users API: { page, limit, total, total_pages }
 *
 * @param raw - Raw pagination data from API response (meta or pagination field)
 * @returns PaginationMeta for the Pagination component, or null if data is invalid
 */
export const transformToPaginationMeta = (raw: ApiPaginationRaw | null | undefined): PaginationMeta | null => {
  if (!raw) return null

  const currentPage = raw.page ?? raw.current_page ?? 1
  const perPage = raw.per_page ?? raw.limit ?? raw.size ?? 10
  const total = raw.total ?? 0
  const lastPage = raw.total_pages ?? raw.last_page ?? raw.pages ?? 1

  // Validate required fields have meaningful values
  if (total === undefined || currentPage === undefined) {
    return null
  }

  return {
    total,
    per_page: perPage,
    current_page: currentPage,
    last_page: lastPage,
  }
}
