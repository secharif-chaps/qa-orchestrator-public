export interface JsonLdContext {
  '@vocab': string
  hydra: string
  [key: string]: string
}

export interface JsonLdCollectionView {
  '@id': string
  '@type': 'PartialCollectionView'
  next?: string
  previous?: string
}

export interface JsonLdCollection<T> {
  '@context': JsonLdContext
  '@id': string
  '@type': 'hydra:Collection'
  member: T[]
  totalItems?: number
  view?: JsonLdCollectionView
}

export interface JsonLdResource {
  '@context'?: JsonLdContext
  '@id': string
  '@type': string
}

export interface ValidationViolation {
  propertyPath: string
  message: string
  code: string
}

export interface ValidationError extends JsonLdResource {
  status: number
  violations: ValidationViolation[]
  detail: string
  description: string
  title: string
}

export class ApiError extends Error {
  response: {
    status: number
    data: unknown
  }

  constructor(message: string, status: number, data: unknown) {
    super(message)
    this.name = 'ApiError'
    this.response = {
      status,
      data,
    }
  }
}

export class ApiValidationError extends Error {
  violations: ValidationViolation[]

  constructor(error: ValidationError) {
    super(error.detail)
    this.name = 'ApiValidationError'
    this.violations = error.violations
  }
}

export class ApiUnauthorizedError extends ApiError {
  constructor(error: string) {
    super(error, 401, null)
    this.name = 'ApiUnauthorizedError'
  }
}

export class ApiRateLimitError extends ApiError {
  constructor(error: string) {
    super(error, 429, null)
    this.name = 'ApiRateLimitError'
  }
}
