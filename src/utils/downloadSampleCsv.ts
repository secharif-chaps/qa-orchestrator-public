/**
 * Utility to generate and download a sample CSV file for user import
 */

/**
 * Sample CSV content with headers and example data rows
 * Third row demonstrates optional password field (left empty to trigger generation)
 */
const SAMPLE_CSV_CONTENT = `username,email,firstname,lastname,password
jdoe,john.doe@example.com,John,Doe,SecurePass123!
asmith,alice.smith@example.com,Alice,Smith,MyP@ssw0rd!
bwilson,bob.wilson@example.com,Bob,Wilson,`

/**
 * Download the sample users import CSV file
 *
 * Creates a CSV file with proper headers and 3 example rows:
 * - Two rows with passwords provided
 * - One row with empty password (demonstrates auto-generation)
 */
export function downloadSampleUsersCsv(): void {
  // Create blob from content
  const blob = new Blob([SAMPLE_CSV_CONTENT], { type: 'text/csv;charset=utf-8;' })

  // Create download link
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)

  link.setAttribute('href', url)
  link.setAttribute('download', 'sample-users-import.csv')
  link.style.visibility = 'hidden'

  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  // Clean up URL object
  URL.revokeObjectURL(url)
}
