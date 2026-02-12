/**
 * Composable for parsing CSV and Excel files
 *
 * Supports:
 * - CSV files (.csv) with comma, semicolon, or tab delimiters
 * - Excel files (.xlsx) using SheetJS
 * - Header extraction and data row parsing
 * - Row count validation (max 100 rows)
 */

import { ref } from 'vue'
import * as XLSX from 'xlsx'
import type { ParsedFileData } from '@/types/user-import'
import { MAX_IMPORT_ROWS, SUPPORTED_FILE_EXTENSIONS } from '@/types/user-import'

export interface CsvParserState {
  /** Parsed file data */
  data: ParsedFileData | null
  /** Whether parsing is in progress */
  isParsing: boolean
  /** Error message if parsing failed */
  error: string | null
}

export interface CsvParserReturn {
  /** Current parser state */
  state: CsvParserState
  /** Parse a file */
  parseFile: (file: File) => Promise<ParsedFileData>
  /** Reset the parser state */
  reset: () => void
  /** Validate file before parsing */
  validateFile: (file: File) => string | null
}

/**
 * Detect CSV delimiter (comma, semicolon, or tab)
 */
function detectDelimiter(content: string): string {
  const firstLine = content.split('\n')[0] || ''

  // Count occurrences of each delimiter
  const commaCount = (firstLine.match(/,/g) || []).length
  const semicolonCount = (firstLine.match(/;/g) || []).length
  const tabCount = (firstLine.match(/\t/g) || []).length

  // Return the most common delimiter
  if (semicolonCount > commaCount && semicolonCount > tabCount) {
    return ';'
  }
  if (tabCount > commaCount && tabCount > semicolonCount) {
    return '\t'
  }
  return ','
}

/**
 * Parse CSV content into rows
 */
function parseCsvContent(content: string): string[][] {
  const delimiter = detectDelimiter(content)
  const lines = content.split(/\r?\n/).filter((line) => line.trim() !== '')

  return lines.map((line) => {
    // Simple CSV parsing - handles basic quoted values
    const row: string[] = []
    let current = ''
    let inQuotes = false

    for (let i = 0; i < line.length; i++) {
      const char = line[i]

      if (char === '"') {
        if (inQuotes && line[i + 1] === '"') {
          // Escaped quote
          current += '"'
          i++
        } else {
          inQuotes = !inQuotes
        }
      } else if (char === delimiter && !inQuotes) {
        row.push(current.trim())
        current = ''
      } else {
        current += char
      }
    }

    row.push(current.trim())
    return row
  })
}

/**
 * Parse Excel file using SheetJS
 */
function parseExcelFile(arrayBuffer: ArrayBuffer): string[][] {
  const workbook = XLSX.read(arrayBuffer, { type: 'array' })

  // Get the first sheet
  const sheetName = workbook.SheetNames[0]
  if (!sheetName) {
    throw new Error('Excel file has no sheets')
  }

  const sheet = workbook.Sheets[sheetName]
  if (!sheet) {
    throw new Error('Failed to read Excel sheet')
  }

  // Convert to array of arrays
  const data = XLSX.utils.sheet_to_json<string[]>(sheet, {
    header: 1,
    defval: '',
    blankrows: false,
  })

  // Convert all values to strings
  return data.map((row) => row.map((cell) => String(cell ?? '').trim()))
}

/**
 * Composable for parsing CSV and Excel files
 */
export function useCsvParser(): CsvParserReturn {
  const state = ref<CsvParserState>({
    data: null,
    isParsing: false,
    error: null,
  })

  /**
   * Validate file before parsing
   */
  function validateFile(file: File): string | null {
    // Check file extension
    const fileName = file.name.toLowerCase()
    const hasValidExtension = SUPPORTED_FILE_EXTENSIONS.some((ext) => fileName.endsWith(ext))

    if (!hasValidExtension) {
      return `Invalid file format. Supported formats: ${SUPPORTED_FILE_EXTENSIONS.join(', ')}`
    }

    // Check file size (max 5MB)
    const maxSize = 5 * 1024 * 1024
    if (file.size > maxSize) {
      return 'File is too large. Maximum size is 5MB.'
    }

    return null
  }

  /**
   * Parse a CSV or Excel file
   */
  async function parseFile(file: File): Promise<ParsedFileData> {
    state.value.isParsing = true
    state.value.error = null
    state.value.data = null

    try {
      // Validate file
      const validationError = validateFile(file)
      if (validationError) {
        throw new Error(validationError)
      }

      const fileName = file.name.toLowerCase()
      let allRows: string[][]

      if (fileName.endsWith('.xlsx')) {
        // Parse Excel file
        const arrayBuffer = await file.arrayBuffer()
        allRows = parseExcelFile(arrayBuffer)
      } else {
        // Parse CSV file
        const content = await file.text()
        allRows = parseCsvContent(content)
      }

      // Validate we have data
      if (allRows.length === 0) {
        throw new Error('File is empty')
      }

      if (allRows.length === 1) {
        throw new Error('File contains only headers, no data rows')
      }

      // Extract headers (first row) and data rows
      const headers = allRows[0] ?? []
      const rows = allRows.slice(1)

      // Validate row count
      if (rows.length > MAX_IMPORT_ROWS) {
        throw new Error(
          `Too many rows. Maximum ${MAX_IMPORT_ROWS} users per import. File has ${rows.length} rows.`,
        )
      }

      // Build parsed data
      const parsedData: ParsedFileData = {
        fileName: file.name,
        fileSize: file.size,
        headers,
        rows,
        rowCount: rows.length,
      }

      state.value.data = parsedData
      return parsedData
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to parse file'
      state.value.error = errorMessage
      throw new Error(errorMessage)
    } finally {
      state.value.isParsing = false
    }
  }

  /**
   * Reset the parser state
   */
  function reset(): void {
    state.value.data = null
    state.value.isParsing = false
    state.value.error = null
  }

  return {
    state: state.value,
    parseFile,
    reset,
    validateFile,
  }
}
