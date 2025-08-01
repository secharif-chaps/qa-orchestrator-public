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
  page: number
  size: number
  pages: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}
