import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createCompany, deleteCompany, restoreCompany, refreshCompany } from '@/api/companies'
import type { CreateCompanyRequest } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import { ORGANIZATION_TOKEN_KEYS } from '@/queries/tokens'
import { FOLDER_QUERY_KEYS } from '@/queries/folders'
import { TASK_QUERY_KEYS } from '@/queries/tasks'
import type { Company } from '@/types/company'
import type { TokenBalanceResponse } from '@/types/tokens'
import { toast } from '@/utils/toast'
import { useI18n } from 'vue-i18n'
import { useTokenConfig } from '@/composables/useGlobalTokens'

export const useCreateCompany = defineMutation(() => {
  const queryCache = useQueryCache()
  const { tokensPerCompany } = useTokenConfig()
  const name = ref('')
  const website = ref('')
  const organizationId = ref('')

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: async (company: { name: string; website: string }) => {
      const request: CreateCompanyRequest = {
        name: company.name,
        website: company.website,
      }

      return createCompany(request)
    },
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
            balance: Math.max(0, currentBalance.balance - tokensPerCompany.value),
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

    onError: (_error: Error, { companyName }: { companyId: string; companyName: string }) => {
      toast.error(t('screen.company.delete.error', { name: companyName }))
    },

    onSuccess: (_data: void, { companyName }: { companyId: string; companyName: string }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(t('screen.company.delete.success', { name: companyName }))
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

    onError: (_error: Error, { companyName }: { companyId: string; companyName: string }) => {
      toast.error(t('screen.company.delete.error', { name: companyName }))
    },

    onSuccess: (_data: void, { companyName }: { companyId: string; companyName: string }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(t('screen.company.delete.success', { name: companyName }))
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

    onError: (_error: Error, { companyName }: { companyId: string; companyName: string }) => {
      toast.error(t('screen.company.restore.error', { name: companyName }))
    },

    onSuccess: (_data, { companyName }: { companyId: string; companyName: string }) => {
      // Invalidate folder caches to refetch fresh data
      queryCache.invalidateQueries({ key: FOLDER_QUERY_KEYS.root })

      toast.success(t('screen.company.restore.success', { name: companyName }))
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
      toast.error(t('screen.company.refresh.error', { name: companyName }))
    },

    onSuccess: (_data, { companyName, companyId }) => {
      // Invalidate company cache to refetch updated tasks
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.root })
      // Invalidate tasks cache for this company
      queryCache.invalidateQueries({ key: TASK_QUERY_KEYS.byCompanyId(companyId) })
      // Invalidate token balance since 35 tokens were consumed
      queryCache.invalidateQueries({ key: ORGANIZATION_TOKEN_KEYS.root })
      toast.success(t('screen.company.refresh.success', { name: companyName }))
    },
  })

  return { ...mutation, refreshCompany: mutateAsync, mutate, mutateAsync }
})
