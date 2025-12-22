/**
 * Utility to generate and download a CSV file with generated passwords
 */

import type { UserImportResult } from '@/types/user-import'

/**
 * Generate CSV content from import results that have generated passwords
 */
function generatePasswordsCsvContent(results: UserImportResult[]): string {
  // Filter to only successful imports with generated passwords
  const usersWithPasswords = results.filter(
    (r) => r.success && r.generated_password,
  )

  if (usersWithPasswords.length === 0) {
    return ''
  }

  // Build CSV content
  const headers = 'username,email,temporary_password'
  const rows = usersWithPasswords.map(
    (r) => `${r.username},${r.email},${r.generated_password}`,
  )

  return [headers, ...rows].join('\n')
}

/**
 * Download the generated passwords as a CSV file
 *
 * @param results - The import results containing generated passwords
 * @param fileName - Optional custom filename (defaults to timestamp-based name)
 */
export function downloadPasswordsCsv(
  results: UserImportResult[],
  fileName?: string,
): void {
  const content = generatePasswordsCsvContent(results)

  if (!content) {
    console.warn('No generated passwords to download')
    return
  }

  // Generate default filename with timestamp
  const timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '')
  const defaultFileName = `imported-users-passwords-${timestamp}.csv`

  // Create blob from content
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })

  // Create download link
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)

  link.setAttribute('href', url)
  link.setAttribute('download', fileName || defaultFileName)
  link.style.visibility = 'hidden'

  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  // Clean up URL object
  URL.revokeObjectURL(url)
}

/**
 * Check if there are any generated passwords in the results
 */
export function hasGeneratedPasswords(results: UserImportResult[]): boolean {
  return results.some((r) => r.success && r.generated_password)
}
