/**
 * API client configuration
 */
import { useAuthStore } from '@/stores/auth'

const API_BASE_URL = import.meta.env.VITE_BACKEND_API || 'http://localhost:8000'

class ApiClient {
  private baseURL: string

  constructor(baseURL: string) {
    this.baseURL = baseURL
  }

  private async request<T>(endpoint: string, options: RequestInit = {}, retry = true): Promise<T> {
    const url = `${this.baseURL}/api${endpoint}`

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

    const response = await fetch(url, {
      headers,
      ...options,
    })

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

class InsufficientTokensError extends Error {
  public readonly currentTokens: number
  public readonly requiredTokens: number
  public readonly module: string

  constructor(data: any) {
    const message = data.message || 'Insufficient tokens for this operation'
    super(message)
    this.name = 'InsufficientTokensError'
    this.currentTokens = data.current_tokens || 0
    this.requiredTokens = data.required_tokens || 1
    this.module = data.module || 'unknown'
  }
}

export const apiClient = new ApiClient(API_BASE_URL)
export { ApiError, InsufficientTokensError }
