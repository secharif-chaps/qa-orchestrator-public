#!/usr/bin/env node

/**
 * Dataset Schema Validator
 *
 * Validates N8N test datasets against the JSON Schema
 *
 * Usage:
 *   node validate-datasets.js [dataset-file]
 *   node validate-datasets.js                    # Validates all datasets
 *   node validate-datasets.js datasets/test.json # Validates specific file
 */

const fs = require('fs')
const path = require('path')
const Ajv = require('ajv')
const chalk = require('chalk')

// Configuration
const SCHEMA_PATH = path.join(__dirname, 'dataset-schema.json')
const DATASETS_DIR = path.join(__dirname, 'datasets')

// Initialize AJV with strict mode
const ajv = new Ajv({
  allErrors: true,
  verbose: true,
  strict: true,
})

/**
 * Load and compile schema
 */
function loadSchema() {
  try {
    const schemaContent = fs.readFileSync(SCHEMA_PATH, 'utf8')
    const schema = JSON.parse(schemaContent)
    return ajv.compile(schema)
  } catch (error) {
    console.error(chalk.red(`Failed to load schema: ${error.message}`))
    process.exit(1)
  }
}

/**
 * Find all dataset files
 */
function findDatasetFiles() {
  try {
    return fs
      .readdirSync(DATASETS_DIR)
      .filter((file) => file.endsWith('.json'))
      .map((file) => path.join(DATASETS_DIR, file))
  } catch (error) {
    console.error(chalk.red(`Failed to read datasets directory: ${error.message}`))
    return []
  }
}

/**
 * Validate a single dataset file
 */
function validateDataset(filePath, validate) {
  const fileName = path.basename(filePath)

  try {
    // Load dataset
    const datasetContent = fs.readFileSync(filePath, 'utf8')
    const dataset = JSON.parse(datasetContent)

    // Validate against schema
    const valid = validate(dataset)

    if (valid) {
      return {
        file: fileName,
        valid: true,
        testCount: dataset.testCases?.length || 0,
        workflowId: dataset.metadata?.workflowId,
        workflowName: dataset.metadata?.workflowName,
      }
    } else {
      return {
        file: fileName,
        valid: false,
        errors: validate.errors,
      }
    }
  } catch (error) {
    return {
      file: fileName,
      valid: false,
      errors: [
        {
          message: `Failed to parse JSON: ${error.message}`,
          dataPath: '',
        },
      ],
    }
  }
}

/**
 * Format validation error for display
 */
function formatError(error) {
  const path = error.instancePath || error.dataPath || 'root'
  const message = error.message || 'Unknown error'
  const params = error.params ? ` (${JSON.stringify(error.params)})` : ''

  return `  ${chalk.yellow('→')} ${chalk.cyan(path)}: ${message}${params}`
}

/**
 * Display validation results
 */
function displayResults(results) {
  console.log(chalk.cyan.bold('\n📋 Dataset Validation Results\n'))

  let validCount = 0
  let invalidCount = 0
  let totalTests = 0

  for (const result of results) {
    if (result.valid) {
      validCount++
      totalTests += result.testCount
      console.log(chalk.green(`✓ ${result.file}`))
      console.log(chalk.gray(`  Workflow: ${result.workflowName} (${result.workflowId})`))
      console.log(chalk.gray(`  Test cases: ${result.testCount}`))
    } else {
      invalidCount++
      console.log(chalk.red(`✗ ${result.file}`))
      result.errors.forEach((error) => {
        console.log(formatError(error))
      })
    }
    console.log('')
  }

  // Summary
  console.log(chalk.cyan.bold('📊 Summary\n'))
  console.log(chalk.white(`Total datasets:  ${results.length}`))
  console.log(chalk.green(`Valid:           ${validCount}`))
  console.log(chalk.red(`Invalid:         ${invalidCount}`))
  console.log(chalk.gray(`Total test cases: ${totalTests}`))
  console.log('')

  if (invalidCount === 0) {
    console.log(chalk.green.bold('✓ All datasets are valid!'))
  } else {
    console.log(chalk.red.bold('✗ Some datasets have validation errors'))
  }
  console.log('')

  return invalidCount === 0
}

/**
 * Main execution
 */
function main() {
  const args = process.argv.slice(2)
  const specificFile = args[0]

  console.log(chalk.cyan.bold('🔍 N8N Dataset Schema Validator\n'))

  // Load schema
  console.log(chalk.gray(`Loading schema from ${path.basename(SCHEMA_PATH)}...`))
  const validate = loadSchema()
  console.log(chalk.green('✓ Schema loaded successfully\n'))

  // Find datasets to validate
  let datasetFiles
  if (specificFile) {
    const fullPath = path.isAbsolute(specificFile)
      ? specificFile
      : path.join(process.cwd(), specificFile)

    if (!fs.existsSync(fullPath)) {
      console.error(chalk.red(`Error: File not found: ${specificFile}`))
      process.exit(1)
    }

    datasetFiles = [fullPath]
    console.log(chalk.gray(`Validating single file: ${path.basename(fullPath)}\n`))
  } else {
    datasetFiles = findDatasetFiles()
    console.log(
      chalk.gray(`Found ${datasetFiles.length} dataset files in ${path.basename(DATASETS_DIR)}/\n`),
    )
  }

  if (datasetFiles.length === 0) {
    console.log(chalk.yellow('No dataset files found'))
    process.exit(0)
  }

  // Validate all datasets
  const results = datasetFiles.map((file) => validateDataset(file, validate))

  // Display results
  const allValid = displayResults(results)

  // Exit with appropriate code
  process.exit(allValid ? 0 : 1)
}

// Run main function
main()
