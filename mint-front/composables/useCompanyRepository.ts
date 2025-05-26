import type { CompanyCreate, CompanyResponse, CompanyUpdate } from '~/types/company'
import { useApiService } from './useApiService'

export const useCompanyRepository = () => {
  const api = useApiService()
  const baseUrl = '/companies'

  return {
    /**
     * Get all companies
     */
    getCompanies: () => {
      return api.get<CompanyResponse[]>(`${baseUrl}/`)
    },

    /**
     * Get a company by ID
     */
    getCompanyById: (companyId: number) => {
      return api.get<CompanyResponse>(`${baseUrl}/${companyId}`)
    },

    /**
     * Get a company by name
     */
    getCompanyByName: (name: string) => {
      return api.get<CompanyResponse>(`${baseUrl}/by-name/${name}`)
    },

    /**
     * Create a new company
     */
    createCompany: (company: CompanyCreate) => {
      return api.post<CompanyResponse>(`${baseUrl}/`, company)
    },

    /**
     * Update a company
     */
    updateCompany: (companyId: number, update: CompanyUpdate) => {
      return api.put<CompanyResponse>(`${baseUrl}/${companyId}`, update)
    },

    /**
     * Delete a company
     */
    deleteCompany: (companyId: number) => {
      return api.delete(`${baseUrl}/${companyId}`)
    },

    /**
     * Search for company data (triggers n8n workflows)
     */
    searchCompany: (company: CompanyCreate) => {
      return api.post(`${baseUrl}/search`, company)
    },

    /**
     * Start a specific query for company data
     */
    startQuery: (companyId: number, queryType: string) => {
      return api.post(`${baseUrl}/${companyId}/query/${queryType}`, {})
    }
  }
}
