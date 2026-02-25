/**
 * User import API functions
 */

import { apiClient } from './client'
import type { BulkImportRequest, BulkImportResponse } from '@/types/user-import'

/**
 * Import users in bulk to an organization
 *
 * @param request - The bulk import request containing organization_id, users array, and generate_passwords flag
 * @returns The bulk import response with success/error counts and detailed results
 */
export const importUsers = async (request: BulkImportRequest): Promise<BulkImportResponse> => {
  return apiClient.post<BulkImportResponse>('/users/import', request)
}
