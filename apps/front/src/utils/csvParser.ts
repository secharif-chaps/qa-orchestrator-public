export interface ParsedCompany {
  row_number: number
  name: string
  website: string
}

export interface CSVParseResult {
  companies: ParsedCompany[]
  errors: string[]
}

// Possible column names for company name
const NAME_VARIATIONS = [
  'name',
  'company',
  'company name',
  'company_name',
  'companyname',
  'nom',
  'entreprise',
  "nom de l'entreprise",
  'nom entreprise',
  'business',
  'business name',
  'organization',
  'org',
  'entity',
  'firm',
  'societe',
  'société',
]

// Possible column names for website
const WEBSITE_VARIATIONS = [
  'website',
  'url',
  'site',
  'web',
  'domain',
  'homepage',
  'site web',
  'site internet',
  'web address',
  'company website',
  'company url',
  'link',
  'www',
  'site url',
  'web url',
]

function normalizeHeader(header: string): string {
  return header
    .toLowerCase()
    .trim()
    .replace(/[\s_-]+/g, ' ')
}

function findColumnIndex(headers: string[], variations: string[]): number {
  const normalizedHeaders = headers.map(normalizeHeader)

  for (const variation of variations) {
    const normalizedVariation = normalizeHeader(variation)
    const index = normalizedHeaders.indexOf(normalizedVariation)
    if (index !== -1) return index
  }

  return -1
}

function cleanWebsiteUrl(url: string): string {
  if (!url) return ''

  url = url.trim()

  // Remove quotes if present
  if ((url.startsWith('"') && url.endsWith('"')) || (url.startsWith("'") && url.endsWith("'"))) {
    url = url.slice(1, -1)
  }

  // Add https:// if no protocol is present
  if (url && !url.match(/^https?:\/\//i)) {
    url = 'https://' + url
  }

  return url
}

export function parseCSV(content: string): CSVParseResult {
  const errors: string[] = []
  const companies: ParsedCompany[] = []

  // Split into lines and filter out empty lines
  const lines = content.split(/\r?\n/).filter((line) => line.trim())

  if (lines.length < 2) {
    errors.push('CSV file must contain at least a header row and one data row')
    return { companies, errors }
  }

  // Parse header row
  const headers = lines[0].split(',').map((h) => h.trim())

  // Find column indices for name and website
  const nameIndex = findColumnIndex(headers, NAME_VARIATIONS)
  const websiteIndex = findColumnIndex(headers, WEBSITE_VARIATIONS)

  if (nameIndex === -1) {
    errors.push(
      `Could not find company name column. Expected one of: ${NAME_VARIATIONS.slice(0, 5).join(', ')}, etc.`,
    )
  }

  if (websiteIndex === -1) {
    errors.push(
      `Could not find website column. Expected one of: ${WEBSITE_VARIATIONS.slice(0, 5).join(', ')}, etc.`,
    )
  }

  if (errors.length > 0) {
    return { companies, errors }
  }

  // Parse data rows
  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim()
    if (!line) continue

    // Simple CSV parsing (handles basic cases, not quoted commas)
    const values = line.split(',').map((v) => v.trim())

    const name = values[nameIndex]?.trim() || ''
    const website = cleanWebsiteUrl(values[websiteIndex] || '')

    if (name || website) {
      companies.push({
        row_number: i,
        name,
        website,
      })
    }
  }

  if (companies.length === 0) {
    errors.push('No valid company data found in CSV file')
  }

  return { companies, errors }
}

// Advanced CSV parsing that handles quoted fields with commas
export function parseCSVAdvanced(content: string): CSVParseResult {
  const errors: string[] = []
  const companies: ParsedCompany[] = []

  // Split into lines and filter out empty lines
  const lines = content.split(/\r?\n/).filter((line) => line.trim())

  if (lines.length < 2) {
    errors.push('CSV file must contain at least a header row and one data row')
    return { companies, errors }
  }

  // Parse CSV line handling quoted fields
  const parseCSVLine = (line: string): string[] => {
    const result: string[] = []
    let current = ''
    let inQuotes = false

    for (let i = 0; i < line.length; i++) {
      const char = line[i]
      const nextChar = line[i + 1]

      if (char === '"') {
        if (inQuotes && nextChar === '"') {
          // Escaped quote
          current += '"'
          i++ // Skip next quote
        } else {
          // Toggle quote mode
          inQuotes = !inQuotes
        }
      } else if (char === ',' && !inQuotes) {
        // Field separator
        result.push(current.trim())
        current = ''
      } else {
        current += char
      }
    }

    // Add last field
    result.push(current.trim())

    return result
  }

  // Parse header row
  const headers = parseCSVLine(lines[0])

  // Find column indices for name and website
  const nameIndex = findColumnIndex(headers, NAME_VARIATIONS)
  const websiteIndex = findColumnIndex(headers, WEBSITE_VARIATIONS)

  if (nameIndex === -1) {
    errors.push(
      `Could not find company name column. Expected one of: ${NAME_VARIATIONS.slice(0, 5).join(', ')}, etc.`,
    )
  }

  if (websiteIndex === -1) {
    errors.push(
      `Could not find website column. Expected one of: ${WEBSITE_VARIATIONS.slice(0, 5).join(', ')}, etc.`,
    )
  }

  if (errors.length > 0) {
    return { companies, errors }
  }

  // Parse data rows
  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim()
    if (!line) continue

    const values = parseCSVLine(line)

    const name = values[nameIndex]?.trim() || ''
    const website = cleanWebsiteUrl(values[websiteIndex] || '')

    if (name || website) {
      companies.push({
        row_number: i,
        name,
        website,
      })
    }
  }

  if (companies.length === 0) {
    errors.push('No valid company data found in CSV file')
  }

  return { companies, errors }
}
