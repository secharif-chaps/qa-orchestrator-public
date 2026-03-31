#!/usr/bin/env node
/**
 * i18n:find — Search for a key across all locale files.
 * Usage: node scripts/i18n/find.mjs <key>
 */
import { loadLocales } from './utils.mjs'

const key = process.argv[2]
if (!key) {
  console.error('Usage: node scripts/i18n/find.mjs <key>')
  console.error('Example: node scripts/i18n/find.mjs company.delete.title')
  process.exit(1)
}

const locales = loadLocales()

/** Resolve a dot-notation key in a nested object */
const resolveKey = (obj, path) => {
  const parts = path.split('.')
  let current = obj
  for (const part of parts) {
    if (current === undefined || current === null || typeof current !== 'object') return undefined
    current = current[part]
  }
  return current
}

console.log(`\n🔍 Looking up key: "${key}"\n`)

let found = false
for (const [name, obj] of Object.entries(locales)) {
  const value = resolveKey(obj, key)
  if (value !== undefined) {
    found = true
    if (typeof value === 'object') {
      console.log(`${name}: [object with ${Object.keys(value).length} sub-keys]`)
    } else {
      console.log(`${name}: "${value}"`)
    }
  } else {
    console.log(`${name}: ❌ missing`)
  }
}

if (!found) {
  console.log('\nKey not found in any locale file.')
  process.exit(1)
}
