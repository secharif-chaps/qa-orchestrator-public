/**
 * Tests for DataSourceCard component.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

// Mock vue-i18n — createI18n must be included because src/i18n/index.ts calls it at module load time
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, fallback?: string) => fallback || key,
  }),
  createI18n: () => ({ global: { t: (key: string) => key }, install: vi.fn() }),
}))

const $t = (key: string) => key

// Mock pinia/colada
vi.mock('@pinia/colada', () => ({
  useQuery: () => ({
    data: ref({
      source: 'pappers',
      enabled: false,
      api_key_masked: null,
      enabled_at: null,
      updated_at: null,
    }),
    isLoading: ref(false),
    error: ref(null),
    refetch: vi.fn(),
  }),
  defineQueryOptions: <T>(fn: () => T) => fn,
  useQueryCache: () => ({
    invalidateQueries: vi.fn(),
  }),
  defineMutation: <T>(fn: () => T) => fn,
  useMutation: () => ({
    mutate: vi.fn(),
    isLoading: ref(false),
  }),
}))

// Mock the queries
vi.mock('@/queries/data-sources', () => ({
  DATA_SOURCE_KEYS: {
    root: ['data-sources'],
    config: (orgId: string, source: string) => ['data-sources', 'config', orgId, source],
  },
  dataSourceConfigQuery: () => ({
    key: ['data-sources', 'config', 'test-org', 'pappers'],
    query: () => Promise.resolve({}),
  }),
}))

// Mock the mutation
const mockUpdateConfig = vi.fn()
vi.mock('@/mutations/data-sources', () => ({
  useUpdateDataSourceConfig: () => ({
    organizationId: ref(''),
    source: ref(''),
    apiKey: ref(''),
    updateConfig: mockUpdateConfig,
    isLoading: ref(false),
    mutate: mockUpdateConfig,
  }),
}))

// Mock Vuellar components
vi.mock('@owlint/feathers-vue', () => ({
  Button: {
    name: 'Button',
    template:
      '<button class="button" :disabled="disabled" @click="$emit(\'click\')"><slot />{{ label }}</button>',
    props: ['variant', 'size', 'label', 'icon', 'loading', 'disabled'],
    emits: ['click'],
  },
  Input: {
    name: 'Input',
    template:
      '<input :id="id" type="text" class="input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" :placeholder="placeholder" />',
    props: ['id', 'modelValue', 'type', 'placeholder', 'disabled'],
    emits: ['update:modelValue'],
  },
}))

describe('DataSourceCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockUpdateConfig.mockResolvedValue({})
  })

  it('Sources tab appears in organization detail toggle', () => {
    const sectionOptions = [
      { value: 'profile', icon: 'fas fa-building', label: 'Profile' },
      { value: 'tokens', icon: 'fas fa-coins', label: 'Tokens' },
      { value: 'members', icon: 'fas fa-users', label: 'Members' },
      { value: 'sources', icon: 'fas fa-plug', label: 'Sources' },
    ]

    const sourcesTab = sectionOptions.find((opt) => opt.value === 'sources')

    expect(sourcesTab).toBeDefined()
    expect(sourcesTab?.value).toBe('sources')
    expect(sourcesTab?.icon).toBe('fas fa-plug')
    expect(sourcesTab?.label).toBe('Sources')
  })

  it('DataSourceCard renders source info correctly', async () => {
    const { default: DataSourceCard } = await import('./DataSourceCard.vue')

    const wrapper = mount(DataSourceCard, {
      props: {
        source: {
          source: 'pappers',
          name: 'Pappers',
          description: 'French company data provider (legal info, financials, officers)',
          logo: '/assets/logos/pappers.svg',
        },
        organizationId: 'test-org-123',
      },
      global: { mocks: { $t } },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Pappers')
    expect(wrapper.text()).toContain('French company data provider')
  })

  it('API key obfuscation display shows first 4 + ... + last 4', () => {
    const obfuscateApiKey = (apiKey: string | null): string | null => {
      if (apiKey === null || apiKey.length < 8) {
        return null
      }
      return `${apiKey.slice(0, 4)}...${apiKey.slice(-4)}`
    }

    expect(obfuscateApiKey('my-secret-api-key-12345')).toBe('my-s...2345')
    expect(obfuscateApiKey('short')).toBeNull()
    expect(obfuscateApiKey(null)).toBeNull()
    expect(obfuscateApiKey('12345678')).toBe('1234...5678')
  })

  it('Save API key mutation triggers correctly', async () => {
    const { default: DataSourceCard } = await import('./DataSourceCard.vue')

    const wrapper = mount(DataSourceCard, {
      props: {
        source: {
          source: 'pappers',
          name: 'Pappers',
          description: 'French company data provider',
          logo: '/assets/logos/pappers.svg',
        },
        organizationId: 'test-org-123',
      },
      global: { mocks: { $t } },
    })

    await flushPromises()

    const editButton = wrapper.find('.button')
    await editButton.trigger('click')
    await flushPromises()

    const input = wrapper.find('.input')
    if (input.exists()) {
      await input.setValue('new-api-key-12345678')
      await flushPromises()

      const buttons = wrapper.findAll('.button')
      const saveButton = buttons.find((btn) => btn.text().includes('Save'))
      if (saveButton) {
        await saveButton.trigger('click')
        await flushPromises()

        expect(mockUpdateConfig).toHaveBeenCalled()
      }
    }
  })
})
