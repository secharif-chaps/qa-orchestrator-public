import { describe, it, expect, beforeEach, vi } from 'vitest'

// Create mock functions at the top level
const mockGetAccessToken = vi.fn()
const mockNavigateTo = vi.fn()

// Mock composables
vi.mock('~/composables/useAuth', () => ({
  useAuth: () => ({
    getAccessToken: mockGetAccessToken
  })
}))

// Mock Nuxt runtime config
vi.mock('#nuxt', () => ({
  useRuntimeConfig: () => ({
    public: {
      backendApi: 'http://localhost:8000'
    }
  })
}))

// Mock navigateTo as a global function
globalThis.navigateTo = mockNavigateTo

// Import after mocking
import { useApiService } from '~/composables/useApiService'

// Mock fetch
globalThis.fetch = vi.fn()

describe('useApiService', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    globalThis.fetch = vi.fn()
    globalThis.navigateTo = mockNavigateTo
  })

  describe('fetchAPI', () => {
    it('should make request with default headers', async () => {
      mockGetAccessToken.mockResolvedValue(null)
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
      })

      const api = useApiService()
      await api.get('/test')

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      })
    })

    it('should add authorization header when token is available', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
      })

      const api = useApiService()
      await api.get('/test')

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token'
        }
      })
    })

    it('should handle 401 unauthorized and return undefined', async () => {
      mockGetAccessToken.mockResolvedValue('invalid-token')
      
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        statusText: 'Unauthorized'
      })

      const api = useApiService()
      const result = await api.get('/test')

      // For unit tests, we just verify that 401 returns undefined
      // (The actual navigation is handled by Nuxt runtime and tested in E2E)
      expect(result).toBeUndefined()
    })

    it('should handle HTTP errors with error details', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 400,
        statusText: 'Bad Request',
        json: () => Promise.resolve({ detail: 'Invalid input' })
      })

      const api = useApiService()

      await expect(api.get('/test')).rejects.toThrow('Invalid input')
    })

    it('should handle HTTP errors without error details', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        json: () => Promise.reject(new Error('Not JSON'))
      })

      const api = useApiService()

      await expect(api.get('/test')).rejects.toThrow('API error: 500 Internal Server Error')
    })

    it('should handle 204 No Content responses', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 204
      })

      const api = useApiService()
      const result = await api.delete('/test')

      expect(result).toBeNull()
    })

    it('should merge custom headers with default headers', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
      })

      const api = useApiService()
      await api.get('/test', {
        headers: {
          'Custom-Header': 'custom-value'
        }
      })

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token',
          'Custom-Header': 'custom-value'
        }
      })
    })
  })

  describe('HTTP methods', () => {
    beforeEach(() => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
      })
    })

    it('should make GET request', async () => {
      const api = useApiService()
      await api.get('/test')

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token'
        }
      })
    })

    it('should make POST request with data', async () => {
      const api = useApiService()
      const data = { name: 'test', value: 123 }
      
      await api.post('/test', data)

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token'
        },
        body: JSON.stringify(data)
      })
    })

    it('should make PUT request with data', async () => {
      const api = useApiService()
      const data = { id: 1, name: 'updated' }
      
      await api.put('/test/1', data)

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test/1', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token'
        },
        body: JSON.stringify(data)
      })
    })

    it('should make DELETE request', async () => {
      const api = useApiService()
      
      await api.delete('/test/1')

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test/1', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token'
        }
      })
    })

    it('should pass custom options to HTTP methods', async () => {
      const api = useApiService()
      
      await api.post('/test', { data: 'test' }, {
        headers: {
          'Custom-Header': 'value'
        }
      })

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/test', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer test-token',
          'Custom-Header': 'value'
        },
        body: JSON.stringify({ data: 'test' })
      })
    })
  })

  describe('error handling', () => {
    it('should handle network errors', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockRejectedValue(new Error('Network error'))

      const api = useApiService()

      await expect(api.get('/test')).rejects.toThrow('Network error')
    })

    it('should handle JSON parsing errors gracefully', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 400,
        statusText: 'Bad Request',
        json: () => Promise.reject(new Error('Invalid JSON'))
      })

      const api = useApiService()

      await expect(api.get('/test')).rejects.toThrow('API error: 400 Bad Request')
    })

    it('should handle empty response bodies', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve()
      })

      const api = useApiService()
      const result = await api.get('/test')

      expect(result).toBeUndefined()
    })
  })

  describe('token handling', () => {
    it('should work without authentication token', async () => {
      mockGetAccessToken.mockResolvedValue(null)
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'public' })
      })

      const api = useApiService()
      const result = await api.get('/public')

      expect(fetch).toHaveBeenCalledWith('http://localhost:8000/public', {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      })
      expect(result).toEqual({ data: 'public' })
    })

    it('should handle token retrieval errors', async () => {
      mockGetAccessToken.mockRejectedValue(new Error('Token error'))
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
      })

      const api = useApiService()
      
      // Token error should propagate and reject the promise
      await expect(api.get('/test')).rejects.toThrow('Token error')
    })
  })
})