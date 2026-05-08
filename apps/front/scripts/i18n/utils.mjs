/**
 * Shared utilities for i18n validation scripts.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { resolve, dirname, extname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
export const FRONT_DIR = resolve(__dirname, '../..')
export const LOCALE_DIR = resolve(FRONT_DIR, 'src/i18n/locales')
export const SOURCE_DIR = resolve(FRONT_DIR, 'src')

/** Load all locale JSON files and return { 'en-US': obj, 'fr-FR': obj } */
export const loadLocales = () => {
  const locales = {}
  const files = readdirSync(LOCALE_DIR).filter((f) => f.endsWith('.json'))
  for (const file of files) {
    const name = file.replace('.json', '')
    locales[name] = JSON.parse(readFileSync(resolve(LOCALE_DIR, file), 'utf-8'))
  }
  return locales
}

/** Recursively flatten nested object to Set of dot-notation keys */
export const flattenKeys = (obj, prefix = '') => {
  const keys = new Set()
  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
      for (const k of flattenKeys(value, fullKey)) {
        keys.add(k)
      }
    } else {
      keys.add(fullKey)
    }
  }
  return keys
}

/** Recursively collect file paths matching given extensions */
export const scanSourceFiles = (dir, extensions = ['.vue', '.ts']) => {
  const results = []
  const walk = (d) => {
    for (const entry of readdirSync(d)) {
      const full = join(d, entry)
      if (entry === 'node_modules' || entry === 'dist' || entry === '.nuxt') continue
      const stat = statSync(full)
      if (stat.isDirectory()) {
        walk(full)
      } else if (extensions.includes(extname(full))) {
        results.push(full)
      }
    }
  }
  walk(dir)
  return results
}

/** Extract all i18n keys used in source files. Returns { usedKeys: Set, prefixes: Set } */
export const extractUsedKeys = (sourceFiles, allKnownKeys) => {
  const usedKeys = new Set()
  const prefixes = new Set()

  // Patterns: t('key'), $t('key'), tm('key') (message array/object), plus backtick and dynamic forms.
  const staticPattern = /\$?tm?\(\s*['"]([^'"]+)['"]/g
  const staticBacktickPattern = /\$?tm?\(\s*`([^`$]+)`/g
  const dynamicPattern = /\$?tm?\(\s*`([^`]*)\$\{/g

  // String literals that look like i18n keys (contain dots, used as props/values)
  // Matches: 'some.dotted.key' or "some.dotted.key" but NOT inside t() calls
  // Identifier segments allow underscores (snake_case keys are common in locales).
  const stringLiteralPattern = /['"]([a-zA-Z][a-zA-Z0-9_]*(?:\.[a-zA-Z][a-zA-Z0-9_]*){1,5})['"]/g

  for (const file of sourceFiles) {
    const content = readFileSync(file, 'utf-8')

    // Static keys (single/double quotes)
    let match
    while ((match = staticPattern.exec(content)) !== null) {
      usedKeys.add(match[1])
    }

    // Static keys (backticks without interpolation)
    while ((match = staticBacktickPattern.exec(content)) !== null) {
      usedKeys.add(match[1])
    }

    // Dynamic keys — extract static prefix
    while ((match = dynamicPattern.exec(content)) !== null) {
      const prefix = match[1]
      if (prefix) {
        prefixes.add(prefix)
      }
    }

    // String literals that match known i18n keys (props, object values, etc.)
    // Only check if we have a set of known keys to validate against
    if (allKnownKeys) {
      while ((match = stringLiteralPattern.exec(content)) !== null) {
        const candidate = match[1]
        if (allKnownKeys.has(candidate)) {
          usedKeys.add(candidate)
        }
      }
    }
  }

  return { usedKeys, prefixes }
}
