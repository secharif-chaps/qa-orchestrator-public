/**
 * Tests for global token queries and mutations.
 *
 * These tests verify:
 * - organizationBalanceQuery returns correct structure
 * - tokenHistoryQuery works with pagination and filters
 * - useAddGlobalTokens mutation properly adds tokens
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ORGANIZATION_TOKEN_KEYS } from './tokens'
import type { TokenBalanceResponse, TokenHistoryResponse, TokenHistoryFilters } from '@/types/tokens'

// Mock the API client
const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('@/api/client', () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
    post: (...args: unknown[]) => mockPost(...args),
    put: vi.fn(),
  },
}))

// Mock toast for mutations
vi.mock('@/utils/toast', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

/**
 * Test factory for creating mock balance response
 */
function createMockBalanceResponse(overrides: Partial<TokenBalanceResponse> = {}): TokenBalanceResponse {
  return {
    organization_id: 'org-uuid-123',
    balance: 350,
    ...overrides,
  }
}

/**
 * Test factory for creating mock history response
 */
function createMockHistoryResponse(overrides: Partial<TokenHistoryResponse> = {}): TokenHistoryResponse {
  return {
    items: [
      {
        id: 1,
        organization_id: 'org-uuid-123',
        amount: 175,
        balance_after: 350,
        transaction_type: 'add',
        reference_type: 'manual',
        reference_id: null,
        created_at: '2024-01-15T10:00:00Z',
        created_by: 'admin-user-id',
      },
      {
        id: 2,
        organization_id: 'org-uuid-123',
        amount: -35,
        balance_after: 315,
        transaction_type: 'consume',
        reference_type: 'company',
        reference_id: 'company-123',
        created_at: '2024-01-15T11:00:00Z',
        created_by: 'user-id',
      },
    ],
    total: 2,
    page: 1,
    size: 10,
    pages: 1,
    ...overrides,
  }
}

describe('ORGANIZATION_TOKEN_KEYS', () => {
  describe('key generation', () => {
    it('generates correct root key', () => {
      expect(ORGANIZATION_TOKEN_KEYS.root).toEqual(['organization-tokens'])
    })

    it('generates correct balance key for organization', () => {
      const orgId = 'org-uuid-123'
      const key = ORGANIZATION_TOKEN_KEYS.balance(orgId)

      expect(key).toEqual(['organization-tokens', 'balance', 'org-uuid-123'])
    })

    it('generates correct history key with filters as serialized string', () => {
      const orgId = 'org-uuid-123'
      const filters: TokenHistoryFilters = { page: 1, size: 10, transaction_type: 'add' }
      const key = ORGANIZATION_TOKEN_KEYS.history(orgId, filters)

      // Filters are serialized to a JSON string with sorted keys
      expect(key[0]).toBe('organization-tokens')
      expect(key[1]).toBe('history')
      expect(key[2]).toBe('org-uuid-123')
      expect(typeof key[3]).toBe('string')
      // Verify the serialized string contains the filter values
      expect(key[3]).toContain('"page":1')
      expect(key[3]).toContain('"size":10')
      expect(key[3]).toContain('"transaction_type":"add"')
    })

    it('generates correct modules key for organization', () => {
      const orgId = 'org-uuid-123'
      const key = ORGANIZATION_TOKEN_KEYS.modules(orgId)

      expect(key).toEqual(['organization-tokens', 'modules', 'org-uuid-123'])
    })

    it('generates different keys for different organizations', () => {
      const key1 = ORGANIZATION_TOKEN_KEYS.balance('org-1')
      const key2 = ORGANIZATION_TOKEN_KEYS.balance('org-2')

      expect(key1).not.toEqual(key2)
    })

    it('generates different keys for different history filters', () => {
      const orgId = 'org-uuid-123'
      const key1 = ORGANIZATION_TOKEN_KEYS.history(orgId, { page: 1 })
      const key2 = ORGANIZATION_TOKEN_KEYS.history(orgId, { page: 2 })

      expect(key1).not.toEqual(key2)
    })

    it('generates consistent keys regardless of filter property order', () => {
      const orgId = 'org-uuid-123'
      // Same filters but different property order in source
      const key1 = ORGANIZATION_TOKEN_KEYS.history(orgId, { page: 1, size: 10 })
      const key2 = ORGANIZATION_TOKEN_KEYS.history(orgId, { size: 10, page: 1 })

      // Keys should be identical due to sorted serialization
      expect(key1).toEqual(key2)
    })
  })
})

describe('organizationBalanceQuery', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns correct balance structure from API', async () => {
    const mockResponse = createMockBalanceResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    // Import the API function directly to test
    const { getOrganizationBalance } = await import('@/api/tokens')
    const result = await getOrganizationBalance('org-uuid-123')

    expect(result).toEqual(mockResponse)
    expect(result.organization_id).toBe('org-uuid-123')
    expect(result.balance).toBe(350)
  })

  it('calls correct API endpoint', async () => {
    const mockResponse = createMockBalanceResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getOrganizationBalance } = await import('@/api/tokens')
    await getOrganizationBalance('org-uuid-123')

    expect(mockGet).toHaveBeenCalledWith('/organizations/org-uuid-123/tokens')
  })

  it('handles organization with zero balance', async () => {
    const mockResponse = createMockBalanceResponse({ balance: 0 })
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getOrganizationBalance } = await import('@/api/tokens')
    const result = await getOrganizationBalance('org-uuid-123')

    expect(result.balance).toBe(0)
  })
})

describe('tokenHistoryQuery', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns paginated history response', async () => {
    const mockResponse = createMockHistoryResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getTokenHistory } = await import('@/api/tokens')
    const result = await getTokenHistory('org-uuid-123', { page: 1, size: 10 })

    expect(result.items).toHaveLength(2)
    expect(result.total).toBe(2)
    expect(result.page).toBe(1)
    expect(result.size).toBe(10)
  })

  it('includes transaction details in history items', async () => {
    const mockResponse = createMockHistoryResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getTokenHistory } = await import('@/api/tokens')
    const result = await getTokenHistory('org-uuid-123', {})

    const addTransaction = result.items[0]
    expect(addTransaction.transaction_type).toBe('add')
    expect(addTransaction.amount).toBe(175)
    expect(addTransaction.balance_after).toBe(350)
    expect(addTransaction.reference_type).toBe('manual')

    const consumeTransaction = result.items[1]
    expect(consumeTransaction.transaction_type).toBe('consume')
    expect(consumeTransaction.amount).toBe(-35)
    expect(consumeTransaction.reference_type).toBe('company')
    expect(consumeTransaction.reference_id).toBe('company-123')
  })

  it('builds correct URL with filters', async () => {
    const mockResponse = createMockHistoryResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getTokenHistory } = await import('@/api/tokens')
    await getTokenHistory('org-uuid-123', {
      transaction_type: 'consume',
      reference_type: 'company',
      page: 2,
      size: 25,
    })

    expect(mockGet).toHaveBeenCalledWith(
      expect.stringContaining('/organizations/org-uuid-123/tokens/history?')
    )
    const calledUrl = mockGet.mock.calls[0][0] as string
    expect(calledUrl).toContain('transaction_type=consume')
    expect(calledUrl).toContain('reference_type=company')
    expect(calledUrl).toContain('page=2')
    expect(calledUrl).toContain('size=25')
  })

  it('handles empty filters', async () => {
    const mockResponse = createMockHistoryResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getTokenHistory } = await import('@/api/tokens')
    await getTokenHistory('org-uuid-123', {})

    expect(mockGet).toHaveBeenCalledWith('/organizations/org-uuid-123/tokens/history')
  })

  it('handles date range filters', async () => {
    const mockResponse = createMockHistoryResponse()
    mockGet.mockResolvedValueOnce(mockResponse)

    const { getTokenHistory } = await import('@/api/tokens')
    await getTokenHistory('org-uuid-123', {
      date_from: '2024-01-01',
      date_to: '2024-01-31',
    })

    const calledUrl = mockGet.mock.calls[0][0] as string
    expect(calledUrl).toContain('date_from=2024-01-01')
    expect(calledUrl).toContain('date_to=2024-01-31')
  })
})

describe('useAddGlobalTokens mutation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls correct API endpoint with amount', async () => {
    const mockResponse = createMockBalanceResponse({ balance: 525 })
    mockPost.mockResolvedValueOnce(mockResponse)

    const { addOrganizationTokens } = await import('@/api/tokens')
    const result = await addOrganizationTokens('org-uuid-123', 175)

    expect(mockPost).toHaveBeenCalledWith('/organizations/org-uuid-123/tokens', { amount: 175 })
    expect(result.balance).toBe(525)
  })

  it('returns updated balance after adding tokens', async () => {
    const mockResponse = createMockBalanceResponse({ balance: 700 })
    mockPost.mockResolvedValueOnce(mockResponse)

    const { addOrganizationTokens } = await import('@/api/tokens')
    const result = await addOrganizationTokens('org-uuid-123', 350)

    expect(result.organization_id).toBe('org-uuid-123')
    expect(result.balance).toBe(700)
  })
})
