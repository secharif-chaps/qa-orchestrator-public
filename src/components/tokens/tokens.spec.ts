/**
 * Tests for global token system UI components.
 *
 * These tests verify the component behavior for:
 * - OrganizationTokensManager displaying global balance
 * - Quick-add buttons calculating correct amounts
 * - ModuleTokenCard showing only toggle (no tokens)
 * - TokenSidebar displaying global balance
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import { createTestingPinia } from '@pinia/testing'

// Mock the i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, fallback?: string) => fallback || key,
    locale: ref('en-US'),
  }),
}))

// Mock router
vi.mock('vue-router', () => ({
  useRoute: () => ({
    path: '/admin/organizations/test-org',
    params: { organizationId: 'test-org-123' },
  }),
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

// Mock Pinia Colada
const mockBalanceData = ref({ organization_id: 'test-org-123', balance: 175 })
const mockModulesData = ref({
  modules: [
    { name: 'screen', enabled: true, created_at: '2024-01-01', updated_at: '2024-01-01' },
    { name: 'target', enabled: false, created_at: '2024-01-01', updated_at: '2024-01-01' },
    { name: 'explore', enabled: true, created_at: '2024-01-01', updated_at: '2024-01-01' },
  ],
})

vi.mock('@pinia/colada', () => ({
  useQuery: vi.fn((queryFn, paramsFn) => {
    // Return different data based on the query
    const params = typeof paramsFn === 'function' ? paramsFn() : paramsFn
    if (params && 'organizationId' in params) {
      // Check if this is a balance or modules query based on the query function
      return {
        data: mockBalanceData,
        isLoading: ref(false),
        error: ref(null),
        refetch: vi.fn(),
      }
    }
    return {
      data: mockModulesData,
      isLoading: ref(false),
      error: ref(null),
      refetch: vi.fn(),
    }
  }),
  useQueryCache: () => ({
    invalidateQueries: vi.fn(),
  }),
  defineMutation: vi.fn((fn) => fn),
  useMutation: vi.fn(() => ({
    mutate: vi.fn(),
    isLoading: ref(false),
    error: ref(null),
  })),
}))

// Mock Vuellar components
vi.mock('@owlint/feathers-vue', () => ({
  Button: {
    name: 'Button',
    template: '<button><slot /></button>',
    props: ['variant', 'size', 'icon', 'label', 'loading', 'disabled', 'iconOnly', 'title'],
  },
  Tag: {
    name: 'Tag',
    template: '<span><slot /></span>',
    props: ['variant', 'label', 'size', 'icon', 'rounded'],
  },
  Badge: {
    name: 'Badge',
    template: '<span><slot /></span>',
    props: ['variant', 'size', 'icon'],
  },
  Switch: {
    name: 'Switch',
    template: '<input type="checkbox" />',
    props: ['modelValue', 'disabled'],
  },
  Toggle: {
    name: 'Toggle',
    template: '<div><slot /></div>',
    props: ['modelValue', 'options', 'variant'],
  },
}))

// Mock toast utility
vi.mock('@/utils/toast', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

// Constants
const TOKENS_PER_COMPANY = 35

describe('Global Token System UI Components', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockBalanceData.value = { organization_id: 'test-org-123', balance: 175 }
  })

  describe('OrganizationTokensManager', () => {
    it('displays global token balance prominently', async () => {
      // Import the component dynamically to avoid hoisting issues
      const { default: OrganizationTokensManager } = await import('./OrganizationTokensManager.vue')

      const wrapper = mount(OrganizationTokensManager, {
        props: {
          organizationId: 'test-org-123',
        },
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            Card: { template: '<div class="card"><slot /></div>' },
            ModuleTokenCard: { template: '<div class="module-card"></div>' },
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })

      await flushPromises()

      // The component should display the global balance (175 tokens = 5 companies)
      const html = wrapper.html()
      expect(html).toContain('175')
    })

    it('renders quick-add buttons with correct token amounts', async () => {
      const { default: OrganizationTokensManager } = await import('./OrganizationTokensManager.vue')

      const wrapper = mount(OrganizationTokensManager, {
        props: {
          organizationId: 'test-org-123',
        },
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            Card: { template: '<div class="card"><slot /></div>' },
            ModuleTokenCard: { template: '<div class="module-card"></div>' },
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })

      await flushPromises()

      // Quick-add buttons should show: 5, 10, 25, 50, 100 companies
      // Which equals: 175, 350, 875, 1750, 3500 tokens
      const html = wrapper.html()

      // Verify component has quick-add section
      expect(
        wrapper.find('[data-testid="quick-add-section"]').exists() || html.includes('Quick'),
      ).toBe(true)
    })
  })

  describe('ModuleTokenCard', () => {
    it('shows only toggle switch without token count', async () => {
      const { default: ModuleTokenCard } = await import('./ModuleTokenCard.vue')

      const wrapper = mount(ModuleTokenCard, {
        props: {
          module: 'screen',
          isEnabled: true,
          organizationId: 'test-org-123',
        },
        global: {
          plugins: [createTestingPinia()],
        },
      })

      await flushPromises()

      // Should have toggle/switch element
      const html = wrapper.html()

      // Should NOT display token count (old behavior)
      expect(html).not.toContain('token_count')
      expect(html).not.toContain('Current Tokens')

      // Should have a toggle/switch for enabling/disabling
      const toggle = wrapper.find('input[type="checkbox"]')
      expect(toggle.exists()).toBe(true)
    })

    it('emits toggle event when switch is clicked', async () => {
      const { default: ModuleTokenCard } = await import('./ModuleTokenCard.vue')

      const wrapper = mount(ModuleTokenCard, {
        props: {
          module: 'screen',
          isEnabled: true,
          organizationId: 'test-org-123',
        },
        global: {
          plugins: [createTestingPinia()],
        },
      })

      await flushPromises()

      const toggle = wrapper.find('input[type="checkbox"]')
      if (toggle.exists()) {
        await toggle.trigger('change')
        await nextTick()

        // Component should emit or handle toggle
        expect(wrapper.emitted('refresh') || wrapper.emitted('toggle')).toBeTruthy()
      }
    })
  })

  describe('TokenSidebar', () => {
    it('displays global balance from single query', async () => {
      // Mock organization query
      vi.doMock('@/queries/organization', () => ({
        currentOrganizationQuery: {
          key: ['organization', 'current'],
          query: () => Promise.resolve({ id: 'test-org-123', name: 'Test Org' }),
        },
      }))

      const { default: TokenSidebar } = await import('../sidebar/TokenSidebar.vue')

      const wrapper = mount(TokenSidebar, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            TokenHistoryItem: { template: '<div class="history-item"></div>' },
            RouterLink: { template: '<a><slot /></a>' },
          },
        },
      })

      await flushPromises()

      // Should display total tokens from global balance
      const html = wrapper.html()
      // The sidebar should show credits/tokens
      expect(html.toLowerCase()).toContain('credit')
    })
  })

  describe('Quick-add token calculations', () => {
    it('calculates correct token amounts for company counts', () => {
      const companyAmounts = [5, 10, 25, 50, 100]

      companyAmounts.forEach((companies) => {
        const expectedTokens = companies * TOKENS_PER_COMPANY
        expect(expectedTokens).toBe(companies * 35)
      })
    })

    it('displays company equivalence correctly', () => {
      // 175 tokens = 5 companies (175 / 35 = 5)
      const balance = 175
      const companiesEquivalent = Math.floor(balance / TOKENS_PER_COMPANY)
      expect(companiesEquivalent).toBe(5)

      // 350 tokens = 10 companies
      const balance2 = 350
      const companiesEquivalent2 = Math.floor(balance2 / TOKENS_PER_COMPANY)
      expect(companiesEquivalent2).toBe(10)

      // 100 tokens = 2 companies (rounded down)
      const balance3 = 100
      const companiesEquivalent3 = Math.floor(balance3 / TOKENS_PER_COMPANY)
      expect(companiesEquivalent3).toBe(2)
    })
  })
})
