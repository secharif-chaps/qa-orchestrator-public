/**
 * UI-compatible tests for useApiService composable
 * Simplified version that works in jsdom environment
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'

describe('useApiService (UI)', () => {
  let useApiService: any
  let mockGetAccessToken: any

  beforeEach(async () => {
    // Clear all mocks
    vi.clearAllMocks()
    
    // Mock fetch globally
    globalThis.fetch = vi.fn()
    
    // Set up useAuth mock for this test
    mockGetAccessToken = vi.fn()
    globalThis.useAuth = vi.fn(() => ({
      getAccessToken: mockGetAccessToken
    }))
    
    // Re-import the module to get fresh instance
    const module = await import('~/composables/useApiService')
    useApiService = module.useApiService
  })

  describe('Basic functionality', () => {
    it('should create API service', () => {
      const api = useApiService()
      
      expect(typeof api.get).toBe('function')
      expect(typeof api.post).toBe('function')
      expect(typeof api.put).toBe('function')
      expect(typeof api.delete).toBe('function')
    })

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
  })

  describe('HTTP methods', () => {
    beforeEach(() => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ data: 'test' })
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
  })

  describe('Error handling', () => {
    it('should handle network errors', async () => {
      mockGetAccessToken.mockResolvedValue('test-token')
      globalThis.fetch = vi.fn().mockRejectedValue(new Error('Network error'))

      const api = useApiService()

      await expect(api.get('/test')).rejects.toThrow('Network error')
    })

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
  })
})