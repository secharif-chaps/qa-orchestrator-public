#!/usr/bin/env node
/**
 * i18n:duplicates — Detect duplicate keys at the same nesting level in JSON locale files.
 * Uses a character-by-character state machine to track object scopes accurately.
 * Exits with code 1 if duplicates are found.
 */
import { readFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { LOCALE_DIR } from './utils.mjs'

/**
 * Detect duplicate keys by scanning JSON text character by character,
 * tracking object scope depth and key occurrences at each level.
 */
const findDuplicates = (text) => {
  const duplicates = []
  let line = 1
  let i = 0

  const scopeStack = []
  const pathStack = []

  let inString = false
  let escaped = false
  let currentKey = null
  let collectingKey = false
  let keyBuffer = ''
  let keyStartLine = 0
  let expectColon = false

  while (i < text.length) {
    const ch = text[i]
    if (ch === '\n') line++

    if (inString) {
      if (escaped) {
        if (collectingKey) keyBuffer += ch
        escaped = false
      } else if (ch === '\\') {
        escaped = true
        if (collectingKey) keyBuffer += ch
      } else if (ch === '"') {
        inString = false
        if (collectingKey) {
          currentKey = keyBuffer
          collectingKey = false
          expectColon = true
        }
      } else {
        if (collectingKey) keyBuffer += ch
      }
    } else {
      if (ch === '"') {
        inString = true
        if (expectColon) {
          expectColon = false
          currentKey = null
        } else if (scopeStack.length > 0) {
          collectingKey = true
          keyBuffer = ''
          keyStartLine = line
        }
      } else if (ch === ':') {
        if (expectColon && currentKey !== null) {
          expectColon = false
          const scope = scopeStack[scopeStack.length - 1]
          if (scope.has(currentKey)) {
            const fullPath = [...pathStack, currentKey].join('.')
            duplicates.push({
              key: fullPath,
              line: keyStartLine,
              firstLine: scope.get(currentKey),
            })
          } else {
            scope.set(currentKey, keyStartLine)
          }
        }
      } else if (ch === '{') {
        if (currentKey !== null && !expectColon) {
          pathStack.push(currentKey)
        }
        scopeStack.push(new Map())
        currentKey = null
        expectColon = false
      } else if (ch === '}') {
        scopeStack.pop()
        if (pathStack.length > scopeStack.length) {
          pathStack.pop()
        }
        currentKey = null
        expectColon = false
      } else if (ch === ',') {
        if (expectColon) {
          currentKey = null
          expectColon = false
        }
      }
    }
    i++
  }

  return duplicates
}

const files = readdirSync(LOCALE_DIR).filter((f) => f.endsWith('.json'))
let hasErrors = false

for (const file of files) {
  const content = readFileSync(resolve(LOCALE_DIR, file), 'utf-8')
  const duplicates = findDuplicates(content)

  if (duplicates.length > 0) {
    hasErrors = true
    console.error(`\n❌ ${file} has ${duplicates.length} duplicate key(s):`)
    for (const d of duplicates) {
      console.error(`   - "${d.key}" at line ${d.line} (first seen at line ${d.firstLine})`)
    }
  }
}

if (hasErrors) {
  console.error('\n⛔ Duplicate keys found in locale files.')
  process.exit(1)
} else {
  console.log(`✅ No duplicate keys found in ${files.length} locale file(s).`)
}
