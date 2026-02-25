/**
 * Composable for mapping CSV columns to user import fields
 *
 * Features:
 * - Auto-detect column mappings based on header patterns
 * - Manual override of mappings via dropdown
 * - Track required fields (username, email) mapping status
 * - Detect if password column exists in CSV
 */

import { ref, computed, type Ref, type ComputedRef } from 'vue'
import type { ColumnMapping, UserImportRow, ParsedFileData } from '@/types/user-import'
import { COLUMN_PATTERNS } from '@/types/user-import'

export type TargetField = 'username' | 'email' | 'firstname' | 'lastname' | 'password' | null

export interface ColumnMapperReturn {
  /** Current column mappings */
  mappings: Ref<ColumnMapping[]>
  /** Whether all required fields are mapped */
  isValid: ComputedRef<boolean>
  /** Whether password column is detected/mapped */
  hasPasswordColumn: ComputedRef<boolean>
  /** List of unmapped columns */
  unmappedColumns: ComputedRef<string[]>
  /** Auto-detect mappings from headers */
  autoDetectMappings: (headers: string[]) => void
  /** Update a specific column mapping */
  updateMapping: (csvColumn: string, targetField: TargetField) => void
  /** Transform parsed rows to UserImportRow objects based on current mappings */
  transformRows: (data: ParsedFileData) => UserImportRow[]
  /** Reset mappings */
  reset: () => void
}

/**
 * Auto-detect the target field for a column header
 */
function detectFieldForHeader(header: string): TargetField {
  const headerLower = header.toLowerCase().trim()

  for (const pattern of COLUMN_PATTERNS) {
    if (pattern.patterns.some((p) => headerLower === p || headerLower.includes(p))) {
      return pattern.field
    }
  }

  return null
}

/**
 * Composable for column mapping logic
 */
export function useColumnMapper(): ColumnMapperReturn {
  const mappings = ref<ColumnMapping[]>([])

  /**
   * Check if all required fields are mapped
   */
  const isValid = computed(() => {
    const mappedFields = new Set(
      mappings.value.filter((m) => m.targetField !== null).map((m) => m.targetField),
    )

    return mappedFields.has('username') && mappedFields.has('email')
  })

  /**
   * Check if password column is mapped
   */
  const hasPasswordColumn = computed(() => {
    return mappings.value.some((m) => m.targetField === 'password')
  })

  /**
   * Get list of columns that are set to ignore
   */
  const unmappedColumns = computed(() => {
    return mappings.value.filter((m) => m.targetField === null).map((m) => m.csvColumn)
  })

  /**
   * Auto-detect mappings based on header patterns
   */
  function autoDetectMappings(headers: string[]): void {
    const usedFields = new Set<TargetField>()

    mappings.value = headers.map((header) => {
      const detectedField = detectFieldForHeader(header)

      // Avoid mapping multiple columns to the same field
      let targetField: TargetField = null
      if (detectedField && !usedFields.has(detectedField)) {
        targetField = detectedField
        usedFields.add(detectedField)
      }

      return {
        csvColumn: header,
        targetField,
        isRequired: targetField === 'username' || targetField === 'email',
      }
    })
  }

  /**
   * Update a specific column mapping
   */
  function updateMapping(csvColumn: string, targetField: TargetField): void {
    const mapping = mappings.value.find((m) => m.csvColumn === csvColumn)
    if (mapping) {
      // If this field is already used by another column, clear that mapping
      if (targetField !== null) {
        const existingMapping = mappings.value.find(
          (m) => m.targetField === targetField && m.csvColumn !== csvColumn,
        )
        if (existingMapping) {
          existingMapping.targetField = null
          existingMapping.isRequired = false
        }
      }

      mapping.targetField = targetField
      mapping.isRequired = targetField === 'username' || targetField === 'email'
    }
  }

  /**
   * Transform parsed rows to UserImportRow objects based on current mappings
   */
  function transformRows(data: ParsedFileData): UserImportRow[] {
    // Build a mapping of column index to target field
    const columnIndexToField = new Map<number, TargetField>()

    mappings.value.forEach((mapping) => {
      const columnIndex = data.headers.indexOf(mapping.csvColumn)
      if (columnIndex !== -1 && mapping.targetField) {
        columnIndexToField.set(columnIndex, mapping.targetField)
      }
    })

    // Transform each row
    return data.rows.map((row) => {
      const user: UserImportRow = {
        username: '',
        email: '',
      }

      columnIndexToField.forEach((field, columnIndex) => {
        const value = row[columnIndex]?.trim() || ''

        switch (field) {
          case 'username':
            user.username = value
            break
          case 'email':
            user.email = value
            break
          case 'firstname':
            user.firstname = value || undefined
            break
          case 'lastname':
            user.lastname = value || undefined
            break
          case 'password':
            user.password = value || undefined
            break
        }
      })

      return user
    })
  }

  /**
   * Reset all mappings
   */
  function reset(): void {
    mappings.value = []
  }

  return {
    mappings,
    isValid,
    hasPasswordColumn,
    unmappedColumns,
    autoDetectMappings,
    updateMapping,
    transformRows,
    reset,
  }
}
