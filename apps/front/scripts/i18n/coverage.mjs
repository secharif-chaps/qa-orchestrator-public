#!/usr/bin/env node
/**
 * i18n:coverage — Display translation coverage statistics per locale.
 * Informational only, always exits 0.
 */
import { loadLocales, flattenKeys } from './utils.mjs'

const locales = loadLocales()

// Use union of all keys as reference
const allKeys = new Set()
for (const obj of Object.values(locales)) {
  for (const k of flattenKeys(obj)) allKeys.add(k)
}

console.log(`\n📊 i18n Coverage Report`)
console.log(`${'─'.repeat(50)}`)
console.log(`Total unique keys: ${allKeys.size}\n`)

for (const [name, obj] of Object.entries(locales)) {
  const keys = flattenKeys(obj)
  const missing = allKeys.size - keys.size
  const pct = ((keys.size / allKeys.size) * 100).toFixed(1)
  const bar = '█'.repeat(Math.round(pct / 2)) + '░'.repeat(50 - Math.round(pct / 2))
  console.log(`${name}: ${bar} ${pct}% (${keys.size}/${allKeys.size})`)
  if (missing > 0) {
    console.log(`         ${missing} key(s) missing`)
  }
}

console.log()
