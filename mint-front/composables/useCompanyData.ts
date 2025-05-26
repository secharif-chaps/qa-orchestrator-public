// composables/useCompanyData.ts
import type { SourcedValue } from '~/types.global'
import { useCompanyAdapter } from './useCompanyAdapter'
import type { CompanyResponse } from '~/types/company'

export function useCompanyData() {
  const route = useRoute()
  const companyStore = useCompanyStore()
  const { apiToAppModel } = useCompanyAdapter()
  const repository = useCompanyRepository()

  // Get ID from route params - support both numeric and string IDs
  const companyParam = computed(() => {
    return route.params.id as string
  })

  // Try to parse as a number ID for new API
  const companyId = computed(() => {
    if (!companyParam.value) return null

    const parsed = parseInt(companyParam.value, 10)
    return !isNaN(parsed) ? parsed : null
  })

  // Use as a string name for old API
  const companyName = computed(() => {
    return companyParam.value?.toLocaleLowerCase() || ''
  })

  // Use Nuxt's useAsyncData for caching company data
  const { data: companyData, refresh: refreshCompanyData } = useAsyncData<CompanyResponse | null>(
    `company-${companyId.value}`,
    async () => {
      if (companyId.value) {
        return await repository.getCompanyById(companyId.value)
      } else if (companyName.value) {
        return await repository.getCompanyByName(companyName.value)
      }
      return null
    },
    {
      watch: [companyId, companyName],
      immediate: true
    }
  )

  // Function to fetch company data
  const fetchCompany = async () => {
    await refreshCompanyData()
  }

  // Try to get company data from API store first, then fall back to old store
  const company = computed(() => {
    // If we have a numeric ID, try to get from API store
    if (companyId.value) {
      if (companyData.value) {
        // If we have cached data, convert and return
        return apiToAppModel(companyData.value)
      }
    }

    // If we have the current company from API store but with a different ID
    if (
      companyStore.currentCompany &&
      companyStore.currentCompany.name.toLowerCase() === companyName.value
    ) {
      return apiToAppModel(companyStore.currentCompany)
    }

    // Not found in either store
    return null
  })

  const hasAnyData = computed(() => !!company.value)

  // Loading state that combines both stores
  const loading = computed(() => {
    return companyStore.loading || (companyId.value && !companyData.value)
  })

  // Error state that combines both stores
  const error = computed(() => {
    return companyStore.error
  })

  // Helper function to extract values from SourcedValue fields
  const getSourcedValue = <T>(sourcedValue: SourcedValue<T> | undefined | any): T | undefined => {
    if (!sourcedValue) return undefined
    return sourcedValue.value !== undefined ? sourcedValue.value : sourcedValue
  }

  // Helper function to extract sources from SourcedValue fields
  const getSourcedSource = <T>(sourcedValue: SourcedValue<T> | undefined): string | undefined => {
    return sourcedValue?.source
  }

  // Helper function to extract sources from SourcedValue fields
  const getSourcedSourceName = <T>(
    sourcedValue: SourcedValue<T> | undefined
  ): string | undefined => {
    if (!sourcedValue?.source) return undefined

    //remove http and https and trailing slash
    let name = sourcedValue.source.replace(/^https?:\/\//, '').replace(/\/$/, '')
    // remove www.
    name = name?.replace(/^www\./, '')
    // remove everything after the first slash
    name = name?.split('/')[0]

    return name
  }

  // Helper function to handle SourcedValue arrays
  const getSourcedArray = <T>(sourcedArray: SourcedValue<T>[] | undefined | any[]): T[] => {
    if (!sourcedArray) return []
    if (!Array.isArray(sourcedArray)) return []

    return sourcedArray.map(item => {
      if (item.value !== undefined) return item.value
      return item
    })
  }

  // Helper function to get sources from SourcedValue arrays
  const getSourcedArraySources = <T>(sourcedArray: SourcedValue<T>[] | undefined): string[] => {
    if (!sourcedArray) return []
    return sourcedArray.map(item => item.source)
  }

  return {
    company,
    companyId,
    companyName,
    hasAnyData,
    loading,
    error,
    fetchCompany,
    getSourcedValue,
    getSourcedSourceName,
    getSourcedSource,
    getSourcedArray,
    getSourcedArraySources
  }
}
