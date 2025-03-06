// composables/useCompanyData.ts
export function useCompanyData() {
  const route = useRoute()
  const companyStore = useCompanyStore()
  const companyName = computed(() => route.params.id as string)
  
  const company = computed(() => companyStore.getCompanyByName(companyName.value))
  const hasAnyData = computed(() => !!company.value)
  const isCompanyNew = ref(true)
  
  const hasPropertyBeenUpdated = (propertyPath: string) => {
    return companyStore.hasPropertyBeenUpdated(companyName.value, propertyPath)
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
    hasPropertyBeenUpdated
  }
}