/**
 * Company Management Integration Tests
 * Tests the company API endpoints using mocked backend
 */

import { describe, it, expect } from 'vitest'
import { mockCompany, mockAccessToken, server } from '../setup.e2e'

describe('Company Management Integration', () => {
  describe('Company API', () => {
    it('should fetch companies with authentication', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(Array.isArray(data)).toBe(true)
      expect(data.length).toBeGreaterThan(0)
      expect(data[0]).toEqual(expect.objectContaining({
        id: expect.any(Number),
        name: expect.any(String),
        website: expect.any(String)
      }))
    })

    it('should create a new company', async () => {
      const newCompany = {
        name: 'New Test Company',
        website: 'https://new-test-company.com'
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(newCompany)
      })

      expect(response.status).toBe(201)
      const data = await response.json()
      expect(data.name).toBe(newCompany.name)
      expect(data.website).toBe(newCompany.website)
      expect(data.id).toBeDefined()
    })

    it('should validate company name length', async () => {
      const invalidCompany = {
        name: 'A', // Too short
        website: 'https://test.com'
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(invalidCompany)
      })

      expect(response.status).toBe(400)
      const data = await response.json()
      expect(data.detail).toContain('at least 2 characters')
    })

    it('should require both name and website', async () => {
      const incompleteCompany = {
        name: 'Test Company'
        // Missing website
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(incompleteCompany)
      })

      expect(response.status).toBe(400)
      const data = await response.json()
      expect(data.detail).toContain('Name and website are required')
    })
  })

  describe('Company Details API', () => {
    it('should fetch specific company details', async () => {
      const response = await fetch('http://localhost:8001/api/companies/1', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(data).toEqual(expect.objectContaining({
        id: 16,
        name: expect.any(String),
        website: expect.any(String),
        owner_username: expect.any(String)
      }))
    })

    it('should return 404 for non-existent company', async () => {
      const response = await fetch('http://localhost:8001/api/companies/999', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.status).toBe(404)
      const data = await response.json()
      expect(data.detail).toBe('Company not found')
    })

    it('should update company details', async () => {
      const updates = {
        name: 'Updated Company Name'
      }

      const response = await fetch('http://localhost:8001/api/companies/1', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(updates)
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(data.name).toBe(updates.name)
      expect(data.updated_at).toBeDefined()
    })

    it('should delete a company', async () => {
      const response = await fetch('http://localhost:8001/api/companies/1', {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.status).toBe(204)
    })
  })

  describe('Task Management API', () => {
    it('should fetch company tasks', async () => {
      const response = await fetch('http://localhost:8001/api/tasks/company/1', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(response.ok).toBe(true)
      const data = await response.json()
      expect(Array.isArray(data)).toBe(true)
      
      if (data.length > 0) {
        expect(data[0]).toEqual(expect.objectContaining({
          id: expect.any(Number),
          company_id: expect.any(Number),
          type: expect.any(String),
          status: expect.any(String)
        }))
      }
    })

    it('should create a new task', async () => {
      const newTask = {
        company_id: 1,
        type: 'profile'
      }

      const response = await fetch('http://localhost:8001/api/tasks', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(newTask)
      })

      expect(response.status).toBe(201)
      const data = await response.json()
      expect(data.company_id).toBe(newTask.company_id)
      expect(data.type).toBe(newTask.type)
      expect(data.status).toBe('running')
    })
  })
})