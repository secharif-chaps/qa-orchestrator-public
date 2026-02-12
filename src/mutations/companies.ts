import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createCompany, deleteCompany, restoreCompany, refreshCompany } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import { ORGANIZATION_TOKEN_KEYS } from '@/queries/tokens'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'
import { TASK_QUERY_KEYS } from '@/queries/tasks'
import type { Company } from '@/types/company'
import type { TokenBalanceResponse } from '@/types/tokens'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'

// Cost per company creation (screen module)
const TOKENS_PER_COMPANY = 35

export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const name = ref('')
  const website = ref('')
  const organizationId = ref('')

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (company: { name: string; website: string }) => createCompany(company),
    onSuccess: (newCompany: Company) => {
      // Get current recent companies from cache (limit 10)
      const currentRecent = queryCache.getQueryData<Company[]>(COMPANY_QUERY_KEYS.recent(10)) || []

      // Prepend new company to the top (most recent first) and limit to 10 items
      const updatedRecent = [newCompany, ...currentRecent].slice(0, 10)

      // Update cache with new company at the top
      queryCache.setQueryData(COMPANY_QUERY_KEYS.recent(10), updatedRecent)

      // Optimistically update token balance cache (reduce by 35)
      if (organizationId.value) {
        const balanceKey = ORGANIZATION_TOKEN_KEYS.balance(organizationId.value)
        const currentBalance = queryCache.getQueryData<TokenBalanceResponse>(balanceKey)

        if (currentBalance) {
          queryCache.setQueryData(balanceKey, {
            ...currentBalance,
            balance: Math.max(0, currentBalance.balance - TOKENS_PER_COMPANY),
          })
        }
      }
    },
  })

  return {
    ...mutation,
    createCompany: () => {
      if (!name.value || !website.value) {
        throw new Error('Company name and website are required')
      }
      return mutateAsync({
        name: name.value,
        website: website.value,
      })
    },
    name,
    website,
    organizationId,
    mutate,
    mutateAsync,
  }
})

/**
 * Archive a company (soft delete).
 * Invalidates folder caches on success to refetch fresh data.
 */
export const useArchiveCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ companyId }: { companyId: string; companyName: string }) =>
      deleteCompany(companyId),

    onError: (_error, { companyName }) => {
      toast.error(
        t('company.archive.error', 'Failed to archive company "{name}". Please try again.', {
          name: companyName,
        }),
      )
    },

    onSuccess: (_data, { companyName }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(
        t('company.archive.success', 'Company "{name}" has been archived successfully', {
          name: companyName,
        }),
      )
    },
  })

  return {
    ...mutation,
    archiveCompany: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Permanently delete a company.
 * Invalidates folder caches on success to refetch fresh data.
 */
export const useDeleteCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ companyId }: { companyId: string; companyName: string }) =>
      deleteCompany(companyId),

    onError: (_error, { companyName }) => {
      toast.error(
        t('company.delete.error', 'Failed to delete company "{name}". Please try again.', {
          name: companyName,
        }),
      )
    },

    onSuccess: (_data, { companyName }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(
        t('company.delete.success', 'Company "{name}" has been deleted successfully', {
          name: companyName,
        }),
      )
    },
  })

  return {
    ...mutation,
    deleteCompany: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Restore an archived company.
 * Invalidates folder caches on success to refetch fresh data.
 */
export const useRestoreCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ companyId }: { companyId: string; companyName: string }) =>
      restoreCompany(companyId),

    onError: (_error, { companyName }) => {
      toast.error(
        t('company.restore.error', 'Failed to restore company "{name}". Please try again.', {
          name: companyName,
        }),
      )
    },

    onSuccess: (_data, { companyName }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(
        t('company.restore.success', 'Company "{name}" has been restored successfully', {
          name: companyName,
        }),
      )
    },
  })

  return {
    ...mutation,
    restoreCompany: mutateAsync,
    mutate,
    mutateAsync,
  }
})

/**
 * Refresh company data by re-running all tasks.
 * Invalidates company caches on success to refetch fresh data.
 */
export const useRefreshCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const { t } = useI18n()

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: ({ companyId }: { companyId: string; companyName: string }) =>
      refreshCompany(companyId),

    onError: (_error, { companyName }) => {
      toast.error(t('company.refresh.error', { name: companyName }))
    },

    onSuccess: (_data, { companyName, companyId }) => {
      // Invalidate company cache to refetch updated tasks
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
      // Invalidate tasks cache for this company
      queryCache.invalidateQueries({ key: TASK_QUERY_KEYS.byCompanyId(companyId) })
      // Invalidate token balance since 35 tokens were consumed
      queryCache.invalidateQueries({ key: ORGANIZATION_TOKEN_KEYS.root })
      toast.success(t('company.refresh.success', { name: companyName }))
    },
  })

  return { ...mutation, refreshCompany: mutateAsync, mutate, mutateAsync }
})
