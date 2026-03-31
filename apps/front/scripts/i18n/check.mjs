#!/usr/bin/env node
/**
 * i18n:check — Verify key consistency between all locale files.
 * Exits with code 1 if any locale is missing keys present in others.
 */
import { loadLocales, flattenKeys } from './utils.mjs'

const locales = loadLocales()
const localeNames = Object.keys(locales)

if (localeNames.length < 2) {
  console.log('Only one locale found, nothing to compare.')
  process.exit(0)
}

// Flatten all keys per locale
const keysByLocale = {}
for (const name of localeNames) {
  keysByLocale[name] = flattenKeys(locales[name])
}

// Build union of all keys
const allKeys = new Set()
for (const keys of Object.values(keysByLocale)) {
  for (const k of keys) allKeys.add(k)
}

let hasErrors = false

for (const name of localeNames) {
  const missing = []
  for (const key of allKeys) {
    if (!keysByLocale[name].has(key)) {
      missing.push(key)
    }
  }
  if (missing.length > 0) {
    hasErrors = true
    console.error(`\n❌ ${name}.json is missing ${missing.length} key(s):`)
    for (const key of missing.sort()) {
      console.error(`   - ${key}`)
    }
  }
}

if (hasErrors) {
  console.error('\n⛔ i18n check failed: locale files are out of sync.')
  process.exit(1)
} else {
  console.log(`✅ All ${localeNames.length} locale files are in sync (${allKeys.size} keys).`)
}
