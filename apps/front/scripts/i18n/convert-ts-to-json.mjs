/**
 * Converts locale .ts files to .json format.
 * Uses jiti to dynamically import TypeScript modules.
 *
 * Usage: node scripts/i18n/convert-ts-to-json.mjs
 */
import { createJiti } from 'jiti'
import { writeFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const rootDir = resolve(__dirname, '..')

const jiti = createJiti(import.meta.url, {
  alias: {
    '@': resolve(rootDir, 'src'),
    '@target': resolve(rootDir, 'src/target'),
  },
})

const locales = ['en-US', 'fr-FR']

for (const locale of locales) {
  const tsPath = resolve(rootDir, `src/i18n/locales/${locale}.ts`)
  const jsonPath = resolve(rootDir, `src/i18n/locales/${locale}.json`)

  const mod = await jiti.import(tsPath)
  const data = mod.default || mod

  writeFileSync(jsonPath, JSON.stringify(data, null, 2) + '\n', 'utf-8')
  console.log(
    `✅ Converted ${locale}.ts → ${locale}.json (${Object.keys(data).length} top-level keys)`,
  )
}
