/**
 * User import mutations
 */

import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { importUsers } from '@/api/user-import'
import { ADMIN_USER_QUERY_KEYS } from '@/queries/admin-users'
import { ORGANIZATION_QUERY_KEYS } from '@/queries/organization-admin'
import { toast } from '@/utils/toast'
import type { BulkImportRequest, BulkImportResponse } from '@/types/user-import'

/**
 * Mutation to bulk import users from CSV/Excel data
 *
 * Features:
 * - Invalidates admin user queries on success
 * - Invalidates organization member queries on success
 * - Shows success toast with count
 * - Shows error toast on failure
 */
export const useImportUsers = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (request: BulkImportRequest) => importUsers(request),
    onSuccess: (response: BulkImportResponse) => {
      // Show success/partial success message
      if (response.error_count === 0) {
        toast.success(`Successfully imported ${response.success_count} users`)
      } else if (response.success_count > 0) {
        toast.warning(`Imported ${response.success_count} users, ${response.error_count} failed`)
      } else {
        toast.error(`Import failed: ${response.error_count} users could not be imported`)
      }

      // Invalidate admin user queries to refresh the user list
      queryCache.invalidateQueries({ key: ADMIN_USER_QUERY_KEYS.root })

      // TODO : optimistic ui update for organization members and count
    },
    onError: (error: unknown) => {
      const errorMessage = error instanceof Error ? error.message : 'Failed to import users'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    importUsers: mutate,
    importUsersAsync: mutateAsync,
    isLoading: mutation.isLoading,
  }
})
