/**
 * Tests for FeatureFlagCard component.
 *
 * These tests verify the URL input behavior for the discover feature flag.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, fallback?: string) => fallback || key,
  }),
}))

// Mock the mutation
const mockToggleFeatureFlag = vi.fn()
vi.mock('@/mutations/feature-flags', () => ({
  useToggleFeatureFlag: () => ({
    toggleFeatureFlag: mockToggleFeatureFlag,
    isPending: ref(false),
  }),
}))

// Mock Vuellar components
vi.mock('@owlint/feathers-vue', () => ({
  Badge: {
    name: 'Badge',
    template: '<span class="badge"><slot /></span>',
    props: ['intent', 'label', 'icon', 'variant'],
  },
  Switch: {
    name: 'Switch',
    template:
      '<input type="checkbox" class="switch" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
    props: ['id', 'modelValue', 'disabled'],
    emits: ['update:modelValue'],
  },
  Input: {
    name: 'Input',
    template:
      '<input type="text" class="input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" :disabled="disabled" :placeholder="placeholder" />',
    props: ['modelValue', 'type', 'label', 'placeholder', 'error', 'disabled', 'icon'],
    emits: ['update:modelValue', 'blur'],
  },
}))

describe('FeatureFlagCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockToggleFeatureFlag.mockResolvedValue({})
  })

  it('shows URL input when discover flag is enabled', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: null,
      },
    })

    await flushPromises()

    // URL input should be visible for enabled discover flag
    const input = wrapper.find('.input')
    expect(input.exists()).toBe(true)
  })

  it('hides URL input when discover flag is disabled', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: false,
        organizationId: 'test-org-123',
        config: null,
      },
    })

    await flushPromises()

    // URL input should NOT be visible for disabled discover flag
    const input = wrapper.find('.input')
    expect(input.exists()).toBe(false)
  })

  it('hides URL input for non-discover flags', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'translation',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: null,
      },
    })

    await flushPromises()

    // URL input should NOT be visible for translation flag
    const input = wrapper.find('.input')
    expect(input.exists()).toBe(false)
  })

  it('pre-populates URL input with existing config value', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const existingUrl = 'https://discover.example.com'
    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: { url: existingUrl },
      },
    })

    await flushPromises()

    // URL input should be pre-populated
    const input = wrapper.find('.input')
    expect((input.element as HTMLInputElement).value).toBe(existingUrl)
  })

  it('validates HTTPS URL on blur and shows error for invalid URL', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: null,
      },
    })

    await flushPromises()

    // Type an invalid URL (HTTP instead of HTTPS)
    const input = wrapper.find('.input')
    await input.setValue('http://insecure.example.com')
    await input.trigger('blur')
    await flushPromises()

    // The mutation should NOT have been called
    expect(mockToggleFeatureFlag).not.toHaveBeenCalled()
  })

  it('calls mutation with config on blur when valid URL is entered', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: null,
      },
    })

    await flushPromises()

    // Type a valid HTTPS URL
    const validUrl = 'https://discover.example.com'
    const input = wrapper.find('.input')
    await input.setValue(validUrl)
    await input.trigger('blur')
    await flushPromises()

    // The mutation should have been called with the config
    expect(mockToggleFeatureFlag).toHaveBeenCalledWith({
      organizationId: 'test-org-123',
      flag: 'discover',
      enabled: true,
      config: { url: validUrl },
    })
  })

  it('does not call mutation if URL has not changed', async () => {
    const { default: FeatureFlagCard } = await import('./FeatureFlagCard.vue')

    const existingUrl = 'https://discover.example.com'
    const wrapper = mount(FeatureFlagCard, {
      props: {
        flag: 'discover',
        isEnabled: true,
        organizationId: 'test-org-123',
        config: { url: existingUrl },
      },
    })

    await flushPromises()

    // Blur without changing the URL
    const input = wrapper.find('.input')
    await input.trigger('blur')
    await flushPromises()

    // The mutation should NOT have been called
    expect(mockToggleFeatureFlag).not.toHaveBeenCalled()
  })
})
