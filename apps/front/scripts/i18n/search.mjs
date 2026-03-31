#!/usr/bin/env node
/**
 * i18n:search — Search for a value (translated text) across all locale files.
 * Usage: node scripts/i18n/search.mjs <text>
 */
import { loadLocales, flattenKeys } from './utils.mjs'

const query = process.argv.slice(2).join(' ')
if (!query) {
  console.error('Usage: node scripts/i18n/search.mjs <text>')
  console.error('Example: node scripts/i18n/search.mjs "Delete company"')
  process.exit(1)
}

const locales = loadLocales()
const queryLower = query.toLowerCase()

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

console.log(`\n🔍 Searching for: "${query}"\n`)

let totalMatches = 0

for (const [name, obj] of Object.entries(locales)) {
  const keys = flattenKeys(obj)
  const matches = []

  for (const key of keys) {
    const value = resolveKey(obj, key)
    if (typeof value === 'string' && value.toLowerCase().includes(queryLower)) {
      matches.push({ key, value })
    }
  }

  if (matches.length > 0) {
    console.log(`📦 ${name} (${matches.length} match${matches.length > 1 ? 'es' : ''}):`)
    for (const { key, value } of matches) {
      console.log(`   ${key} = "${value}"`)
    }
    console.log()
    totalMatches += matches.length
  }
}

if (totalMatches === 0) {
  console.log('No matches found.')
  process.exit(1)
}

console.log(`Total: ${totalMatches} match(es) found.`)
