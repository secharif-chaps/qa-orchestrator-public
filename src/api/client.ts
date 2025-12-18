/**
 * API client configuration
 */
import { useAuthStore } from '@/stores/auth'
import { useEndpointResolver } from '@/composables/useEndpointResolver'

type AuthStore = ReturnType<typeof useAuthStore>

const { endpoints } = useEndpointResolver()
const API_BASE_URL = endpoints.value.apiUrl

class ApiClient {
  private baseURL: string

  constructor(baseURL: string) {
    this.baseURL = baseURL
  }

  private async request<T>(endpoint: string, options: RequestInit = {}, retry = true): Promise<T> {
    const url = `${this.baseURL}${endpoint}`

    // Get auth store and access token
    const authStore = useAuthStore()
    const token = authStore.accessToken

    // Prepare headers with authentication
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string>),
    }

    // Add Bearer token if available
    if (token) {
      headers['Authorization'] = `Bearer ${token}`
    }

    // Use manual redirect handling to preserve HTTPS on 307 redirects
    const response = await fetch(url, {
      headers,
      redirect: 'manual',
      ...options,
    })

    // Handle 307/308 redirects manually to preserve HTTPS protocol
    if (response.status === 307 || response.status === 308) {
      const redirectUrl = response.headers.get('Location')
      if (redirectUrl) {
        // Ensure redirect URL uses same protocol as original request
        const originalProtocol = new URL(url).protocol
        let finalRedirectUrl = redirectUrl

        // If redirect switches to HTTP but original was HTTPS, fix it
        if (originalProtocol === 'https:' && redirectUrl.startsWith('http://')) {
          finalRedirectUrl = redirectUrl.replace('http://', 'https://')
        }

        const redirectResponse = await fetch(finalRedirectUrl, {
          headers,
          ...options,
        })
        return this.handleResponse<T>(redirectResponse, endpoint, options, retry, authStore)
      }
    }

    return this.handleResponse<T>(response, endpoint, options, retry, authStore)
  }

  private async handleResponse<T>(
    response: Response,
    endpoint: string,
    options: RequestInit,
    retry: boolean,
    authStore: AuthStore,
  ): Promise<T> {
    // Handle 401 Unauthorized - token might be expired
    if (response.status === 401 && retry) {
      // Try to refresh the token
      const refreshedUser = await authStore.refreshToken()
      if (refreshedUser) {
        // Retry the request with the new token
        return this.request<T>(endpoint, options, false)
      }
    }

    if (!response.ok) {
      if (response.status === 402) {
        // Handle insufficient tokens error with detailed response
        const errorData = await response.json().catch(() => ({}))
        throw new InsufficientTokensError(errorData)
      }
      throw new ApiError(`HTTP ${response.status}: ${response.statusText}`, response.status)
    }

    // Handle responses with no content (like 204 No Content)
    if (response.status === 204 || response.headers.get('content-length') === '0') {
      return null as T
    }

    // Check if response has JSON content
    const contentType = response.headers.get('content-type')
    if (contentType && contentType.includes('application/json')) {
      return response.json()
    }

    // For non-JSON responses, return null
    return null as T
  }

  async get<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint)
  }

  async post<T>(endpoint: string, data: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  }

  async put<T>(endpoint: string, data: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
  }

  async patch<T>(endpoint: string, data: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
  }

  async delete(endpoint: string): Promise<void> {
    await this.request(endpoint, {
      method: 'DELETE',
    })
  }
}

class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

/**
 * Error thrown when token balance is insufficient for an operation.
 *
 * Supports both the old module-based format (current_tokens) and
 * the new global token format (current_balance) for backward compatibility.
 */
class InsufficientTokensError extends Error {
  public readonly currentBalance: number
  public readonly requiredTokens: number

  constructor(data: Record<string, unknown>) {
    const message = (data.message as string) || 'Insufficient tokens for this operation'
    super(message)
    this.name = 'InsufficientTokensError'
    // Support both old (current_tokens) and new (current_balance) field names
    this.currentBalance =
      (data.current_balance as number) ?? (data.current_tokens as number) ?? 0
    this.requiredTokens = (data.required_tokens as number) ?? 1
  }

  // Alias for backward compatibility
  get currentTokens(): number {
    return this.currentBalance
  }
}

export const apiClient = new ApiClient(API_BASE_URL)
export { ApiError, InsufficientTokensError }
