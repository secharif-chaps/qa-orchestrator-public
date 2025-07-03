/**
 * Authentication Integration Tests
 * Tests the authentication flow using API calls and mocked backend
 */

import { describe, it, expect } from 'vitest'
import { mockUser, mockAccessToken, server } from '../setup.e2e'

describe('Authentication Integration', () => {
  describe('Authentication API', () => {
    it('should handle login with valid credentials', async () => {
      const response = await fetch('http://localhost:8001/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          username: 'testuser',
          password: 'password'
        })
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(data.access_token).toBe(mockAccessToken)
      expect(data.token_type).toBe('Bearer')
    })

    it('should reject login with invalid credentials', async () => {
      const response = await fetch('http://localhost:8001/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          username: 'wronguser',
          password: 'wrongpass'
        })
      })

      expect(response.status).toBe(401)
      const data = await response.json()
      expect(data.detail).toBe('Invalid credentials')
    })

    it('should protect API endpoints', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'GET'
      })

      expect(response.status).toBe(401)
      const data = await response.json()
      expect(data.detail).toBe('Authentication required')
    })

    it('should allow access with valid token', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(Array.isArray(data)).toBe(true)
    })

    it('should return user info with valid token', async () => {
      const response = await fetch('http://localhost:8001/api/auth/me', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(data.username).toBe(mockUser.username)
      expect(data.email).toBe(mockUser.email)
    })
  })

  describe('Company API Integration', () => {
    it('should create a new company with authentication', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify({
          name: 'Test Company',
          website: 'https://test.com'
        })
      })

      expect(response.status).toBe(201)
      const data = await response.json()
      expect(data.name).toBe('Test Company')
      expect(data.website).toBe('https://test.com')
    })

    it('should validate company creation input', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify({
          name: 'A', // Too short
          website: 'https://test.com'
        })
      })

      expect(response.status).toBe(400)
      const data = await response.json()
      expect(data.detail).toContain('at least 2 characters')
    })
  })
})