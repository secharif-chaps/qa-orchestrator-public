#!/usr/bin/env node

/**
 * N8N Workflow Test Script
 *
 * Tests N8N workflows via webhook triggers and evaluates AI agent responses
 *
 * Usage:
 *   node test-workflow.js <dataset-file> [options]
 *
 * Options:
 *   --workflow-id <id>      Workflow ID (fetches webhook URL automatically)
 *   --webhook-url <url>     Webhook URL (overrides workflow-id)
 *   --n8n-base-url <url>    N8N base URL (default: http://localhost:5678)
 *   --timeout <ms>          Request timeout in milliseconds (default: 60000)
 *   --parallel              Run tests in parallel (default: sequential)
 *   --verbose               Enable verbose logging
 *   --update-snapshots, -u  Update snapshots for failed tests
 *   --test-case <id>        Run only specific test case by ID
 *   --case <id>             Alias for --test-case
 *   --output <file>         Output report file (default: auto-generated from dataset name + timestamp)
 */

const fs = require('fs')
const path = require('path')
const axios = require('axios')
const Ajv = require('ajv')
const chalk = require('chalk')

// Configuration
const DEFAULT_N8N_BASE_URL = process.env.N8N_BASE_URL || 'http://localhost:5678'
const DEFAULT_TIMEOUT = 60000 * 5 // 5 minutes
const DEFAULT_OUTPUT = '/tests/reports/test-report.json'
const N8N_API_KEY = process.env.N8N_API_KEY || ''

// Parse command line arguments
const args = process.argv.slice(2)
const config = {
  datasetFile: args[0],
  workflowId: getArgValue('--workflow-id'),
  webhookUrl: getArgValue('--webhook-url'),
  n8nBaseUrl: getArgValue('--n8n-base-url') || DEFAULT_N8N_BASE_URL,
  n8nApiKey: getArgValue('--n8n-api-key') || N8N_API_KEY,
  timeout: parseInt(getArgValue('--timeout')) || DEFAULT_TIMEOUT,
  parallel: args.includes('--parallel'),
  verbose: args.includes('--verbose'),
  updateSnapshots: args.includes('--update-snapshots') || args.includes('-u'),
  outputFile: getArgValue('--output') || DEFAULT_OUTPUT,
  testCase: getArgValue('--test-case') || getArgValue('--case'),
}

function getArgValue(flag) {
  const index = args.indexOf(flag)
  return index !== -1 && args[index + 1] ? args[index + 1] : null
}

// Validate arguments
if (!config.datasetFile) {
  console.error(chalk.red('Error: Dataset file path required'))
  console.log('\nUsage: node test-workflow.js <dataset-file> [options]')
  console.log('\nOptions:')
  console.log('  --workflow-id <id>      Workflow ID (auto-fetch webhook URL)')
  console.log('  --webhook-url <url>     Webhook URL (overrides workflow-id)')
  console.log('  --n8n-base-url <url>    N8N base URL')
  console.log('  --n8n-api-key <key>     N8N API key (or use N8N_API_KEY env var)')
  console.log('  --timeout <ms>          Request timeout')
  console.log('  --parallel              Run tests in parallel')
  console.log('  --verbose               Enable verbose logging')
  console.log('  --update-snapshots, -u  Update snapshots for failed tests')
  console.log('  --test-case <id>        Run only specific test case by ID')
  console.log('  --case <id>             Alias for --test-case')
  console.log('  --output <file>         Custom output report file (default: auto-generated)')
  process.exit(1)
}

/**
 * Remove fields from object based on path pattern
 * Supports wildcards: *.context.execution, *._testMetadata
 */
function removeFieldsByPattern(obj, pattern) {
  if (!obj || typeof obj !== 'object') {
    return
  }

  const parts = pattern.split('.')

  function removeRecursive(current, pathParts, depth = 0) {
    if (depth >= pathParts.length) {
      return
    }

    const part = pathParts[depth]
    const isLast = depth === pathParts.length - 1

    if (part === '*') {
      // Wildcard: apply to all keys
      for (const key in current) {
        if (current.hasOwnProperty(key)) {
          if (isLast) {
            delete current[key]
          } else if (current[key] && typeof current[key] === 'object') {
            removeRecursive(current[key], pathParts, depth + 1)
          }
        }
      }
    } else {
      // Specific key
      if (isLast) {
        delete current[part]
      } else if (current[part] && typeof current[part] === 'object') {
        removeRecursive(current[part], pathParts, depth + 1)
      }
    }
  }

  removeRecursive(obj, parts)
}

/**
 * Deep clone object using the most efficient method available
 * Prefers structuredClone (Node 17+) over JSON.parse/stringify
 */
function deepClone(obj) {
  if (!obj || typeof obj !== 'object') {
    return obj
  }

  // Use structuredClone if available (Node 17+)
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(obj)
    } catch (e) {
      // Fallback to JSON if structuredClone fails (e.g., functions, symbols)
    }
  }

  // Fallback to JSON parse/stringify
  // Note: This loses functions, symbols, undefined values, and Date becomes string
  return JSON.parse(JSON.stringify(obj))
}

/**
 * Normalize response data for snapshot comparison
 * Removes volatile fields that change between executions
 */
function normalizeResponseForSnapshot(data, config = null) {
  if (!data || typeof data !== 'object') {
    return data
  }

  // Clone to avoid mutating original
  const normalized = deepClone(data)

  // Default fields to remove (backward compatibility)
  const defaultRemoveFields = [
    '*.context.execution',
    '*.context.timestamp',
    '*.context.messageId',
    '*.context.conversationId',
    '_testMetadata',
  ]

  // Get removal patterns from config or use defaults
  const removeFields = config?.snapshotNormalization?.removeFields || defaultRemoveFields

  // Handle arrays at the top level
  if (Array.isArray(normalized)) {
    // Apply patterns to each element in the array
    normalized.forEach((item) => {
      if (item && typeof item === 'object') {
        removeFields.forEach((pattern) => {
          removeFieldsByPattern(item, pattern)
        })
      }
    })
  } else {
    // Apply each removal pattern to object
    removeFields.forEach((pattern) => {
      removeFieldsByPattern(normalized, pattern)
    })
  }

  return normalized
}

/**
 * Deep compare two objects and return differences
 */
function getSnapshotDiff(snapshot, actual) {
  const diffs = []

  function compareObjects(obj1, obj2, path = '') {
    if (obj1 === obj2) return

    if (typeof obj1 !== typeof obj2) {
      diffs.push({
        path,
        expected: obj1,
        received: obj2,
        type: 'type-mismatch',
      })
      return
    }

    if (obj1 === null || obj2 === null) {
      if (obj1 !== obj2) {
        diffs.push({ path, expected: obj1, received: obj2, type: 'value' })
      }
      return
    }

    if (Array.isArray(obj1) && Array.isArray(obj2)) {
      if (obj1.length !== obj2.length) {
        diffs.push({
          path: `${path}.length`,
          expected: obj1.length,
          received: obj2.length,
          type: 'array-length',
        })
      }
      const maxLen = Math.max(obj1.length, obj2.length)
      for (let i = 0; i < maxLen; i++) {
        compareObjects(obj1[i], obj2[i], `${path}[${i}]`)
      }
      return
    }

    if (typeof obj1 === 'object' && typeof obj2 === 'object') {
      const keys1 = Object.keys(obj1)
      const keys2 = Object.keys(obj2)
      const allKeys = new Set([...keys1, ...keys2])

      for (const key of allKeys) {
        if (!(key in obj1)) {
          diffs.push({
            path: path ? `${path}.${key}` : key,
            expected: undefined,
            received: obj2[key],
            type: 'missing-in-snapshot',
          })
        } else if (!(key in obj2)) {
          diffs.push({
            path: path ? `${path}.${key}` : key,
            expected: obj1[key],
            received: undefined,
            type: 'missing-in-actual',
          })
        } else {
          compareObjects(obj1[key], obj2[key], path ? `${path}.${key}` : key)
        }
      }
      return
    }

    if (obj1 !== obj2) {
      diffs.push({ path, expected: obj1, received: obj2, type: 'value' })
    }
  }

  compareObjects(snapshot, actual)
  return diffs
}

/**
 * Extract execution ID from response data
 * Searches recursively for execution.id field with safeguards
 */
function extractExecutionId(data, visited = new WeakSet(), depth = 0) {
  // Safety checks
  if (!data || typeof data !== 'object') {
    return null
  }

  // Prevent infinite loops with circular references
  if (visited.has(data)) {
    return null
  }
  visited.add(data)

  // Limit recursion depth to prevent stack overflow
  const MAX_DEPTH = 10
  if (depth > MAX_DEPTH) {
    return null
  }

  // Direct path check: data.context.execution.id
  if (data.context?.execution?.id) {
    return data.context.execution.id
  }

  // Recursive search through object properties
  for (const key in data) {
    if (data.hasOwnProperty(key)) {
      const value = data[key]

      // Check if this property has context.execution.id
      if (value && typeof value === 'object') {
        if (value.context?.execution?.id) {
          return value.context.execution.id
        }

        // Skip if already visited
        if (!visited.has(value)) {
          // Recursively search nested objects
          const nested = extractExecutionId(value, visited, depth + 1)
          if (nested) {
            return nested
          }
        }
      }
    }
  }

  return null
}

/**
 * Generate N8N execution URL for UI access
 */
function generateExecutionUrl(workflowId, executionId) {
  // Use N8N_HOST env var for public URL (e.g., https://n8n.basil.local)
  const n8nHost = process.env.N8N_HOST || 'https://n8n.basil.local'
  return `${n8nHost}/workflow/${workflowId}/executions/${executionId}`
}

/**
 * Fetch webhook URL from N8N workflow
 */
async function getWorkflowWebhookUrl(workflowId, n8nBaseUrl, n8nApiKey) {
  try {
    // Prepare request headers
    const headers = {
      Accept: 'application/json',
      'X-N8N-API-KEY': n8nApiKey || undefined,
    }

    // Get workflow details
    const response = await axios.get(
      `${n8nBaseUrl}/api/v1/workflows/${workflowId}?excludePinnedData=true`,
      {
        timeout: 10000,
        headers,
      },
    )

    const workflow = response.data

    // Find webhook node
    const webhookNode = workflow.nodes.find((node) => node.type === 'n8n-nodes-base.webhook')

    if (!webhookNode) {
      throw new Error('No webhook trigger found in workflow')
    }

    // Extract webhook path
    const webhookPath = webhookNode.parameters.path
    if (!webhookPath) {
      throw new Error('Webhook node has no path configured')
    }

    // workflow state
    let webhookPrefix = 'webhook'
    if (workflow.active !== true) {
      if (config.verbose) {
        console.log(chalk.yellow('Warning: Workflow is not active. Using test webhook URL.'))
      }

      webhookPrefix = 'webhook-test'
    }

    // Build webhook URL
    const webhookUrl = `${n8nBaseUrl}/${webhookPrefix}/${webhookPath}`

    return webhookUrl
  } catch (error) {
    if (error.response?.status === 404) {
      throw new Error(`Workflow ${workflowId} not found`)
    }
    throw new Error(`Failed to fetch workflow: ${error.message}`)
  }
}

// Initialize JSON schema validator (for optional custom schemas in test cases)
const ajv = new Ajv({ allErrors: true })

/**
 * Helper function to get nested property value using dot-notation path
 * Example: getNestedValue(obj, "actor.success") returns obj.actor.success
 * @param {Object} obj - The object to traverse
 * @param {string} path - Dot-notation path (e.g., "actor.success", "data.result.status")
 * @returns {*} The value at the specified path, or undefined if not found
 */
function getNestedValue(obj, path) {
  if (!obj || typeof obj !== 'object' || !path) {
    return undefined
  }

  const parts = path.split('.')
  let current = obj

  for (const part of parts) {
    if (current && typeof current === 'object' && part in current) {
      current = current[part]
    } else {
      return undefined
    }
  }

  return current
}

// Metrics calculator
class MetricsCalculator {
  /**
   * Calculate generic response quality metrics
   *
   * @param {Object} testCase - Test case configuration with optional validation options:
   *   - responseSchema: JSON schema for structure validation
   *   - successCheck: {
   *       enabled: boolean (default: true) - enable/disable success state check
   *       expectedPathValue: Array - array of [path, expectedValue] pairs to check success field
   *     }
   * @param {Object} response - Response data to validate
   * @param {number} responseTime - Response time in milliseconds
   * @returns {Object} Metrics object with validation results
   */
  static calculateQualityMetrics(testCase, response, responseTime) {
    const metrics = {
      responseTime,
      structureCompliance: false,
      hasContent: false,
      contentLength: 0,
      isError: false,
      customMetrics: {},
    }

    // Basic validation: response should be an object or have content
    if (!response) {
      return metrics
    }

    // Generic validation: response is valid JSON object with content
    metrics.contentLength = JSON.stringify(response).length
    metrics.hasContent = metrics.contentLength > 10 // arbitrary threshold for "has content"
    metrics.structureCompliance = typeof response === 'object' && Object.keys(response).length > 0

    // Check if test case provides custom JSON schema
    const responseSchema = testCase?.responseSchema || null
    if (responseSchema) {
      try {
        const validate = ajv.compile(responseSchema)
        metrics.structureCompliance = validate(response)

        // Store validation errors if any
        if (!metrics.structureCompliance && validate.errors) {
          metrics.customMetrics.schemaValidationErrors = validate.errors
        }
      } catch (error) {
        console.warn(`Schema validation error: ${error.message}`)
        metrics.structureCompliance = false
        metrics.customMetrics.schemaValidationErrors = [{ message: error.message }]
      }
    }

    // Get success check configuration from test case (with defaults)
    const successCheckConfig = testCase.successCheck || {}
    const isSuccessCheckEnabled = successCheckConfig?.enabled !== false // default: true
    const expectedSuccessPathValue = successCheckConfig?.expectedPathValue || [['success', true]]

    // Handle both object and array responses
    const checkResponse = Array.isArray(response) ? response[0] : response

    const hasCommonErrorIndicators =
      checkResponse?.error || checkResponse?.errorMessage || checkResponse?.status === 'error'

    if (hasCommonErrorIndicators) {
      metrics.isError = true

      return metrics
    }

    if (isSuccessCheckEnabled && checkResponse) {
      for (const [successPath, expectedSuccessValue] of expectedSuccessPathValue) {
        const successValue = getNestedValue(checkResponse, successPath)

        if (successValue !== undefined) {
          metrics.isError = successValue !== expectedSuccessValue
        }

        if (metrics.isError) {
          break
        }
      }
    }

    return metrics
  }

  /**
   * Calculate overall test suite statistics
   */
  static calculateSuiteStats(results) {
    // Handle empty results to avoid division by zero
    if (!results || results.length === 0) {
      return {
        total: 0,
        passed: 0,
        failed: 0,
        successRate: 0,
        avgResponseTime: 0,
        minResponseTime: 0,
        maxResponseTime: 0,
        structureComplianceRate: 0,
      }
    }

    const total = results.length
    const passed = results.filter((r) => r.passed).length
    const failed = total - passed
    const successRate = (passed / total) * 100

    const responseTimes = results.map((r) => r.metrics.responseTime)
    const avgResponseTime = responseTimes.reduce((a, b) => a + b, 0) / total
    const minResponseTime = Math.min(...responseTimes)
    const maxResponseTime = Math.max(...responseTimes)

    const structureCompliance = results.filter((r) => r.metrics.structureCompliance).length
    const structureComplianceRate = (structureCompliance / total) * 100

    return {
      total,
      passed,
      failed,
      successRate: parseFloat(successRate.toFixed(2)),
      avgResponseTime: parseFloat(avgResponseTime.toFixed(2)),
      minResponseTime,
      maxResponseTime,
      structureComplianceRate: parseFloat(structureComplianceRate.toFixed(2)),
    }
  }
}

// Test runner
class WorkflowTestRunner {
  constructor(config) {
    this.config = config
    this.results = []
    this.datasetMetadata = null // Will be loaded from dataset
    // Generate unique run ID for this test execution
    this.runId = `test-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`
  }

  /**
   * Find dataset file path
   * @returns {string}
   */
  findDatasetPath() {
    let datasetPath = this.config.datasetFile

    // If path is not absolute, try to resolve from /tests/datasets/ directory
    if (!path.isAbsolute(datasetPath)) {
      const datasetsDir = path.join(__dirname, 'datasets')
      const potentialPath = path.join(datasetsDir, datasetPath)

      // Check if file exists in datasets directory
      if (fs.existsSync(potentialPath)) {
        return potentialPath
      } else {
        // Fall back to resolving from current directory
        return path.resolve(datasetPath)
      }
    }

    return datasetPath
  }

  /**
   * Read and JSON-parse dataset file
   * @returns {Object}
   */
  readDataset() {
    try {
      const datasetPath = this.findDatasetPath()
      const datasetContent = fs.readFileSync(datasetPath, 'utf8')

      return JSON.parse(datasetContent)
    } catch (error) {
      throw new Error(`Failed to load dataset: ${error.message}`, {
        cause: error,
      })
    }
  }

  /**
   * Load dataset from file
   */
  loadDataset() {
    try {
      const dataset = this.readDataset()

      // Store dataset metadata for later use
      this.datasetMetadata = dataset.metadata || {}

      // Auto-extract workflow ID from dataset metadata if not provided
      if (!this.config.workflowId && !this.config.webhookUrl && dataset.metadata?.workflowId) {
        this.config.workflowId = dataset.metadata.workflowId
        if (this.config.verbose) {
          console.log(
            chalk.gray(`Auto-detected workflow ID from dataset: ${this.config.workflowId}`),
          )
        }
      }

      // Log snapshot normalization config if present
      if (this.config.verbose && dataset.metadata?.snapshotNormalization) {
        console.log(chalk.gray(`Using custom snapshot normalization config`))
        console.log(
          chalk.gray(
            `  Fields to remove: ${dataset.metadata.snapshotNormalization.removeFields.join(', ')}`,
          ),
        )
      }

      // Filter to specific test case if requested
      if (this.config.testCase) {
        const originalTestCases = dataset.testCases
        const originalCount = originalTestCases.length
        dataset.testCases = originalTestCases.filter((tc) => tc.testCaseId === this.config.testCase)

        if (dataset.testCases.length === 0) {
          const availableIds = originalTestCases.map((tc) => tc.testCaseId).join(', ') || 'none'
          throw new Error(
            `Test case "${this.config.testCase}" not found in dataset. Available test cases: ${availableIds}`,
          )
        }

        if (this.config.verbose) {
          console.log(
            chalk.gray(
              `Filtered to test case: ${this.config.testCase} (${originalCount} total available)`,
            ),
          )
        }
      }

      return dataset
    } catch (error) {
      throw new Error(`Failed to load dataset: ${error.message}`, {
        cause: error,
      })
    }
  }

  /**
   * Execute a single test case
   */
  async executeTestCase(testCase) {
    const startTime = Date.now()

    if (this.config.verbose) {
      console.log(chalk.blue(`\n▶ Running: ${testCase.testCaseId} - ${testCase.name}`))
      console.log(chalk.gray(`  Expected: ${testCase.expectedOutput}`))
    }

    try {
      // Prepare payload with test metadata for N8N execution tracking
      const payload = {
        ...testCase.input,
        _testMetadata: {
          runId: this.runId,
          testCaseId: testCase.testCaseId,
          testCaseName: testCase.name,
          timestamp: new Date().toISOString(),
          source: 'n8n-test-runner',
          datasetFile: path.basename(this.config.datasetFile),
        },
      }

      // Send request to webhook
      const response = await axios.post(this.config.webhookUrl, payload, {
        timeout: this.config.timeout,
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
      })

      const responseTime = Date.now() - startTime
      const responseData = response.data

      // Extract N8N execution ID from response (generic extraction)
      const executionId = extractExecutionId(responseData)

      // Warning if execution ID is missing (convention requirement)
      if (!executionId) {
        console.log(
          chalk.yellow(
            `    ⚠ WARNING: Execution ID not found in response (should be in context.execution.id)`,
          ),
        )
        console.log(chalk.yellow(`       This is required for execution tracking`))
      }

      // Generate execution URL for easy access
      let executionUrl = null
      if (executionId && this.config.workflowId) {
        executionUrl = generateExecutionUrl(this.config.workflowId, executionId)
      }

      // Calculate metrics
      const metrics = MetricsCalculator.calculateQualityMetrics(
        testCase,
        responseData,
        responseTime,
      )

      // Snapshot comparison (if snapshot exists)
      let snapshotDiff = null
      let snapshotPassed = true
      const normalizedResponse = normalizeResponseForSnapshot(responseData, this.datasetMetadata)

      if (testCase.snapshot) {
        // Normalize the saved snapshot too for fair comparison
        const normalizedSnapshot = normalizeResponseForSnapshot(
          testCase.snapshot,
          this.datasetMetadata,
        )
        snapshotDiff = getSnapshotDiff(normalizedSnapshot, normalizedResponse)
        snapshotPassed = snapshotDiff.length === 0

        if (!snapshotPassed && this.config.verbose) {
          console.log(chalk.yellow(`    ⚠ Snapshot mismatch (${snapshotDiff.length} differences)`))
        }
      } else if (this.config.verbose) {
        console.log(chalk.gray(`    ℹ No snapshot - will create one`))
      }

      // Determine if test passed
      const evaluation = this.evaluateTestResult(testCase, responseData, metrics, snapshotPassed)

      const result = {
        testCaseId: testCase.testCaseId,
        name: testCase.name,
        passed: evaluation.passed,
        failureReason: evaluation.reason || null,
        metrics,
        response: responseData,
        normalizedResponse,
        executionId,
        executionUrl,
        snapshotDiff,
        snapshotPassed,
        error: null,
      }

      if (this.config.verbose) {
        this.logTestResult(result)
      }

      return result
    } catch (error) {
      const responseTime = Date.now() - startTime

      const result = {
        testCaseId: testCase.testCaseId,
        name: testCase.name,
        passed: false,
        metrics: {
          responseTime,
          structureCompliance: false,
          errorHandling: false,
        },
        response: null,
        executionId: null,
        executionUrl: null,
        error: {
          message: error.message,
          code: error.code,
          response: error.response?.data,
        },
      }

      if (this.config.verbose) {
        this.logTestResult(result)
      }

      return result
    }
  }

  /**
   * Evaluate test result based on expected behavior
   * Returns object with passed status and optional failure reason
   */
  evaluateTestResult(testCase, response, metrics, snapshotPassed) {
    const expectedOutput = testCase.expectedOutput ? testCase.expectedOutput.toLowerCase() : ''

    // If we have a snapshot and update mode is not enabled, snapshot must match
    if (testCase.snapshot && !this.config.updateSnapshots && !snapshotPassed) {
      return { passed: false, reason: 'Snapshot mismatch' }
    }

    // Check if structure is valid
    if (!metrics.structureCompliance) {
      return { passed: false, reason: 'Invalid response structure' }
    }

    // Check based on expected outcome
    if (expectedOutput.includes('success')) {
      // Success case: must have valid structure and content
      if (metrics.isError) {
        return {
          passed: false,
          reason: `Expected success response but got error (isError: ${metrics.isError})`,
        }
      }
      if (!metrics.hasContent) {
        return {
          passed: false,
          reason: 'Expected success response but no content',
        }
      }
      return { passed: true }
    } else if (expectedOutput.includes('error') || expectedOutput.includes('notrelevant')) {
      // Error case: must have error indicator
      if (!metrics.isError) {
        return {
          passed: false,
          reason: `Expected error/notrelevant response but got success (isError: ${metrics.isError})`,
        }
      }
      return { passed: true }
    }

    // Default: check structure compliance and has content
    // If no expectedOutput specified, pass if response is valid and has content
    if (!metrics.hasContent) {
      return { passed: false, reason: 'Response has no content' }
    }
    return { passed: true }
  }

  /**
   * Log test result
   */
  logTestResult(result) {
    if (result.passed) {
      console.log(chalk.green(`  ✓ PASSED (${result.metrics.responseTime}ms)`))
    } else {
      console.log(chalk.red(`  ✗ FAILED (${result.metrics.responseTime}ms)`))
      if (result.error) {
        console.log(chalk.red(`    Error: ${result.error.message}`))
      }
      if (result.failureReason) {
        console.log(chalk.yellow(`    ⚠ ${result.failureReason}`))
      }
    }

    if (result.executionUrl) {
      console.log(chalk.gray(`    N8N Execution: ${result.executionUrl}`))
    } else if (result.executionId) {
      console.log(chalk.gray(`    N8N Execution ID: ${result.executionId}`))
    }

    // Display content size
    if (result.metrics.contentLength > 0) {
      const sizeKb = (result.metrics.contentLength / 1024).toFixed(2)
      console.log(chalk.gray(`    Response Size: ${sizeKb} KB`))
    }

    // Display snapshot status
    if (result.snapshotDiff) {
      if (!result.snapshotPassed) {
        console.log(chalk.yellow(`    📸 Snapshot: ${result.snapshotDiff.length} differences`))

        // Show first few differences
        const maxDiffsToShow = 5
        result.snapshotDiff.slice(0, maxDiffsToShow).forEach((diff) => {
          if (diff.type === 'value') {
            console.log(
              chalk.gray(
                `       ${diff.path}: ${JSON.stringify(diff.expected)} → ${JSON.stringify(diff.received)}`,
              ),
            )
          } else if (diff.type === 'missing-in-snapshot') {
            console.log(chalk.gray(`       ${diff.path}: added in response`))
          } else if (diff.type === 'missing-in-actual') {
            console.log(chalk.gray(`       ${diff.path}: missing in response`))
          } else if (diff.type === 'type-mismatch') {
            console.log(chalk.gray(`       ${diff.path}: type changed`))
          }
        })

        if (result.snapshotDiff.length > maxDiffsToShow) {
          console.log(
            chalk.gray(`       ... and ${result.snapshotDiff.length - maxDiffsToShow} more`),
          )
        }

        if (this.config.updateSnapshots) {
          console.log(chalk.cyan(`       → Will update snapshot`))
        }
      } else {
        console.log(chalk.gray(`    📸 Snapshot: matches`))
      }
    }
  }

  /**
   * Update snapshots in dataset file
   */
  updateDatasetSnapshots(results) {
    try {
      // Load current dataset
      const datasetPath = this.findDatasetPath()
      const dataset = this.readDataset()

      // Track updates
      let updatedCount = 0
      let createdCount = 0

      // Update snapshots for each test case
      dataset.testCases = dataset.testCases.map((testCase) => {
        const result = results.find((r) => r.testCaseId === testCase.testCaseId)

        if (!result) {
          return testCase
        }

        // Create or update snapshot
        if (!testCase.snapshot) {
          // Create new snapshot
          testCase.snapshot = result.normalizedResponse
          createdCount++
          if (this.config.verbose) {
            console.log(chalk.cyan(`  📸 Created snapshot for ${testCase.testCaseId}`))
          }
        } else if (result.snapshotDiff && result.snapshotDiff.length > 0) {
          // Update existing snapshot
          testCase.snapshot = result.normalizedResponse
          updatedCount++
          if (this.config.verbose) {
            console.log(chalk.cyan(`  📸 Updated snapshot for ${testCase.testCaseId}`))
          }
        }

        return testCase
      })

      // Save updated dataset
      fs.writeFileSync(datasetPath, JSON.stringify(dataset, null, 2))

      if (createdCount > 0 || updatedCount > 0) {
        console.log(chalk.cyan(`\n📸 Snapshots: ${createdCount} created, ${updatedCount} updated`))
        console.log(chalk.gray(`   Dataset file updated: ${datasetPath}`))
      }

      return { createdCount, updatedCount }
    } catch (error) {
      console.error(chalk.yellow(`\n⚠ Warning: Failed to update snapshots: ${error.message}`))
      return { createdCount: 0, updatedCount: 0 }
    }
  }

  /**
   * Run all tests
   */
  async runTests() {
    console.log(chalk.cyan.bold('\n🧪 N8N Workflow Test Runner\n'))
    console.log(chalk.gray(`Dataset: ${this.config.datasetFile}`))
    console.log(chalk.gray(`Run ID: ${this.runId}`))
    console.log(chalk.gray(`  Use this ID to filter executions in N8N\n`))

    // Load dataset first (may auto-detect workflow ID)
    const dataset = this.loadDataset()

    // Validate that we have either workflow ID or webhook URL
    if (!this.config.workflowId && !this.config.webhookUrl) {
      console.error(
        chalk.red(
          'Error: Either --workflow-id, --webhook-url, or dataset with metadata.workflowId is required',
        ),
      )
      process.exit(1)
    }

    // Resolve webhook URL if workflow ID is provided
    if (this.config.workflowId && !this.config.webhookUrl) {
      console.log(chalk.gray(`Fetching webhook URL for workflow ${this.config.workflowId}...`))

      if (!this.config.n8nApiKey) {
        console.error(
          chalk.red(
            'Error: N8N API key is required to fetch workflow details. Set via --n8n-api-key or N8N_API_KEY env var.',
          ),
        )
        process.exit(1)
      }

      try {
        this.config.webhookUrl = await getWorkflowWebhookUrl(
          this.config.workflowId,
          this.config.n8nBaseUrl,
          this.config.n8nApiKey,
        )
        console.log(chalk.green(`✓ Found webhook: ${this.config.webhookUrl}\n`))
      } catch (error) {
        console.error(chalk.red(`✗ ${error.message}`))
        process.exit(1)
      }
    }

    console.log(chalk.gray(`Webhook: ${this.config.webhookUrl}`))
    console.log(chalk.gray(`Timeout: ${this.config.timeout}ms`))
    console.log(chalk.gray(`Mode: ${this.config.parallel ? 'Parallel' : 'Sequential'}\n`))

    const testCases = dataset.testCases

    console.log(chalk.cyan(`Running ${testCases.length} test cases...\n`))

    if (this.config.parallel) {
      // Run tests in parallel
      this.results = await Promise.all(testCases.map((tc) => this.executeTestCase(tc)))
    } else {
      // Run tests sequentially
      for (const testCase of testCases) {
        const result = await this.executeTestCase(testCase)
        this.results.push(result)
      }
    }

    return this.results
  }

  /**
   * Generate and save test report
   */
  generateReport(results, stats) {
    const report = {
      timestamp: new Date().toISOString(),
      runId: this.runId,
      config: {
        datasetFile: this.config.datasetFile,
        webhookUrl: this.config.webhookUrl,
        timeout: this.config.timeout,
        parallel: this.config.parallel,
      },
      summary: stats,
      results: results.map((r) => ({
        testCaseId: r.testCaseId,
        name: r.name,
        passed: r.passed,
        executionId: r.executionId,
        executionUrl: r.executionUrl,
        metrics: r.metrics,
        snapshotPassed: r.snapshotPassed,
        snapshotDiffCount: r.snapshotDiff ? r.snapshotDiff.length : 0,
        error: r.error,
        response: r.response,
        snapshotDiff: r.snapshotDiff,
      })),
    }

    // Generate dynamic report filename based on dataset name and timestamp
    let outputPath
    if (this.config.outputFile === DEFAULT_OUTPUT) {
      // Extract dataset name (without path and extension)
      const datasetPath = path.basename(this.config.datasetFile)
      const datasetName = datasetPath.replace(/\.json$/, '')

      // Format timestamp as YYYY-MM-DD_HH-mm-ss
      const now = new Date()
      const timestamp = now
        .toISOString()
        .replace(/T/, '_')
        .replace(/:/g, '-')
        .replace(/\.\d+Z$/, '')

      // Build dynamic filename: datasetName_timestamp.json
      const reportFileName = `${datasetName}_${timestamp}.json`
      outputPath = path.join('/tests/reports', reportFileName)
    } else {
      // Use custom output path if provided
      outputPath = path.resolve(this.config.outputFile)
    }

    // Ensure reports directory exists
    const reportDir = path.dirname(outputPath)
    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true })
    }

    fs.writeFileSync(outputPath, JSON.stringify(report, null, 2))

    return outputPath
  }

  /**
   * Display summary
   */
  displaySummary(stats) {
    console.log(chalk.cyan.bold('\n📊 Test Summary\n'))

    console.log(chalk.white(`Total Tests:     ${stats.total}`))
    console.log(chalk.green(`Passed:          ${stats.passed}`))
    console.log(chalk.red(`Failed:          ${stats.failed}`))
    console.log(chalk.white(`Success Rate:    ${stats.successRate}%`))
    console.log(chalk.white(`Struct Compliance: ${stats.structureComplianceRate}%`))

    console.log(chalk.cyan.bold('\n⏱️  Performance\n'))
    console.log(chalk.white(`Avg Response:    ${stats.avgResponseTime}ms`))
    console.log(chalk.white(`Min Response:    ${stats.minResponseTime}ms`))
    console.log(chalk.white(`Max Response:    ${stats.maxResponseTime}ms`))

    // Overall result
    console.log('')
    if (stats.successRate === 100) {
      console.log(chalk.green.bold('✓ All tests passed!'))
    } else if (stats.successRate >= 80) {
      console.log(chalk.yellow.bold('⚠ Some tests failed'))
    } else {
      console.log(chalk.red.bold('✗ Many tests failed'))
    }
    console.log('')
  }
}

// Main execution
async function main() {
  try {
    const runner = new WorkflowTestRunner(config)

    // Run tests
    const results = await runner.runTests()

    // Update snapshots if requested or if new snapshots need to be created
    const needsSnapshotUpdate =
      config.updateSnapshots ||
      results.some((r) => !r.error && r.normalizedResponse && r.snapshotDiff === null)

    if (needsSnapshotUpdate) {
      runner.updateDatasetSnapshots(results)
    }

    // Calculate statistics
    const stats = MetricsCalculator.calculateSuiteStats(results)

    // Display summary
    runner.displaySummary(stats)

    // Generate report
    const reportPath = runner.generateReport(results, stats)
    console.log(chalk.gray(`\n📄 Report saved to: ${reportPath}\n`))

    // Exit with appropriate code
    process.exit(stats.successRate === 100 ? 0 : 1)
  } catch (error) {
    console.error(chalk.red.bold('\n✗ Fatal Error:\n'))
    console.error(chalk.red(error.message))
    console.error(chalk.gray('\n' + error.stack))
    process.exit(1)
  }
}

// Run main function
main()
