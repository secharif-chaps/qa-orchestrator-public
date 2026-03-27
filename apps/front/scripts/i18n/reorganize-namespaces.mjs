#!/usr/bin/env node
/**
 * One-time script to reorganize i18n namespaces according to ADR-0012.
 *
 * 1. Moves top-level keys into target namespaces in JSON files
 * 2. Updates all translation function references in source files
 *
 * Usage: node scripts/i18n/reorganize-namespaces.mjs [--dry-run]
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const FRONT_DIR = resolve(__dirname, '../..')
const LOCALE_DIR = resolve(FRONT_DIR, 'src/i18n/locales')
const SRC_DIR = resolve(FRONT_DIR, 'src')

const dryRun = process.argv.includes('--dry-run')

// ── Mapping: oldPrefix → newPrefix ──
// Keys already at target (common, dashboard, settings, admin) just get nested deeper
const MAPPING = {
  // → common.*
  pagination: 'common.pagination',
  errors: 'common.errors',
  appBar: 'common.appBar',
  appbar: 'common.appBar', // merge into appBar
  breadcrumb: 'common.breadcrumb',
  logo: 'common.logo',
  welcome: 'common.welcome',
  mentions: 'common.mentions',
  components: 'common.components',
  composables: 'common.composables',
  auth: 'common.auth',
  login: 'common.login',
  logout: 'common.logout',
  sidebar: 'common.sidebar',
  folder: 'common.folder',

  // → target.*
  watch_files: 'target.watchFiles',
  source_types: 'target.sourceTypes',
  documents: 'target.documents',
  document: 'target.document',
  financials: 'target.financials',
  communications: 'target.communications',

  // → screen.*
  company: 'screen.company',
  cards: 'screen.cards',
  csv: 'screen.csv',
  search: 'screen.search',
  jobs: 'screen.jobs',
  products: 'screen.products',
  profile: 'screen.profile',
  timeline: 'screen.timeline',
  team: 'screen.team',
  tasks: 'screen.tasks',
  chapseAssist: 'screen.chapseAssist',
  chapse: 'screen.chapse',
  dataSources: 'screen.dataSources',
  workflowStep: 'screen.workflowStep',

  // → dashboard.*
  home: 'dashboard.home',

  // → settings.*
  aiPreferences: 'settings.aiPreferences',
  featureFlags: 'settings.featureFlags',
  credits: 'settings.credits',
  tokens: 'settings.tokens',
  user: 'settings.user',
  help: 'settings.help',

  // → admin.*
  organization: 'admin.organization',
  organizations: 'admin.organizations',
}

// Keys that stay as-is (already correct root namespaces)
const KEEP_AS_IS = new Set(['common', 'target', 'screen', 'dashboard', 'settings', 'admin'])

// ── Step 1: Reorganize JSON files ──

const deepMerge = (target, source) => {
  for (const [key, value] of Object.entries(source)) {
    if (
      key in target &&
      typeof target[key] === 'object' &&
      typeof value === 'object' &&
      !Array.isArray(target[key]) &&
      !Array.isArray(value)
    ) {
      deepMerge(target[key], value)
    } else {
      target[key] = value
    }
  }
  return target
}

const setNested = (obj, path, value) => {
  const parts = path.split('.')
  let current = obj
  for (let i = 0; i < parts.length - 1; i++) {
    if (!(parts[i] in current) || typeof current[parts[i]] !== 'object') {
      current[parts[i]] = {}
    }
    current = current[parts[i]]
  }
  // Deep merge if both are objects
  const lastKey = parts[parts.length - 1]
  if (lastKey in current && typeof current[lastKey] === 'object' && typeof value === 'object') {
    deepMerge(current[lastKey], value)
  } else {
    current[lastKey] = value
  }
}

const sortKeys = (obj) => {
  if (obj === null || typeof obj !== 'object' || Array.isArray(obj)) return obj
  const sorted = {}
  for (const key of Object.keys(obj).sort((a, b) => a.localeCompare(b))) {
    sorted[key] = sortKeys(obj[key])
  }
  return sorted
}

const localeFiles = ['en-US.json', 'fr-FR.json']

for (const file of localeFiles) {
  const filePath = resolve(LOCALE_DIR, file)
  const data = JSON.parse(readFileSync(filePath, 'utf-8'))
  const reorganized = {}

  for (const [key, value] of Object.entries(data)) {
    if (KEEP_AS_IS.has(key)) {
      // Already a valid root namespace — keep as-is
      if (!(key in reorganized)) reorganized[key] = {}
      deepMerge(reorganized[key], value)
    } else if (key in MAPPING) {
      // Move to new location
      setNested(reorganized, MAPPING[key], value)
    } else {
      console.warn(`⚠️  ${file}: Unknown top-level key "${key}" — keeping as-is`)
      reorganized[key] = value
    }
  }

  const sorted = sortKeys(reorganized)
  const output = JSON.stringify(sorted, null, 2) + '\n'

  if (dryRun) {
    const rootKeys = Object.keys(sorted)
    console.log(`${file}: would reorganize to root keys: [${rootKeys.join(', ')}]`)
  } else {
    writeFileSync(filePath, output, 'utf-8')
    const rootKeys = Object.keys(sorted)
    console.log(`✅ ${file}: reorganized → [${rootKeys.join(', ')}]`)
  }
}

// ── Step 2: Build flat key rename map ──
// For each old prefix, we need to rename t('oldPrefix.xxx') → t('newPrefix.xxx')

const renameMap = {}
for (const [oldKey, newKey] of Object.entries(MAPPING)) {
  renameMap[oldKey] = newKey
}

// Sort by longest prefix first to avoid partial matches
const sortedPrefixes = Object.keys(renameMap).sort((a, b) => b.length - a.length)

// ── Step 3: Update source files ──

import { readdirSync, statSync } from 'node:fs'
import { extname, join } from 'node:path'

const scanFiles = (dir, extensions) => {
  const results = []
  const walk = (d) => {
    for (const entry of readdirSync(d)) {
      const full = join(d, entry)
      if (entry === 'node_modules' || entry === 'dist' || entry === '.nuxt') continue
      const stat = statSync(full)
      if (stat.isDirectory()) walk(full)
      else if (extensions.includes(extname(full))) results.push(full)
    }
  }
  walk(dir)
  return results
}

const sourceFiles = scanFiles(SRC_DIR, ['.vue', '.ts'])

// Matches translation function calls and replaces the key prefix inside them
const replaceKeysInContent = (content) => {
  let modified = content
  let count = 0

  for (const oldPrefix of sortedPrefixes) {
    const newPrefix = renameMap[oldPrefix]

    // Match t('oldPrefix.xxx') and $t('oldPrefix.xxx') with single/double quotes
    // Also match t('oldPrefix') alone (exact match, no dot after)
    const patterns = [
      // t('oldPrefix.something') or t('oldPrefix')
      new RegExp(`(\\$?t\\(\\s*')${escapeRegex(oldPrefix)}(\\.|\\'|")`, 'g'),
      new RegExp(`(\\$?t\\(\\s*")${escapeRegex(oldPrefix)}(\\.|"|')`, 'g'),
      // Template literals: t(`oldPrefix.something`)
      new RegExp(`(\\$?t\\(\\s*\`)${escapeRegex(oldPrefix)}(\\.|\`)`, 'g'),
      // In route meta YAML blocks or other string contexts
      new RegExp(`(key(?:path)?\\s*=\\s*")${escapeRegex(oldPrefix)}(\\.)`, 'g'),
    ]

    for (const pattern of patterns) {
      modified = modified.replace(pattern, (match, before, after) => {
        count++
        return `${before}${newPrefix}${after}`
      })
    }
  }

  return { content: modified, count }
}

const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

let totalReplacements = 0
let modifiedFiles = 0

for (const file of sourceFiles) {
  const original = readFileSync(file, 'utf-8')
  const { content: modified, count } = replaceKeysInContent(original)

  if (count > 0) {
    totalReplacements += count
    modifiedFiles++
    const relPath = file.replace(SRC_DIR + '/', '')

    if (dryRun) {
      console.log(`  ${relPath}: ${count} key reference(s) would be updated`)
    } else {
      writeFileSync(file, modified, 'utf-8')
      console.log(`  ${relPath}: ${count} key reference(s) updated`)
    }
  }
}

const action = dryRun ? 'would be updated' : 'updated'
console.log(`\n✅ ${totalReplacements} key reference(s) ${action} across ${modifiedFiles} file(s).`)

if (dryRun) {
  console.log('\nRun without --dry-run to apply changes.')
}
