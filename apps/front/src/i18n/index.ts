import MessageFormat from '@messageformat/core'
import enUS from '@/i18n/locales/en-US.json'
import frFR from '@/i18n/locales/fr-FR.json'
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

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  fallbackLocale: {
    'fr-CA': ['fr-FR', 'en-US'],
    default: ['en-US'],
  },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
  messages: { 'en-US': enUS, 'fr-FR': frFR },
  datetimeFormats,
  messageCompiler,
})

export default i18n
