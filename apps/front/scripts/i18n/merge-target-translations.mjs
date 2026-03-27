/**
 * Merges Target flat dot-notation translations into core nested JSON files.
 * Target keys win on overlap (matches current spread behavior).
 *
 * Usage: node scripts/i18n/merge-target-translations.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const rootDir = resolve(__dirname, '..')

/** Convert flat dot-notation object to nested object */
const flatToNested = (flat) => {
  const nested = {}
  for (const [key, value] of Object.entries(flat)) {
    const parts = key.split('.')
    let current = nested
    for (let i = 0; i < parts.length - 1; i++) {
      if (!(parts[i] in current) || typeof current[parts[i]] !== 'object') {
        current[parts[i]] = {}
      }
      current = current[parts[i]]
    }
    current[parts[parts.length - 1]] = value
  }
  return nested
}

/** Deep merge source into target. Source wins on conflict. */
const deepMerge = (target, source) => {
  const result = { ...target }
  for (const [key, value] of Object.entries(source)) {
    if (
      key in result &&
      typeof result[key] === 'object' &&
      result[key] !== null &&
      typeof value === 'object' &&
      value !== null
    ) {
      result[key] = deepMerge(result[key], value)
    } else {
      if (key in result && result[key] !== value) {
        console.log(`  ⚠️  Overlap: "${key}" — Target value wins`)
      }
      result[key] = value
    }
  }
  return result
}

/** Count all leaf keys recursively */
const countKeys = (obj, prefix = '') => {
  let count = 0
  for (const [key, value] of Object.entries(obj)) {
    if (typeof value === 'object' && value !== null) {
      count += countKeys(value, prefix ? `${prefix}.${key}` : key)
    } else {
      count++
    }
  }
  return count
}

const locales = ['en-US', 'fr-FR']

for (const locale of locales) {
  const corePath = resolve(rootDir, `src/i18n/locales/${locale}.json`)
  const targetPath = resolve(rootDir, `src/target/i18n/locales/${locale}.json`)

  const core = JSON.parse(readFileSync(corePath, 'utf-8'))
  const targetFlat = JSON.parse(readFileSync(targetPath, 'utf-8'))

  console.log(`\n📦 Processing ${locale}:`)
  console.log(`  Core keys: ${countKeys(core)}`)
  console.log(`  Target flat keys: ${Object.keys(targetFlat).length}`)

  const targetNested = flatToNested(targetFlat)
  const merged = deepMerge(core, targetNested)

  console.log(`  Merged keys: ${countKeys(merged)}`)

  writeFileSync(corePath, JSON.stringify(merged, null, 2) + '\n', 'utf-8')
  console.log(`  ✅ Written to ${locale}.json`)
}
