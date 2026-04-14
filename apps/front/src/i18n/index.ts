import MessageFormat from '@messageformat/core'
import enUS from '@/i18n/locales/en-US.json'
import { datetimeFormats } from '@/i18n/datetime-formats'
import { createI18n } from 'vue-i18n'
import type { MessageCompiler, MessageCompilerContext, MessageContext } from 'vue-i18n'

// ICU MessageFormat compiler — replaces vue-i18n's default pipe-based plural syntax
// with full ICU support (plural, select, selectordinal, nested combinations)
const messageCompiler: MessageCompiler = (message, { locale }: MessageCompilerContext) => {
  if (typeof message === 'string') {
    const mf = new MessageFormat(locale)
    const compiled = mf.compile(message)
    return (ctx: MessageContext) => compiled(ctx.values ?? {})
  }
  return () => String(message)
}

const DEFAULT_LOCALE = 'en-US'

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: DEFAULT_LOCALE,
  fallbackLocale: {
    'fr-CA': ['fr-FR', DEFAULT_LOCALE],
    default: [DEFAULT_LOCALE],
  },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
  messages: { [DEFAULT_LOCALE]: enUS },
  datetimeFormats,
  messageCompiler,
})

const loadedLanguages = new Set<string>([DEFAULT_LOCALE])
const pendingLoads = new Map<string, Promise<void>>()

/**
 * Dynamically load locale messages.
 * Concurrent calls for the same locale share a single import to avoid
 * duplicate network requests and redundant setLocaleMessage calls.
 */
export const loadLocaleMessages = async (locale: string): Promise<void> => {
  if (loadedLanguages.has(locale)) return

  const pending = pendingLoads.get(locale)
  if (pending) return pending

  const promise = import(`./locales/${locale}.json`)
    .then((messages) => {
      i18n.global.setLocaleMessage(locale, messages.default)
      loadedLanguages.add(locale)
    })
    .catch((error) => {
      console.error(`[i18n] Failed to load locale "${locale}"`, error)
      throw error
    })
    .finally(() => {
      pendingLoads.delete(locale)
    })

  pendingLoads.set(locale, promise)
  return promise
}

/**
 * Get the current locale code suitable for Intl APIs.
 * Centralizes locale resolution so callers don't duplicate the ternary.
 */
export const getLocale = (): string => i18n.global.locale.value || DEFAULT_LOCALE

export default i18n
