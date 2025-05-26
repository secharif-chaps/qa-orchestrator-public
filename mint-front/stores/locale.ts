import { defineStore } from 'pinia'

export const useLocaleStore = defineStore('locale', {
  state: () => ({
    currentLocale: 'en'
  }),

  persist: {
    storage: import.meta.client ? localStorage : null,
    paths: ['currentLocale']
  },

  actions: {
    setLocale(locale: string) {
      this.currentLocale = locale
    }
  }
}) 