import { type Company, type CompanyCreate } from '@/types/company'
import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'

export const getCompanyById = async (companyId: string) => {
  const response = await apiClient.get<Company>(`/companies/${companyId}`)
  return response
}

export const getCompanies = async (filters: { page: number; size: number; name: string }) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })

  if (filters.name) {
    params.append('name', filters.name)
  }

  const response = await apiClient.get<PaginatedResponse<Company>>(
    `/companies?${params.toString()}`,
  )
  return response
}

export const createCompany = async (company: { name: string; website: string }) => {
  const response = await apiClient.post<Company>('/companies', company)
  return response
}

// Export as a single API object for backward compatibility
export const companiesApi = {
  getCompanyById,
  getCompanies,
  createCompany,
}
