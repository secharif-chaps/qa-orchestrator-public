export enum SortOrder {
  ASC = 'asc',
  DESC = 'desc',
}

export interface PaginationParams {
  page?: number
  per_page?: number
  sort?: string
  order?: SortOrder
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}
