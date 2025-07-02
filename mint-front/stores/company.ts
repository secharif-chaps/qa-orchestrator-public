import { defineStore } from 'pinia'
import { useCompanyRepository } from '~/composables/useCompanyRepository'
import type { CompanyCreate, CompanyResponse, CompanyUpdate, TaskCreate, TaskResponse } from '~/types/company'

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

    async getCompanyById(id: number) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        this.currentCompany = await repository.getCompanyById(id)
        return this.currentCompany
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to fetch company with ID ${id}:`, err)
        throw err
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
      const { getCurrentUsername } = useAuth()
      this.loading = true
      this.error = null

      try {
        // Set the owner_username to current user
        const username = await getCurrentUsername()
        const companyWithOwner = { ...company, owner_username: username }
        
        const newCompany = await repository.createCompany(companyWithOwner)
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
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to delete company with ID ${id}:`, err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async createTask(task: TaskCreate) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        const newTask = await repository.createTask(task)
        
        // Update current company's tasks if it's the one we're working with
        if (this.currentCompany && this.currentCompany.id === task.company_id) {
          const taskIndex = this.currentCompany.tasks.findIndex(t => t.id === newTask.id)
          if (taskIndex !== -1) {
            this.currentCompany.tasks[taskIndex] = newTask
          } else {
            this.currentCompany.tasks.push(newTask)
          }
        }
        
        // Start polling if not already polling
        if (!this.pollingInterval) {
          this.startPolling(task.company_id)
        }
        
        return newTask
      } catch (err) {
        this.error = (err as Error).message
        console.error('Failed to create task:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async restartTask(taskId: number) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        const restartedTask = await repository.restartTask(taskId)
        
        // Update current company's tasks if it contains the restarted task
        if (this.currentCompany) {
          const taskIndex = this.currentCompany.tasks.findIndex(t => t.id === taskId)
          if (taskIndex !== -1) {
            this.currentCompany.tasks[taskIndex] = restartedTask
          }
        }
        
        return restartedTask
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to restart task with ID ${taskId}:`, err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchCompanyTasks(companyId: number) {
      const repository = useCompanyRepository()
      this.loading = true
      this.error = null

      try {
        const tasks = await repository.getCompanyTasks(companyId)
        
        // Update current company's tasks if it's the one we're working with
        if (this.currentCompany && this.currentCompany.id === companyId) {
          this.currentCompany.tasks = tasks
        }
        
        return tasks
      } catch (err) {
        this.error = (err as Error).message
        console.error(`Failed to fetch tasks for company ID ${companyId}:`, err)
        throw err
      } finally {
        this.loading = false
      }
    },

    startPolling(companyId: number) {
      // Clear any existing polling interval
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval)
      }

      // Start polling every 5 seconds
      this.pollingInterval = setInterval(async () => {
        try {
          const company = await this.getCompanyById(companyId)
          
          // Check if all tasks are completed
          const allTasksCompleted = company.tasks.every(
            task => task.status === 'succeeded' || task.status === 'error'
          )
          
          if (allTasksCompleted) {
            this.stopPolling()
          }
        } catch (err) {
          console.error('Error polling company status:', err)
          this.stopPolling()
        }
      }, 5000)
    },

    stopPolling() {
      if (this.pollingInterval) {
        clearInterval(this.pollingInterval)
        this.pollingInterval = null
      }
    },

    clearCurrentCompany() {
      this.currentCompany = null
      this.stopPolling() // Stop polling when clearing current company
    }
  },

  persist: true
})
