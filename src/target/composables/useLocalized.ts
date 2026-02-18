import { computed, unref, type ComputedRef, type MaybeRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Localized } from '~/types/localized'

/**
 * Composable to get localized values from Localized objects
 * @returns An object with a getValue function that accepts a translation and returns a computed ref
 */
export function useLocalized() {
  const { locale } = useI18n()

  // Map full locale codes (en-US, fr-FR) to short codes (en, fr) used in backend Localized objects
  const shortLocale = computed(() => locale.value.split('-')[0] as keyof Localized)

  const getLocalizedString = (
    translation: MaybeRef<Localized | undefined>,
    defaultValue: string = '',
  ): ComputedRef<string> => {
    return computed(() => {
      const translationValue = unref(translation)
      return translationValue && translationValue[shortLocale.value]
        ? translationValue[shortLocale.value]
        : defaultValue
    })
  }

  const setLocale = (newLocale: string) => {
    locale.value = newLocale
  }

  return {
    getLocalizedString,
    shortLocale,
    setLocale,
  }
}
