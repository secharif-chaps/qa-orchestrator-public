import { defineStore } from 'pinia'
import { useCompanyRepository } from '~/composables/useCompanyRepository'
import type { CompanyCreate, CompanyResponse, CompanyUpdate } from '~/types/company'

export const useCompanyStore = defineStore('company', {
  state: () => ({
    companies: [] as CompanyResponse[],
    currentCompany: null as CompanyResponse | null,
    loading: false,
    error: null as string | null,
    pollingInterval: null as NodeJS.Timeout | null
  }),

  actions: {
    async fetchCompanies() {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        this.companies = await repository.getCompanies()
      } catch (err) {
        this.error = (err as Error).message
        console.error('Failed to fetch companies:', err)
      } finally {
        this.loading = false
      }
    },

    async fetchCompanyById(id: number) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        this.currentCompany = await repository.getCompanyById(id)
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to fetch company with ID ${id}:`, err)
      } finally {
        this.loading = false
      }
    },

    async fetchCompanyByName(name: string) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        this.currentCompany = await repository.getCompanyByName(name)
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to fetch company with name ${name}:`, err)
      } finally {
        this.loading = false
      }
    },

    async createCompany(company: CompanyCreate) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        const newCompany = await repository.createCompany(company)
        this.companies.push(newCompany)
        this.currentCompany = newCompany
        return newCompany
      } catch (err) {
        this.error = (err as Error).message
        console.error('Failed to create company:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateCompany(id: number, updates: CompanyUpdate) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        const updatedCompany = await repository.updateCompany(id, updates)

        // Update the company in the companies array
        const index = this.companies.findIndex(c => c.id === id)
        if (index !== -1) {
          this.companies[index] = updatedCompany
        }

        // Update current company if it's the one being edited
        if (this.currentCompany && this.currentCompany.id === id) {
          this.currentCompany = updatedCompany
        }

        return updatedCompany
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to update company with ID ${id}:`, err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async deleteCompany(id: number) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        await repository.deleteCompany(id)

        // Remove the company from the companies array
        this.companies = this.companies.filter(c => c.id !== id)

        // Clear current company if it's the one being deleted
        if (this.currentCompany && this.currentCompany.id === id) {
          this.currentCompany = null
        }

        return true
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to delete company with ID ${id}:`, err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async searchCompany(company: CompanyCreate) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        return await repository.searchCompany(company)
      } catch (err) {
        this.error = (err as Error).message
        console.error('Failed to search company:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async startQuery(companyId: number, queryType: string) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      // Optimistically update the pending state
      if (this.currentCompany) {
        if (!this.currentCompany.pending_states) {
          this.currentCompany.pending_states = {}
        }
        this.currentCompany.pending_states[queryType] = {
          pending: true,
          error: null
        }
      }

      try {
        console.log('Starting query for company ID:', companyId, 'with type:', queryType)
        const result = await repository.startQuery(companyId, queryType)
        
        // Start polling if not already polling
        if (!this.pollingInterval) {
          this.startPolling(companyId)
        }
        
        return result
      } catch (err) {
        // Update error state if the query fails
        if (this.currentCompany?.pending_states) {
          this.currentCompany.pending_states[queryType] = {
            pending: false,
            error: (err as Error).message
          }
        }
        this.error = (err as Error).message
        console.error('Failed to start query:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    startPolling(companyId: number) {
      // Clear any existing interval
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval)
      }

      // Start new polling interval
      this.pollingInterval = setInterval(async () => {
        try {
          await this.fetchCompanyById(companyId)
          
          // Check if all pending states are resolved
          if (this.currentCompany && !this.hasPendingStates(this.currentCompany)) {
            this.stopPolling()
          }
        } catch (err) {
          console.error('Error during polling:', err)
          this.stopPolling()
        }
      }, 30000) // 30 seconds
    },

    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval)
        this.pollingInterval = null
      }
    },

    hasPendingStates(company: CompanyResponse): boolean {
      if (!company.pending_states) return false
      return Object.values(company.pending_states).some(state => state.pending)
    },

    clearCurrentCompany() {
      this.currentCompany = null
      this.stopPolling() // Stop polling when clearing current company
    }
  },

  persist: true
})
