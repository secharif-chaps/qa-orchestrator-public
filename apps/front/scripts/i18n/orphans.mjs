#!/usr/bin/env node
/**
 * i18n:orphans — Detect translation keys that exist in JSON but are never used in code.
 * Supports an ignore file for dynamically-constructed keys.
 *
 * Usage:
 *   node scripts/i18n/orphans.mjs          — Detect and report (CI mode, exits 1 if found)
 *   node scripts/i18n/orphans.mjs --fix    — Detect and remove orphan keys from all locale files
 */
import { readFileSync, writeFileSync, readdirSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  loadLocales,
  flattenKeys,
  scanSourceFiles,
  extractUsedKeys,
  SOURCE_DIR,
  LOCALE_DIR,
} from './utils.mjs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixMode = process.argv.includes('--fix')

// Load ignore patterns and sort them
const ignoreFile = resolve(__dirname, 'orphan-ignore.json')
let ignorePatterns = []
try {
  ignorePatterns = JSON.parse(readFileSync(ignoreFile, 'utf-8'))
  const sorted = [...ignorePatterns].sort()
  if (JSON.stringify(sorted) !== JSON.stringify(ignorePatterns)) {
    ignorePatterns = sorted
    writeFileSync(ignoreFile, JSON.stringify(sorted, null, 2) + '\n', 'utf-8')
  }
} catch {
  // No ignore file — that's fine
}

const locales = loadLocales()
const referenceLocale = Object.keys(locales)[0]
const allKeys = flattenKeys(locales[referenceLocale])

// Scan source files for used keys (pass allKeys to detect string literal references)
const sourceFiles = scanSourceFiles(SOURCE_DIR)
const { usedKeys, prefixes } = extractUsedKeys(sourceFiles, allKeys)

// Determine orphans
const orphans = []
for (const key of allKeys) {
  if (usedKeys.has(key)) continue

  // Check if key matches a dynamic prefix
  let matchesPrefix = false
  for (const prefix of prefixes) {
    if (key.startsWith(prefix)) {
      matchesPrefix = true
      break
    }
  }
  if (matchesPrefix) continue

  // Check ignore patterns (glob-like with *)
  let ignored = false
  for (const pattern of ignorePatterns) {
    if (pattern.endsWith('*')) {
      if (key.startsWith(pattern.slice(0, -1))) {
        ignored = true
        break
      }
    } else if (key === pattern) {
      ignored = true
      break
    }
  }
  if (ignored) continue

  orphans.push(key)
}

if (orphans.length === 0) {
  console.log(
    `✅ No orphan keys found (checked ${allKeys.size} keys against ${sourceFiles.length} source files).`,
  )
  process.exit(0)
}

if (!fixMode) {
  // Report mode (CI)
  console.error(`\n🔍 Found ${orphans.length} orphan key(s) in ${referenceLocale}:`)
  for (const key of orphans.sort()) {
    console.error(`   - ${key}`)
  }
  console.error('\nRun with --fix to remove them, or add to scripts/i18n/orphan-ignore.json')
  process.exit(1)
}

// Fix mode: remove orphan keys from all locale files
const orphanSet = new Set(orphans)

// Collect all parent paths of used keys so we never remove a used parent object
// e.g. if t('screen.jobs.loading') is used, 'screen.jobs.loading' is a "used parent"
// and we must not remove it even if all its children are orphans
const usedParentPaths = new Set()
for (const key of usedKeys) {
  usedParentPaths.add(key)
}
for (const prefix of prefixes) {
  usedParentPaths.add(prefix)
}

/** Recursively remove orphan keys from a nested object. */
const removeKeys = (obj, prefix = '') => {
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return obj

  const cleaned = {}
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key

    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      const child = removeKeys(value, fullKey)
      // Keep non-empty objects, AND keep empty objects if the path is used in code
      if (Object.keys(child).length > 0 || usedParentPaths.has(fullKey)) {
        cleaned[key] = child
      }
    } else {
      if (!orphanSet.has(fullKey)) {
        cleaned[key] = value
      }
    }
  }
  return cleaned
}

const files = readdirSync(LOCALE_DIR).filter((f) => f.endsWith('.json'))
for (const file of files) {
  const filePath = resolve(LOCALE_DIR, file)
  const data = JSON.parse(readFileSync(filePath, 'utf-8'))
  const cleaned = removeKeys(data)
  writeFileSync(filePath, JSON.stringify(cleaned, null, 2) + '\n', 'utf-8')
}

console.log(`🗑️  Removed ${orphans.length} orphan key(s) from ${files.length} locale file(s).`)

// Show summary by top-level prefix
const byPrefix = new Map()
for (const key of orphans.sort()) {
  const prefix = key.split('.').slice(0, 2).join('.')
  if (!byPrefix.has(prefix)) byPrefix.set(prefix, 0)
  byPrefix.set(prefix, byPrefix.get(prefix) + 1)
}
console.log('\nSummary by prefix:')
for (const [prefix, count] of [...byPrefix.entries()].sort((a, b) => b[1] - a[1])) {
  console.log(`   ${prefix}: ${count} key(s)`)
}
