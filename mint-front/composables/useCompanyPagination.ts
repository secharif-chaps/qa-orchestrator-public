import type { Company, PaginationMeta } from '~/types/company'

export const useCompanyPagination = () => {
  const companyStore = useCompanyStore()
  const { getAccessToken } = useAuth()

  const fetchCompanies = async () => {
    const accessToken = await getAccessToken()
    
    const { data, status, error, refresh } = await useFetch(
      '/api/companies',
      {
        key: companyStore.pageCacheKey,
        watch: [() => companyStore.paginationQuery],
        default: () => ({ data: [], meta: null }),
        query: companyStore.paginationQuery,
        baseURL: useRuntimeConfig().public.backendApi,
        headers: {
          Authorization: `Bearer ${accessToken}`
        },
        transform: (data: any) => ({
          data: data.data as Company[],
          meta: data.meta as PaginationMeta
        })
      }
    )

    // Update store with fetched data
    watch(data, (newData) => {
      if (newData) {
        companyStore.companies = newData.data
        companyStore.paginationMeta = newData.meta
      }
    }, { immediate: true })

    return { data, status, error, refresh }
  }

  const clearCacheAndRefresh = async () => {
    companyStore.clearCache()
    // The watch on paginationQuery will automatically trigger a refresh
  }

  return {
    fetchCompanies,
    clearCacheAndRefresh
  }
}