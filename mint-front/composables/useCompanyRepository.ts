import { useApiService } from './useApiService'
import type { CompanyCreate, CompanyResponse, CompanyUpdate, TaskCreate, TaskResponse } from '~/types/company'

const companiesUrl = '/api/companies'

export const useCompanyRepository = () => {
  const api = useApiService()

  return {
    /**
     * Get all companies
     */
    getCompanies: () => {
      return api.get<CompanyResponse[]>(companiesUrl)
    },

    /**
     * Get a company by ID
     */
    getCompany: (id: number) => {
      return api.get<CompanyResponse>(`${companiesUrl}/${id}`)
    },

    /**
     * Get a company by ID (alias for consistency with store)
     */
    getCompanyById: (id: number) => {
      return api.get<CompanyResponse>(`${companiesUrl}/${id}`)
    },

    /**
     * Get a company by name
     */
    getCompanyByName: (name: string) => {
      return api.get<CompanyResponse>(`${companiesUrl}/by-name/${name}`)
    },

    /**
     * Create a new company
     */
    createCompany: (company: CompanyCreate) => {
      return api.post<CompanyResponse>(companiesUrl, company)
    },

    /**
     * Update a company
     */
    updateCompany: (id: number, company: CompanyUpdate) => {
      return api.put<CompanyResponse>(`${companiesUrl}/${id}`, company)
    },

    /**
     * Delete a company
     */
    deleteCompany: (id: number) => {
      return api.delete(`${companiesUrl}/${id}`)
    },

    /**
     * Create a task
     */
    createTask: (task: TaskCreate) => {
      return api.post<TaskResponse>('/api/tasks', task)
    },

    /**
     * Restart a task
     */
    restartTask: (taskId: number) => {
      return api.post<TaskResponse>(`/api/tasks/${taskId}/restart`)
    },

    /**
     * Get company tasks
     */
    getCompanyTasks: (companyId: number) => {
      return api.get<TaskResponse[]>(`/api/tasks/company/${companyId}`)
    }
  }
}
