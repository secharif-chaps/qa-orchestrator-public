/**
 * Tests for ModulesShowcase component.
 *
 * Verifies dynamic module card rendering based on feature flags and module data.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { FeatureFlagConfig } from '@/types/feature-flags'
import type { ModuleConfig } from '@/types/tokens'

// Mock vue-i18n
const translations: Record<string, string> = {
  'dashboard.home.modules.screen.cardTitle': 'Screen',
  'dashboard.home.modules.screen.cardDescription': 'Analyze your stakeholders',
  'dashboard.home.modules.screen.cardStat': '12 analyses',
  'dashboard.home.modules.screen.cardAction': 'New card',
  'dashboard.home.modules.target.cardTitle': 'Target',
  'dashboard.home.modules.target.cardDescription': 'Master your strategic issues',
  'dashboard.home.modules.target.cardStat': '8 active watch files',
  'dashboard.home.modules.target.cardAction': 'New watch file',
  'dashboard.home.modules.explore.cardTitle': 'Cartography',
  'dashboard.home.modules.explore.cardDescription': 'Decode your ecosystem',
  'dashboard.home.modules.explore.cardStat': '24 graphs',
  'dashboard.home.modules.explore.cardAction': 'New cartography',
  'dashboard.home.modules.discover.cardTitle': 'Discover',
  'dashboard.home.modules.discover.cardDescription': 'Give access to information',
  'dashboard.home.modules.discover.cardStat': '8 active dashboards',
  'dashboard.home.modules.discover.cardAction': 'New dashboard',
  'dashboard.home.modules.status.comingSoon': 'Coming Soon',
}
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => translations[key] || key,
  }),
}))

// Mock vue-router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

// Mock Vuellar components
vi.mock('@owlint/feathers-vue', () => ({
  Button: {
    name: 'Button',
    template:
      '<button class="button" :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    props: ['variant', 'size', 'label', 'iconRight', 'disabled'],
    emits: ['click'],
  },
  Icon: {
    name: 'Icon',
    template: '<i :class="icon"></i>',
    props: ['icon'],
  },
  Tag: {
    name: 'Tag',
    template: '<span class="tag">{{ label }}<slot /></span>',
    props: ['intent', 'color', 'variant', 'size', 'label', 'icon'],
  },
}))

// Mock CmdBadge component
vi.mock('../ui/CmdBadge.vue', () => ({
  default: {
    name: 'CmdBadge',
    template: '<div class="cmd-badge" :data-color="color"><i :class="icon"></i></div>',
    props: ['icon', 'color'],
  },
}))

// Mock window.open
const mockWindowOpen = vi.fn()
Object.defineProperty(window, 'open', {
  value: mockWindowOpen,
  writable: true,
})

const globalMocks = {
  global: {
    mocks: {
      $t: (key: string) => translations[key] || key,
    },
  },
}

const enabledModules: ModuleConfig[] = [
  {
    name: 'screen',
    enabled: true,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '',
  },
  {
    name: 'target',
    enabled: true,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '',
  },
  {
    name: 'explore',
    enabled: false,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '',
  },
]

describe('ModulesShowcase', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockWindowOpen.mockReset()
  })

  it('renders all 4 module cards', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags: [], modulesData: enabledModules },
      ...globalMocks,
    })

    await flushPromises()

    const html = wrapper.html()
    expect(html).toContain('Screen')
    expect(html).toContain('Target')
    expect(html).toContain('Cartography')
    expect(html).toContain('Discover')
  })

  it('shows default state for enabled modules', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags: [], modulesData: enabledModules },
      ...globalMocks,
    })

    await flushPromises()

    // Screen is enabled — should have action button
    const buttons = wrapper.findAll('.button')
    const screenButton = buttons.find((btn) => btn.html().includes('New card'))
    expect(screenButton).toBeDefined()
    expect(screenButton?.attributes('disabled')).toBeUndefined()
  })

  it('shows disabled state for disabled modules', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags: [], modulesData: enabledModules },
      ...globalMocks,
    })

    await flushPromises()

    // Explore is disabled — badge should have disabled color
    const badges = wrapper.findAll('.cmd-badge')
    const disabledBadge = badges.find((b) => b.attributes('data-color') === 'disabled')
    expect(disabledBadge).toBeDefined()
  })

  it('shows soon state for modules not in modulesData', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    // Only screen in modulesData — target and explore are "soon"
    const wrapper = mount(ModulesShowcase, {
      props: {
        featureFlags: [],
        modulesData: [
          {
            name: 'screen',
            enabled: true,
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '',
          },
        ],
      },
      ...globalMocks,
    })

    await flushPromises()

    // Should have "Coming Soon" tags for modules not in data
    const tags = wrapper.findAll('.tag')
    const soonTags = tags.filter((t) => t.html().includes('Coming Soon'))
    expect(soonTags.length).toBeGreaterThanOrEqual(2) // target, explore, discover
  })

  it('shows Discover as default when feature flag is enabled', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const featureFlags: FeatureFlagConfig[] = [
      {
        flag: 'discover',
        enabled: true,
        enabled_at: '2024-01-01T00:00:00Z',
        config: { url: 'https://discover.example.com' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: null,
      },
    ]

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags, modulesData: enabledModules },
      ...globalMocks,
    })

    await flushPromises()

    // Discover should have an action button
    const buttons = wrapper.findAll('.button')
    const discoverButton = buttons.find((btn) => btn.html().includes('New dashboard'))
    expect(discoverButton).toBeDefined()
  })

  it('shows Discover as soon when feature flag is disabled', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const wrapper = mount(ModulesShowcase, {
      props: {
        featureFlags: [
          {
            flag: 'discover',
            enabled: false,
            enabled_at: null,
            config: null,
            created_at: '2024-01-01T00:00:00Z',
            updated_at: null,
          },
        ],
        modulesData: enabledModules,
      },
      ...globalMocks,
    })

    await flushPromises()

    // Discover badge should have cyan color (soon, not disabled)
    const badges = wrapper.findAll('.cmd-badge')
    const cyanBadge = badges.find((b) => b.attributes('data-color') === 'cyan')
    expect(cyanBadge).toBeDefined()
  })

  it('opens URL in new tab when Discover action is clicked', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const discoverUrl = 'https://discover.example.com'
    const featureFlags: FeatureFlagConfig[] = [
      {
        flag: 'discover',
        enabled: true,
        enabled_at: '2024-01-01T00:00:00Z',
        config: { url: discoverUrl },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: null,
      },
    ]

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags, modulesData: enabledModules },
      ...globalMocks,
    })

    await flushPromises()

    // Find and click Discover button
    const buttons = wrapper.findAll('.button')
    const discoverButton = buttons.find((btn) => btn.html().includes('New dashboard'))
    await discoverButton?.trigger('click')
    await flushPromises()

    expect(mockWindowOpen).toHaveBeenCalledWith(discoverUrl, '_blank', 'noopener,noreferrer')
  })
})
