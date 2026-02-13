import { defineStore } from 'pinia'
import { ref } from 'vue'
import { refDebounced } from '@vueuse/core'

export const useFoldersStore = defineStore('folders', () => {
  const page = ref(1)
  const size = ref(12) // Default to grid-friendly value (multiple of 3)
  const filterName = ref('')

  const debouncedName = refDebounced(filterName, 500)

  return { page, size, filterName, debouncedName }
})
