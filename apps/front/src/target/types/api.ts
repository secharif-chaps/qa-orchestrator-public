export interface MercureResponse {
  url: string
  topics: string[]
}

export interface Link {
  rel: string
  href: string
}

export interface DefaultErrorMessage {
  title: string
  description?: string
}

export interface ApiResponse<T> {
  data: T
  status: number
  headers: Headers
  mercure?: MercureResponse
  links: Link[]
}
