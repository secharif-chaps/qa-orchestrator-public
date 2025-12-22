/**
 * Types for CSV/Excel user import functionality
 */

/**
 * Wizard step identifiers
 */
export type WizardStep = 'upload' | 'map' | 'review' | 'results'

/**
 * Single user row from CSV/Excel import
 */
export interface UserImportRow {
  username: string
  email: string
  firstname?: string
  lastname?: string
  password?: string
}

/**
 * Column mapping configuration
 */
export interface ColumnMapping {
  /** CSV column header */
  csvColumn: string
  /** Target field (username, email, firstname, lastname, password, or null for ignore) */
  targetField: 'username' | 'email' | 'firstname' | 'lastname' | 'password' | null
  /** Whether this field is required (username and email are required) */
  isRequired: boolean
}

/**
 * Validation error for a specific row/field
 */
export interface ValidationError {
  /** Row index (0-based) */
  row: number
  /** Field that has the error */
  field: string
  /** Error message */
  message: string
}

/**
 * Result for a single user import attempt
 */
export interface UserImportResult {
  /** Row index (0-based) */
  row_index: number
  /** Username that was attempted */
  username: string
  /** Email that was attempted */
  email: string
  /** Whether import was successful */
  success: boolean
  /** Error message if failed */
  error_message?: string
  /** Keycloak user ID if created successfully */
  user_id?: string
  /** Generated password if one was created */
  generated_password?: string
}

/**
 * Response from bulk import API
 */
export interface BulkImportResponse {
  /** Number of successfully imported users */
  success_count: number
  /** Number of failed imports */
  error_count: number
  /** Total number of users attempted */
  total_count: number
  /** Detailed results per row */
  results: UserImportResult[]
}

/**
 * Request body for bulk import API
 */
export interface BulkImportRequest {
  /** Target organization ID */
  organization_id: string
  /** Users to import */
  users: UserImportRow[]
  /** Whether to generate passwords for users without passwords */
  generate_passwords: boolean
}

/**
 * Parsed file data from CSV/Excel
 */
export interface ParsedFileData {
  /** Original file name */
  fileName: string
  /** File size in bytes */
  fileSize: number
  /** Column headers from the file */
  headers: string[]
  /** Data rows (array of arrays, each inner array is a row) */
  rows: string[][]
  /** Total row count (excluding header) */
  rowCount: number
}

/**
 * Duplicate detection result
 */
export interface DuplicateInfo {
  /** Email that is duplicated */
  email: string
  /** Username associated with the duplicate email */
  username: string
  /** Row index where duplicate was found */
  rowIndex: number
  /** Whether it's a duplicate within the file or against existing users */
  type: 'internal' | 'existing'
}

/**
 * Column pattern for auto-detection
 */
export interface ColumnPattern {
  /** Target field name */
  field: 'username' | 'email' | 'firstname' | 'lastname' | 'password'
  /** Patterns to match (lowercase) */
  patterns: string[]
}

/**
 * Column patterns for auto-detection
 * Based on spec requirements
 */
export const COLUMN_PATTERNS: ColumnPattern[] = [
  {
    field: 'username',
    patterns: ['username', 'user_name', 'login', 'user', 'utilisateur'],
  },
  {
    field: 'email',
    patterns: ['email', 'e-mail', 'mail', 'emails', 'courriel', 'e_mail'],
  },
  {
    field: 'firstname',
    patterns: ['firstname', 'first_name', 'prenom', 'given_name', 'first', 'prénom'],
  },
  {
    field: 'lastname',
    patterns: ['lastname', 'last_name', 'nom', 'family_name', 'last', 'name', 'surname'],
  },
  {
    field: 'password',
    patterns: ['password', 'pwd', 'pass', 'mot_de_passe'],
  },
]

/**
 * Maximum number of rows allowed per import
 */
export const MAX_IMPORT_ROWS = 100

/**
 * Supported file extensions
 */
export const SUPPORTED_FILE_EXTENSIONS = ['.csv', '.xlsx'] as const
export type SupportedFileExtension = (typeof SUPPORTED_FILE_EXTENSIONS)[number]
