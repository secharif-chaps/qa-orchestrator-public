import { useAppFetch } from '@target/composables/useAppFetch'
import { useAuth } from '@target/composables/useAuth'
import { useMercure } from '@target/composables/useMercure'
import { useToast } from '@target/composables/useToast'
import type { ApiResponse, DefaultErrorMessage, Link, MercureResponse } from '@target/types/api'
import { ApiError, ApiRateLimitError, ApiUnauthorizedError } from '@target/types/jsonld'
import { useI18n } from 'vue-i18n'

/**
 * Parses the Link header to extract Mercure hub URL and topics.
 * Expects headers with rel="mercure" (hub URL) and one or more rel="topic" (user-scoped topics).
 */
export function discoverMercure(response: Response): MercureResponse | undefined {
  const linkHeader = response.headers.get('Link')
  if (!linkHeader) return undefined

  const hubMatch = linkHeader.match(/<([^>]+)>;\s*rel="mercure"/)
  const hubUrl = hubMatch?.[1]
  if (!hubUrl) return undefined

  // Extract all topics from the header (there can be multiple)
  const topicRegex = /<([^>]+)>;\s*rel="topic"/g
  const topics: string[] = []
  let topicMatch
  while ((topicMatch = topicRegex.exec(linkHeader)) !== null) {
    const topic = topicMatch[1]
    if (topic) {
      topics.push(topic)
    }
  }

  if (topics.length === 0) return undefined

  return {
    url: hubUrl,
    topics,
  } as MercureResponse
}

function createApi() {
  const mercure = useMercure()
  const auth = useAuth()
  const { t } = useI18n()
  const toast = useToast()
  const api = useAppFetch()

  function listLinks(response: Response): Link[] {
    const linkHeader = response.headers.get('Link')
    if (!linkHeader) return []

    return linkHeader.split(',').map((link) => {
      const parts = link.split(';').map((p) => p.trim())
      const href = parts[0]?.replace(/^<|>$/g, '') || ''
      const rel = parts[1]?.replace(/^rel="|"$/g, '') || ''
      return {
        href,
        rel,
      }
    })
  }

  async function request<T>(
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    {
      retryOnUnauthorized = true,
      defaultErrorMessage,
      ...rest
    }: {
      body?: unknown
      query?: Record<string, unknown>
      subscribeKey?: string
      retryOnUnauthorized?: boolean
      defaultErrorMessage?: DefaultErrorMessage
      onUpdate?: (data: T) => void
      onError?: (error: Error) => void
    } = {},
  ): Promise<ApiResponse<T>> {
    const options = { retryOnUnauthorized, ...rest }
    try {
      const response = await api.raw<T>(path, {
        method,
        headers: {
          'Content-Type':
            method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json',
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
        query: options.query ? options.query : undefined,
      })

      const data = response._data
      if (!data && method !== 'DELETE' && response.status !== 204) {
        throw new ApiError(t('errors.no_data'), response.status, null)
      }

      const apiResponse: ApiResponse<T> = {
        data: data || ({} as T),
        status: response.status,
        headers: response.headers,
        mercure: discoverMercure(response),
        links: listLinks(response),
      }

      if (method === 'GET' && options.subscribeKey && apiResponse.mercure && options.onUpdate) {
        await mercure.subscribe(
          apiResponse.mercure.url,
          apiResponse.mercure.topics,
          {
            onUpdate: options.onUpdate,
            onError: options.onError,
            maxRetries: 3,
            retryDelay: 1000,
          },
          options.subscribeKey,
        )
      }

      return apiResponse
    } catch (error) {
      if (error instanceof ApiUnauthorizedError) {
        if (options.retryOnUnauthorized) {
          return await request<T>(method, path, {
            ...options,
            retryOnUnauthorized: false,
          })
        } else {
          auth.logout()
        }
      } else if (error instanceof ApiRateLimitError) {
        toast.error(error.message)
      } else {
        toast.error(
          defaultErrorMessage?.title || t('common.errors.unexpected'),
          defaultErrorMessage?.description,
        )
      }

      throw error
    }
  }

  return {
    get: <T>(path: string, opts = {}) => request<T>('GET', path, opts),
    post: <T>(path: string, body: unknown, opts = {}) =>
      request<T>('POST', path, { body, ...opts }),
    put: <T>(path: string, body: unknown, opts = {}) => request<T>('PUT', path, { body, ...opts }),
    patch: <T>(path: string, body: unknown, opts = {}) =>
      request<T>('PATCH', path, { body, ...opts }),
    delete: (path: string, opts = {}) => request('DELETE', path, opts),
  }
}

let api: ReturnType<typeof createApi>

export function useApi() {
  if (!api) {
    api = createApi()
  }
  return api
}
