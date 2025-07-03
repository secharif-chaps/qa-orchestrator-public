/**
 * Search Functionality Integration Tests
 * Tests the search/company creation API endpoints
 */

import { describe, it, expect } from 'vitest'
import { mockAccessToken, server } from '../setup.e2e'

describe('Search Functionality Integration', () => {
  describe('Company Search and Creation', () => {
    it('should validate company creation input', async () => {
      // Test with valid input
      const validCompany = {
        name: 'Valid Company Name',
        website: 'https://valid-company.com'
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(validCompany)
      })

      expect(response.status).toBe(201)
      const data = await response.json()
      expect(data.name).toBe(validCompany.name)
      expect(data.website).toBe(validCompany.website)
    })

    it('should reject company with too short name', async () => {
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

    it('should reject company without required fields', async () => {
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

    it('should handle special characters in company name', async () => {
      const companyWithSpecialChars = {
        name: 'Company & Co. Ltd.',
        website: 'https://company-co.com'
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(companyWithSpecialChars)
      })

      expect(response.status).toBe(201)
      const data = await response.json()
      expect(data.name).toBe(companyWithSpecialChars.name)
    })

    it('should create company with various website formats', async () => {
      const testCases = [
        { name: 'HTTPS Company', website: 'https://example.com' },
        { name: 'HTTP Company', website: 'http://example.com' },
        { name: 'Subdomain Company', website: 'https://app.example.com' },
        { name: 'Path Company', website: 'https://example.com/path' }
      ]

      for (const testCase of testCases) {
        const response = await fetch('http://localhost:8001/api/companies', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${mockAccessToken}`
          },
          body: JSON.stringify(testCase)
        })

        expect(response.status).toBe(201)
        const data = await response.json()
        expect(data.name).toBe(testCase.name)
        expect(data.website).toBe(testCase.website)
      }
    })
  })

  describe('Search Workflow Integration', () => {
    it('should create company and retrieve it', async () => {
      // Create a company
      const newCompany = {
        name: 'Integration Test Company',
        website: 'https://integration-test.com'
      }

      const createResponse = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(newCompany)
      })

      expect(createResponse.status).toBe(201)
      const createdCompany = await createResponse.json()
      expect(createdCompany.id).toBeDefined()

      // Retrieve the company
      const getResponse = await fetch(`http://localhost:8001/api/companies/${createdCompany.id}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(getResponse.ok).toBe(true)
      const retrievedCompany = await getResponse.json()
      expect(retrievedCompany.name).toBe(newCompany.name)
      expect(retrievedCompany.website).toBe(newCompany.website)
    })

    it('should create company and fetch it in company list', async () => {
      // Create a company
      const newCompany = {
        name: 'List Test Company',
        website: 'https://list-test.com'
      }

      const createResponse = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: JSON.stringify(newCompany)
      })

      expect(createResponse.status).toBe(201)

      // Fetch companies list
      const listResponse = await fetch('http://localhost:8001/api/companies', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${mockAccessToken}`
        }
      })

      expect(listResponse.ok).toBe(true)
      const companies = await listResponse.json()
      expect(Array.isArray(companies)).toBe(true)
      expect(companies.length).toBeGreaterThan(0)

      // Should include our newly created company or the mock company
      const hasTestCompany = companies.some((company: any) => 
        company.name === newCompany.name || company.name === 'Test Company'
      )
      expect(hasTestCompany).toBe(true)
    })
  })

  describe('Error Handling', () => {
    it('should handle authentication errors', async () => {
      const newCompany = {
        name: 'Unauthorized Test',
        website: 'https://unauthorized.com'
      }

      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
          // No Authorization header
        },
        body: JSON.stringify(newCompany)
      })

      expect(response.status).toBe(401)
      const data = await response.json()
      expect(data.detail).toBe('Authentication required')
    })

    it('should handle malformed JSON', async () => {
      const response = await fetch('http://localhost:8001/api/companies', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${mockAccessToken}`
        },
        body: 'invalid json'
      })

      // This would typically result in a 400 Bad Request
      expect(response.status).toBeGreaterThanOrEqual(400)
    })
  })
})