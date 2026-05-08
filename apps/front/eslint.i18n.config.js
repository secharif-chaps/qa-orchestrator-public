// Dedicated ESLint config for ICU MessageFormat validation on locale files.
// Separated from the main eslint.config.ts because @intlify/eslint-plugin-vue-i18n
// registers jsonc-eslint-parser on *.json files, which conflicts with
// eslint-plugin-i18n-json's processor.
//
// Usage: npx eslint --config eslint.i18n.config.js src/i18n/locales/

// @ts-check
/** @type {import('eslint').Linter.Config[]} */
import pluginI18nJson from 'eslint-plugin-i18n-json'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const referenceLocalePath = path.resolve(__dirname, 'src/i18n/locales/en-US.json')

export default [
  {
    files: ['src/i18n/locales/*.json'],
    plugins: {
      'i18n-json': pluginI18nJson,
    },
    processor: pluginI18nJson.processors['.json'],
    rules: {
      'i18n-json/valid-message-syntax': ['error', { syntax: 'icu' }],
      'i18n-json/valid-json': 'error',
      'i18n-json/sorted-keys': 'off',
      // en-US is the reference locale — every other locale must mirror its key structure
      // (catches keys added to fr-FR but forgotten in en-US, and vice versa).
      'i18n-json/identical-keys': ['error', { filePath: referenceLocalePath }],
      // Ensure ICU placeholders match across locales (e.g. {name} in en-US must also appear in fr-FR).
      'i18n-json/identical-placeholders': ['error', { filePath: referenceLocalePath }],
    },
  },
]
