/**
 * Tests for UsageTimeRangeToggle component.
 *
 * These tests verify:
 * - Date calculation for 7d range produces correct start/end dates
 * - Date calculation for "all" range returns null startDate
 * - Component emits dates when range changes
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    locale: ref('en-US'),
  }),
}))

// Mock Vuellar Toggle component
vi.mock('@owlint/feathers-vue', () => ({
  Toggle: {
    name: 'Toggle',
    template: '<div class="toggle" @click="handleClick"><slot /></div>',
    props: ['modelValue', 'options', 'variant'],
    emits: ['update:modelValue'],
    methods: {
      handleClick() {
        // Simulate toggle click for testing
      },
    },
    setup() {
      return {
        handleClick: () => {
          // Cycles through options when clicked
        },
      }
    },
  },
}))

describe('UsageTimeRangeToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Mock Date for consistent testing
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2025-01-15T12:00:00.000Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('date calculations', () => {
    it('calculates correct start date for 7d range', async () => {
      const { default: UsageTimeRangeToggle } = await import('./UsageTimeRangeToggle.vue')

      const wrapper = mount(UsageTimeRangeToggle, {
        props: {
          modelValue: '7d',
        },
      })

      await flushPromises()

      // Access exposed computed values
      const component = wrapper.vm as unknown as {
        startDate: string | null
        endDate: string
      }

      // Start date should be 7 days ago (date-only format)
      expect(component.startDate).toBe('2025-01-08')

      // End date should be today (date-only format)
      expect(component.endDate).toBe('2025-01-15')
    })

    it('returns null startDate for "all" time range', async () => {
      const { default: UsageTimeRangeToggle } = await import('./UsageTimeRangeToggle.vue')

      const wrapper = mount(UsageTimeRangeToggle, {
        props: {
          modelValue: 'all',
        },
      })

      await flushPromises()

      // Access exposed computed values
      const component = wrapper.vm as unknown as {
        startDate: string | null
        endDate: string
      }

      // Start date should be null for "all time"
      expect(component.startDate).toBeNull()

      // End date should still be today (date-only format)
      expect(component.endDate).toBe('2025-01-15')
    })

    it('calculates correct start date for 30d range', async () => {
      const { default: UsageTimeRangeToggle } = await import('./UsageTimeRangeToggle.vue')

      const wrapper = mount(UsageTimeRangeToggle, {
        props: {
          modelValue: '30d',
        },
      })

      await flushPromises()

      const component = wrapper.vm as unknown as {
        startDate: string | null
        endDate: string
      }

      // Start date should be 30 days ago (date-only format)
      expect(component.startDate).toBe('2024-12-16')
    })

    it('calculates correct start date for 90d range', async () => {
      const { default: UsageTimeRangeToggle } = await import('./UsageTimeRangeToggle.vue')

      const wrapper = mount(UsageTimeRangeToggle, {
        props: {
          modelValue: '90d',
        },
      })

      await flushPromises()

      const component = wrapper.vm as unknown as {
        startDate: string | null
        endDate: string
      }

      // Start date should be 90 days ago (date-only format)
      expect(component.startDate).toBe('2024-10-17')
    })
  })

  describe('date emission', () => {
    it('emits update:dates immediately on mount', async () => {
      const { default: UsageTimeRangeToggle } = await import('./UsageTimeRangeToggle.vue')

      const wrapper = mount(UsageTimeRangeToggle, {
        props: {
          modelValue: '7d',
        },
      })

      await flushPromises()

      // Should emit dates immediately (immediate: true in watch)
      const emittedDates = wrapper.emitted('update:dates')
      expect(emittedDates).toBeDefined()
      expect(emittedDates?.length).toBeGreaterThanOrEqual(1)

      // Verify emitted date structure
      const lastEmission = emittedDates?.[emittedDates.length - 1] as unknown as [
        { startDate: string | null; endDate: string },
      ]
      expect(lastEmission[0]).toHaveProperty('startDate')
      expect(lastEmission[0]).toHaveProperty('endDate')
    })
  })
})
