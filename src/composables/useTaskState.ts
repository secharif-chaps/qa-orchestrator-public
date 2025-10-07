import type { TaskResponse } from '@/types/task'

interface Company {
  tasks?: TaskResponse[]
  [key: string]: any
}

/**
 * Utility function to check if data exists for a specific section
 *
 * @param company - Company data
 * @param section - Section to check (e.g., 'jobs', 'timeline', 'team')
 * @param dataPath - Optional specific path to check within the section
 * @returns Boolean indicating if data exists
 */
export function hasDataForSection(
  company: Company | undefined,
  section: string,
  dataPath?: string,
): boolean {
  if (!company || !company[section]) return false

  const sectionData = company[section]

  // If no specific data path, check if section exists and is not empty
  if (!dataPath) {
    if (Array.isArray(sectionData)) return sectionData.length > 0
    if (typeof sectionData === 'object') return Object.keys(sectionData).length > 0
    return !!sectionData
  }

  // Check specific data path
  const pathParts = dataPath.split('.')
  let currentData = sectionData

  for (const part of pathParts) {
    if (!currentData || typeof currentData !== 'object') return false
    currentData = currentData[part]
  }

  if (Array.isArray(currentData)) return currentData.length > 0
  if (typeof currentData === 'object') return Object.keys(currentData).length > 0
  return !!currentData
}
