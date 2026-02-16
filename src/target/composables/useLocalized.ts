import { computed, unref, type ComputedRef, type MaybeRef } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Localized } from '~/types/localized';

/**
 * Composable to get localized values from Localized objects
 * @returns An object with a getValue function that accepts a translation and returns a computed ref
 */
export function useLocalized() {
  const { locale } = useI18n();

  const getLocalizedString = (
    translation: MaybeRef<Localized | undefined>,
    defaultValue: string = '',
  ): ComputedRef<string> => {
    return computed(() => {
      const translationValue = unref(translation);
      return translationValue && translationValue[locale.value]
        ? translationValue[locale.value]
        : defaultValue;
    });
  };

  return {
    getLocalizedString,
  };
}
