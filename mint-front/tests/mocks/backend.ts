/**
 * Mock backend API handlers using MSW
 */

import { http, HttpResponse } from 'msw'
import { mockUser, mockAdminUser, mockCompany, mockAccessToken } from '../setup.e2e'

const API_BASE = 'http://localhost:8001/api'

// In-memory store for created companies during tests
const createdCompanies = new Map<string, any>()

// Export function to clear created companies between tests
export const clearCreatedCompanies = () => {
  createdCompanies.clear()
}

export const mockBackendHandlers = [
  // Authentication endpoints
  http.post(`${API_BASE}/auth/login`, async ({ request }) => {
    const body = await request.json() as { username: string; password: string }
    
    if (body.username === 'testuser' && body.password === 'password') {
      return HttpResponse.json({
        access_token: mockAccessToken,
        refresh_token: 'mock-refresh-token',
        token_type: 'Bearer',
        expires_in: 3600
      })
    }
    
    if (body.username === 'admin' && body.password === 'password') {
      return HttpResponse.json({
        access_token: mockAccessToken,
        refresh_token: 'mock-refresh-token',
        token_type: 'Bearer',
        expires_in: 3600
      })
    }
    
    return HttpResponse.json(
      { detail: 'Invalid credentials' },
      { status: 401 }
    )
  }),

  http.get(`${API_BASE}/auth/me`, ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    return HttpResponse.json(mockUser)
  }),

  http.post(`${API_BASE}/auth/logout`, () => {
    return HttpResponse.json({ success: true })
  }),

  // Company endpoints
  http.get(`${API_BASE}/companies`, ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    // Return mock company plus any created companies
    const allCompanies = [mockCompany, ...Array.from(createdCompanies.values())]
    return HttpResponse.json(allCompanies)
  }),

  http.get(`${API_BASE}/companies/:id`, ({ params, request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const id = params.id as string
    
    // Check for mock company
    if (id === '16' || id === '1') {
      return HttpResponse.json(mockCompany)
    }
    
    // Check for created companies
    if (createdCompanies.has(id)) {
      return HttpResponse.json(createdCompanies.get(id))
    }
    
    return HttpResponse.json(
      { detail: 'Company not found' },
      { status: 404 }
    )
  }),

  http.post(`${API_BASE}/companies`, async ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const body = await request.json() as { name: string; website: string }
    
    // Validate input
    if (!body.name || !body.website) {
      return HttpResponse.json(
        { detail: 'Name and website are required' },
        { status: 400 }
      )
    }
    
    if (body.name.length < 2) {
      return HttpResponse.json(
        { detail: 'Company name must be at least 2 characters long' },
        { status: 400 }
      )
    }
    
    const newCompany = {
      ...mockCompany,
      id: Math.floor(Math.random() * 1000) + 2,
      name: body.name,
      website: body.website,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    }
    
    // Store the created company for later retrieval
    createdCompanies.set(newCompany.id.toString(), newCompany)
    
    return HttpResponse.json(newCompany, { status: 201 })
  }),

  http.put(`${API_BASE}/companies/:id`, async ({ params, request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const id = params.id
    const body = await request.json() as Partial<typeof mockCompany>
    
    if (id === '16' || id === '1') {
      const updatedCompany = {
        ...mockCompany,
        ...body,
        id: parseInt(id as string),
        updated_at: new Date().toISOString()
      }
      return HttpResponse.json(updatedCompany)
    }
    
    return HttpResponse.json(
      { detail: 'Company not found' },
      { status: 404 }
    )
  }),

  http.delete(`${API_BASE}/companies/:id`, ({ params, request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const id = params.id
    if (id === '16' || id === '1') {
      return HttpResponse.json({ success: true }, { status: 204 })
    }
    
    return HttpResponse.json(
      { detail: 'Company not found' },
      { status: 404 }
    )
  }),

  // Task endpoints
  http.get(`${API_BASE}/tasks/company/:companyId`, ({ params, request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const companyId = parseInt(params.companyId as string)
    if (companyId === 16 || companyId === 1) {
      return HttpResponse.json(mockCompany.tasks)
    }
    
    return HttpResponse.json([])
  }),

  http.post(`${API_BASE}/tasks`, async ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    const body = await request.json() as { company_id: number; type: string }
    
    return HttpResponse.json({
      id: Math.floor(Math.random() * 1000) + 2,
      company_id: body.company_id,
      type: body.type,
      status: 'running',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    }, { status: 201 })
  }),

  // Admin endpoints
  http.get(`${API_BASE}/admin/companies`, ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    // Mock admin check - in real implementation this would check user roles
    return HttpResponse.json([mockCompany])
  }),

  // Security endpoints
  http.get(`${API_BASE}/security/stats`, ({ request }) => {
    const authHeader = request.headers.get('authorization')
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return HttpResponse.json(
        { detail: 'Authentication required' },
        { status: 401 }
      )
    }
    
    return HttpResponse.json({
      database: {
        total_queries: 150,
        average_time: 0.045,
        slow_queries: 2,
        slow_query_percentage: 1.33
      },
      security_features: {
        jwt_verification: 'enabled',
        authorization: 'enabled',
        input_validation: 'enabled',
        rate_limiting: 'enabled'
      }
    })
  }),

  http.get(`${API_BASE}/security/health`, () => {
    return HttpResponse.json({
      status: 'secure',
      timestamp: new Date().toISOString(),
      security_level: 'high',
      features: ['authentication', 'authorization', 'input_validation', 'rate_limiting']
    })
  })
]