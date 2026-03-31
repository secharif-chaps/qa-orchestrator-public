#!/usr/bin/env node
/**
 * i18n:format — Sort locale JSON keys alphabetically and normalize formatting.
 * Can be used as a lint-staged hook or standalone.
 */
import { readFileSync, writeFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { LOCALE_DIR } from './utils.mjs'

/** Recursively sort object keys alphabetically */
const sortKeys = (obj) => {
  if (obj === null || typeof obj !== 'object' || Array.isArray(obj)) return obj
  const sorted = {}
  for (const key of Object.keys(obj).sort((a, b) => a.localeCompare(b))) {
    sorted[key] = sortKeys(obj[key])
  }
  return sorted
}

const files = readdirSync(LOCALE_DIR).filter((f) => f.endsWith('.json'))
let modified = 0

for (const file of files) {
  const filePath = resolve(LOCALE_DIR, file)
  const content = readFileSync(filePath, 'utf-8')
  const parsed = JSON.parse(content)
  const sorted = sortKeys(parsed)
  const formatted = JSON.stringify(sorted, null, 2) + '\n'

  if (content !== formatted) {
    writeFileSync(filePath, formatted, 'utf-8')
    console.log(`📝 Sorted and formatted: ${file}`)
    modified++
  }
}

if (modified === 0) {
  console.log(`✅ All ${files.length} locale file(s) already sorted.`)
} else {
  console.log(`\n✅ Formatted ${modified} file(s).`)
}
