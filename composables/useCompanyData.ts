// composables/useCompanyData.ts
import type { SourcedValue } from '~/types.global'

export function useCompanyData() {
  const route = useRoute()
  const companyStore = useCompanyStore()
  const companyName = computed(() => {
    return (route.params.id as string)?.toLocaleLowerCase() || ''
  })
  
  const company = computed(() => companyStore.getCompanyByName(companyName.value))
  const hasAnyData = computed(() => !!company.value)
  const isCompanyNew = ref(true)
  
  const hasPropertyBeenUpdated = (propertyPath: string) => {
    return companyStore.hasPropertyBeenUpdated(companyName.value, propertyPath)
  }
  
  // Helper function to extract values from SourcedValue fields
  const getSourcedValue = <T>(sourcedValue: SourcedValue<T> | undefined): T | undefined => {
    return sourcedValue?.value
  }
  
  // Helper function to extract sources from SourcedValue fields
  const getSourcedSource = <T>(sourcedValue: SourcedValue<T> | undefined): string | undefined => {
    return sourcedValue?.source
  }

   // Helper function to extract sources from SourcedValue fields
   const getSourcedSourceName = <T>(sourcedValue: SourcedValue<T> | undefined): string | undefined => {
    //remove http and https and trailing slash
    let name = sourcedValue?.source.replace(/^https?:\/\//, '').replace(/\/$/, '')
    // remove www. 
    name = name?.replace(/^www\./, '')
    // remove everything after the first slash
    name = name?.split('/')[0]

    return name
  }
  
  // Helper function to handle SourcedValue arrays
  const getSourcedArray = <T>(sourcedArray: SourcedValue<T>[] | undefined): T[] => {
    if (!sourcedArray) return []
    return sourcedArray.map(item => item.value)
  }
  
  // Helper function to get sources from SourcedValue arrays
  const getSourcedArraySources = <T>(sourcedArray: SourcedValue<T>[] | undefined): string[] => {
    if (!sourcedArray) return []
    return sourcedArray.map(item => item.source)
  }
  
  onMounted(() => {
    if (!company.value) {
      companyStore.initCompany(companyName.value)
    } else {
      isCompanyNew.value = false
    }
  })
  
  return {
    company,
    companyName,
    hasAnyData,
    isCompanyNew,
    hasPropertyBeenUpdated,
    getSourcedValue,
    getSourcedSourceName,
    getSourcedSource,
    getSourcedArray,
    getSourcedArraySources
  }
}