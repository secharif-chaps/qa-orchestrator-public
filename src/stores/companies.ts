import { defineStore } from 'pinia'
import { ref } from 'vue'
import { refDebounced } from '@vueuse/core'

export const useCompaniesStore = defineStore('companies', () => {
  const page = ref(1)
  const size = ref(10)
  const filterName = ref('')

  const debouncedName = refDebounced(filterName, 500)

  return { page, size, filterName, debouncedName }
})
