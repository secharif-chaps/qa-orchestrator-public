#!/usr/bin/env node
/**
 * i18n:remove-fallbacks — Remove inline fallback strings from t() calls.
 *
 * Transforms: translation calls with inline fallback strings into calls without fallback.
 * Does NOT touch interpolation calls with object parameters.
 *
 * Usage: node scripts/i18n/remove-fallbacks.mjs [--dry-run]
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { scanSourceFiles, SOURCE_DIR } from './utils.mjs'

const dryRun = process.argv.includes('--dry-run')

// Match t('key', 'fallback') or $t('key', 'fallback') or t("key", "fallback")
// Uses (?<!\w) lookbehind to avoid matching emit(), set(), etc.
// Group 1: $ or empty (prefix)
// Group 2: quote char for key
// Group 3: key
// Group 4: quote char for fallback
// Group 5: fallback text
// Does NOT match when second arg starts with { (object/interpolation)
// Single-line pattern
const fallbackPattern = /(?<!\w)(\$?)t\(\s*(['"])([^'"]+)\2\s*,\s*(['"])([^'"]*)\4\s*\)/g
// Multi-line pattern: handles t(\n  'key',\n  'fallback',?\n)
const fallbackPatternMultiline =
  /(?<!\w)(\$?)t\(\s*\n\s*(['"])([^'"]+)\2\s*,\s*\n\s*(['"])([^'"]*)\4\s*,?\s*\n\s*\)/g

const sourceFiles = scanSourceFiles(SOURCE_DIR, ['.vue', '.ts'])

let totalReplacements = 0
let modifiedFiles = 0

for (const file of sourceFiles) {
  const original = readFileSync(file, 'utf-8')
  let modified = original
  let fileReplacements = 0

  modified = modified.replace(fallbackPattern, (_match, prefix, q1, key) => {
    fileReplacements++
    return `${prefix}t(${q1}${key}${q1})`
  })

  modified = modified.replace(fallbackPatternMultiline, (_match, prefix, q1, key) => {
    fileReplacements++
    return `${prefix}t(${q1}${key}${q1})`
  })

  if (fileReplacements > 0) {
    totalReplacements += fileReplacements
    modifiedFiles++
    const relPath = file.replace(SOURCE_DIR + '/', '')

    if (dryRun) {
      console.log(`  ${relPath}: ${fileReplacements} fallback(s) would be removed`)
    } else {
      writeFileSync(file, modified, 'utf-8')
      console.log(`  ${relPath}: ${fileReplacements} fallback(s) removed`)
    }
  }
}

const action = dryRun ? 'would be removed' : 'removed'
console.log(`\n✅ ${totalReplacements} fallback(s) ${action} across ${modifiedFiles} file(s).`)

if (dryRun) {
  console.log('\nRun without --dry-run to apply changes.')
}
