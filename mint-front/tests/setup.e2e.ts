/**
 * Integration test setup - includes backend mocking and test data
 */

import { beforeAll, afterAll, beforeEach, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { mockBackendHandlers, clearCreatedCompanies } from './mocks/backend'

// Setup MSW server for API mocking
export const server = setupServer(...mockBackendHandlers)

beforeAll(async () => {
  console.log('🚀 Setting up integration test environment...')
  
  // Start the mock server
  server.listen({
    onUnhandledRequest: 'warn',
  })
  
  console.log('🎭 Mock backend server started')
})

afterAll(async () => {
  // Clean up
  server.close()
  clearCreatedCompanies()
  console.log('🧹 Integration test environment cleaned up')
})

beforeEach(async () => {
  // Reset handlers before each test
  server.resetHandlers()
  // Clear any companies created during previous tests
  clearCreatedCompanies()
})

afterEach(async () => {
  // Cleanup after each test
})

// E2E Test utilities
export const mockUser = {
  username: 'testuser',
  email: 'test@example.com',
  sub: 'test-user-id',
  preferred_username: 'testuser',
  roles: ['user']
}

export const mockAdminUser = {
  username: 'admin',
  email: 'admin@example.com', 
  sub: 'admin-user-id',
  preferred_username: 'admin',
  roles: ['admin', 'user']
}

export const mockCompany = {
  id: 16,
  name: 'Sinequa',
  website: 'https://www.sinequa.com/',
  owner_username: 'testuser',
  tasks: [
    {
      id: 112,
      company_id: 16,
      type: 'press',
      status: 'succeeded',
      error: null,
      created_at: '2025-07-03T09:29:35.269182',
      updated_at: '2025-07-03T09:30:02.89158'
    },
    {
      id: 109,
      company_id: 16,
      type: 'profile',
      status: 'succeeded',
      error: null,
      created_at: '2025-07-03T09:29:35.269175',
      updated_at: '2025-07-03T09:29:58.60438'
    },
    {
      id: 110,
      company_id: 16,
      type: 'digital',
      status: 'succeeded',
      error: null,
      created_at: '2025-07-03T09:29:35.269181',
      updated_at: '2025-07-03T09:30:22.046166'
    },
    {
      id: 111,
      company_id: 16,
      type: 'csr',
      status: 'succeeded',
      error: null,
      created_at: '2025-07-03T09:29:35.269181',
      updated_at: '2025-07-03T09:29:56.195728'
    }
  ],
  profile: {
    businessLine: {
      value: 'Édition de logiciels applicatifs spécialisés dans l\'intelligence artificielle et la recherche d\'information (Enterprise Search, GenAI).',
      source: 'https://www.societe.com/societe/sinequa-442599213.html'
    },
    catchphrase: {
      value: 'Sinequa augments your employees with the most capable Search-Powered GenAI Assistant Platform. Your content, your workflow.',
      source: 'https://www.sinequa.com/'
    },
    establishmentYear: {
      value: '2002',
      source: 'https://www.societe.com/societe/sinequa-442599213.html'
    },
    employeeCount: {
      value: '100 à 199 salariés (donnée 2022)',
      source: 'https://www.societe.com/societe/sinequa-442599213.html'
    },
    ceo: {
      value: 'Olivier Dellenbach',
      source: 'https://www.sinequa.com/company/leadership/'
    }
  },
  digital: {
    strategy: {
      value: 'Sinequa\'s digital strategy centers on leveraging search-powered AI to enable digital transformation for enterprises.',
      source: 'Analysis of Sinequa\'s corporate messaging and product descriptions across official website and published digital content.'
    },
    socialMedia: [
      {
        name: 'LinkedIn',
        url: {
          value: 'https://www.linkedin.com/company/sinequa/',
          source: 'Sinequa LinkedIn official company profile'
        }
      },
      {
        name: 'Twitter/X',
        url: {
          value: 'https://twitter.com/Sinequa',
          source: 'Sinequa Twitter (appears official)'
        }
      }
    ]
  },
  timeline: {
    insights: 'Sinequa evolved from 1980s French research roots to a leading player in AI-powered enterprise search.',
    events: [
      {
        date: '2002',
        title: 'Launch of Semantic Search Engine',
        description: 'Sinequa officially developed and launched its own semantic search engine tailored to enterprises.',
        category: 'Product Launch',
        location: 'France'
      },
      {
        date: '2024-05-22',
        title: 'Acquisition by ChapsVision',
        description: 'ChapsVision acquired Sinequa to reinforce its portfolio in AI-powered enterprise search.',
        category: 'Acquisition',
        location: 'Paris, France'
      }
    ]
  },
  products: {
    range: [
      {
        value: 'Sinequa Platform (modular, extensible enterprise search platform)',
        source: 'Initial report, product overview'
      },
      {
        value: 'Workplace Search (enterprise search solution)',
        source: 'Initial report, product overview'
      }
    ]
  },
  team: [
    {
      position: 'CEO',
      firstName: 'Olivier',
      lastName: 'Dellenbach',
      subordinates: [
        {
          position: 'Senior Vice President, Sales North America',
          firstName: 'Xavier',
          lastName: 'Pornain',
          subordinates: []
        }
      ]
    }
  ],
  created_at: new Date().toISOString(),
  updated_at: new Date().toISOString()
}

export const mockAccessToken = 'mock-access-token-for-testing'