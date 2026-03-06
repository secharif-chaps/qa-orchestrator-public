/**
 * Tests for ModulesShowcase component.
 *
 * These tests verify the dynamic Discover module behavior based on feature flags.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { FeatureFlagConfig } from '@/types/feature-flags'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, fallback?: string) => fallback || key,
  }),
}))

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

// Mock Vuellar components
vi.mock('@owlint/feathers-vue', () => ({
  Tag: {
    name: 'Tag',
    template: '<span class="tag">{{ label }}<slot /></span>',
    props: ['intent', 'label', 'size'],
  },
  Button: {
    name: 'Button',
    template:
      '<button class="button" :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
    props: ['variant', 'intent', 'size', 'label', 'icon', 'disabled'],
    emits: ['click'],
  },
  Badge: {
    name: 'Badge',
    template: '<span class="badge"><slot /></span>',
    props: ['color', 'icon', 'variant'],
  },
}))

// Mock Card component
vi.mock('../ui/Card.vue', () => ({
  default: {
    name: 'Card',
    template: '<div class="card" :data-disabled="disabled"><slot /></div>',
    props: ['hoverable', 'clickable', 'disabled'],
  },
}))

// Mock window.open
const mockWindowOpen = vi.fn()
Object.defineProperty(window, 'open', { value: mockWindowOpen, writable: true })

describe('ModulesShowcase', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockWindowOpen.mockReset()
  })

  it('shows Discover module with Contact Sales button when feature flag is disabled', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const featureFlags: FeatureFlagConfig[] = [
      {
        flag: 'discover',
        enabled: false,
        enabled_at: null,
        config: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: null,
      },
    ]

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags },
    })

    await flushPromises()

    // Should show 4 modules (Screen, Target, Explore, Discover)
    const cards = wrapper.findAll('.card')
    expect(cards.length).toBe(4)

    // Discover should be visible
    const html = wrapper.html()
    expect(html).toContain('Discover')

    // Should show Contact Sales button (not Open)
    expect(html).toContain('Contact Sales')
  })

  it('shows Discover module with Contact Sales button when no feature flags provided', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags: [] },
    })

    await flushPromises()

    // Should show 4 modules (including Discover as Pro Feature)
    const cards = wrapper.findAll('.card')
    expect(cards.length).toBe(4)

    // Discover should be visible with Contact Sales
    const html = wrapper.html()
    expect(html).toContain('Discover')
    expect(html).toContain('Contact Sales')
  })

  it('shows Discover module with disabled button when enabled but no URL configured', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const featureFlags: FeatureFlagConfig[] = [
      {
        flag: 'discover',
        enabled: true,
        enabled_at: '2024-01-01T00:00:00Z',
        config: null, // No URL configured
        created_at: '2024-01-01T00:00:00Z',
        updated_at: null,
      },
    ]

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags },
    })

    await flushPromises()

    // Should show 4 modules now (including Discover)
    const cards = wrapper.findAll('.card')
    expect(cards.length).toBe(4)

    // Discover should be visible
    const html = wrapper.html()
    expect(html).toContain('Discover')

    // Find the Open button and verify it's disabled
    const buttons = wrapper.findAll('.button')
    const openButton = buttons.find((btn) => btn.html().includes('Open'))
    expect(openButton?.attributes('disabled')).toBeDefined()
  })

  it('shows Discover module with enabled button when URL is configured', async () => {
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
      props: { featureFlags },
    })

    await flushPromises()

    // Should show 4 modules
    const cards = wrapper.findAll('.card')
    expect(cards.length).toBe(4)

    // Discover should be visible
    const html = wrapper.html()
    expect(html).toContain('Discover')

    // Find the Open button and verify it's NOT disabled
    const buttons = wrapper.findAll('.button')
    const openButton = buttons.find((btn) => btn.html().includes('Open'))
    expect(openButton?.attributes('disabled')).toBeUndefined()
  })

  it('opens URL in new tab when enabled button is clicked', async () => {
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
      props: { featureFlags },
    })

    await flushPromises()

    // Find the Open button for Discover module
    const buttons = wrapper.findAll('.button')
    const openButton = buttons.find((btn) => btn.html().includes('Open'))
    expect(openButton).toBeDefined()

    // Click the button
    await openButton?.trigger('click')
    await flushPromises()

    // Verify window.open was called with correct arguments
    expect(mockWindowOpen).toHaveBeenCalledWith(discoverUrl, '_blank')
  })

  it('does not call window.open when button is disabled (no URL)', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    const featureFlags: FeatureFlagConfig[] = [
      {
        flag: 'discover',
        enabled: true,
        enabled_at: '2024-01-01T00:00:00Z',
        config: null, // No URL
        created_at: '2024-01-01T00:00:00Z',
        updated_at: null,
      },
    ]

    const wrapper = mount(ModulesShowcase, {
      props: { featureFlags },
    })

    await flushPromises()

    // Find the Open button for Discover module
    const buttons = wrapper.findAll('.button')
    const openButton = buttons.find((btn) => btn.html().includes('Open'))

    // The button should be disabled, but we still test the handler
    // In real implementation, disabled button won't trigger click
    // But the handler also has a check for externalUrl
    await openButton?.trigger('click')
    await flushPromises()

    // window.open should NOT have been called
    expect(mockWindowOpen).not.toHaveBeenCalled()
  })

  it('changes Discover button from Contact Sales to Open when toggled', async () => {
    const { default: ModulesShowcase } = await import('./ModulesShowcase.vue')

    // First render with Discover disabled
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
      },
    })

    await flushPromises()

    // Should have 4 modules (Discover always visible now)
    expect(wrapper.findAll('.card').length).toBe(4)

    // Verify all modules are present, Discover shows Contact Sales
    const html = wrapper.html()
    expect(html).toContain('Screen')
    expect(html).toContain('Target')
    expect(html).toContain('Explore')
    expect(html).toContain('Discover')
    expect(html).toContain('Contact Sales')

    // Update props to enable Discover
    await wrapper.setProps({
      featureFlags: [
        {
          flag: 'discover',
          enabled: true,
          enabled_at: '2024-01-01T00:00:00Z',
          config: { url: 'https://discover.example.com' },
          created_at: '2024-01-01T00:00:00Z',
          updated_at: null,
        },
      ],
    })

    await flushPromises()

    // Should still have 4 modules
    expect(wrapper.findAll('.card').length).toBe(4)

    // All modules should still be present, Discover now shows Open
    const updatedHtml = wrapper.html()
    expect(updatedHtml).toContain('Screen')
    expect(updatedHtml).toContain('Target')
    expect(updatedHtml).toContain('Explore')
    expect(updatedHtml).toContain('Discover')
    expect(updatedHtml).toContain('Open')
  })
})
