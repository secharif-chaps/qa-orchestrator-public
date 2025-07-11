import { useApiService } from './useApiService'
import type { 
  CompanyCreate, 
  CompanyUpdate,
  PaginationParams,
  PaginatedResponse,
  Company
} from '~/types/company'

import type { TaskCreate, TaskResponse } from '~/types/task'


const companiesUrl = '/api/companies'

export const useCompanyRepository = () => {
  const api = useApiService()

  return {
    /**
     * Get companies (legacy - returns all companies)
     */
    getCompanies: () => {
      return api.get<Company[]>(companiesUrl)
    },

    /**
     * Get paginated companies
     */
    getPaginatedCompanies: (params?: PaginationParams) => {
      const queryParams = new URLSearchParams()
      
      if (params?.page) queryParams.set('page', params.page.toString())
      if (params?.per_page) queryParams.set('per_page', params.per_page.toString())
      if (params?.sort) queryParams.set('sort', params.sort)
      if (params?.order) queryParams.set('order', params.order)
      
      const url = queryParams.toString() ? `${companiesUrl}?${queryParams}` : companiesUrl
      return api.get<PaginatedResponse<Company>>(url)
    },

    /**
     * Get a company by ID
     */
    getCompany: (id: number) => {
      return api.get<Company>(`${companiesUrl}/${id}`)
    },

    /**
     * Get a company by ID (alias for consistency with store)
     */
    getCompanyById: (id: number) => {
      return api.get<Company>(`${companiesUrl}/${id}`)
    },

    /**
     * Get a company by name
     */
    getCompanyByName: (name: string) => {
      return api.get<Company>(`${companiesUrl}/by-name/${name}`)
    },

    /**
     * Create a new company
     */
    createCompany: (company: CompanyCreate) => {
      return api.post<Company>(`${companiesUrl}`, company)
    },

    /**
     * Update a company
     */
    updateCompany: (id: number, company: CompanyUpdate) => {
      return api.put<Company>(`${companiesUrl}/${id}`, company)
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
      return api.post<TaskResponse>(`/api/tasks/${taskId}/restart`, {})
    },

    /**
     * Get company tasks
     */
    getCompanyTasks: (companyId: number) => {
      return api.get<TaskResponse[]>(`/api/tasks/company/${companyId}`)
    }
  }
}
