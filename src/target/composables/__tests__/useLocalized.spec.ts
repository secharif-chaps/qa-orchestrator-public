import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useLocalized } from '~/composables/useLocalized'
import type { Localized } from '~/types/localized'

// Create a mutable locale ref for testing
const localeRef = ref('en-US')

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    locale: localeRef,
  }),
}))

describe('useLocalized', () => {
  beforeEach(() => {
    localeRef.value = 'en-US'
  })

  it('returns English value when locale is en-US', () => {
    const { getLocalizedString } = useLocalized()
    const translation: Localized = { en: 'Hello', fr: 'Bonjour' }

    const result = getLocalizedString(translation)
    expect(result.value).toBe('Hello')
  })

  it('returns default value when translation is undefined', () => {
    const { getLocalizedString } = useLocalized()

    const result = getLocalizedString(undefined, 'Default')
    expect(result.value).toBe('Default')
  })

  it('returns empty string when translation is undefined and no default', () => {
    const { getLocalizedString } = useLocalized()

    const result = getLocalizedString(undefined)
    expect(result.value).toBe('')
  })

  it('works with ref translations', () => {
    const { getLocalizedString } = useLocalized()
    const translation = ref<Localized>({ en: 'Test', fr: 'Tester' })

    const result = getLocalizedString(translation)
    expect(result.value).toBe('Test')
  })

  it('works with ref translations in French locale', () => {
    localeRef.value = 'fr-FR'
    const { getLocalizedString } = useLocalized()
    const translation = ref<Localized>({ en: 'Test', fr: 'Tester' })

    const result = getLocalizedString(translation)
    expect(result.value).toBe('Tester')
  })
})
