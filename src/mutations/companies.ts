import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createCompany } from '@/api/companies'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'
import { ORGANIZATION_TOKEN_KEYS } from '@/queries/tokens'
import type { Company } from '@/types/company'
import type { TokenBalanceResponse } from '@/types/tokens'

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
      const currentRecent =
        queryCache.getQueryData<Company[]>(COMPANY_QUERY_KEYS.recent(10)) || []

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