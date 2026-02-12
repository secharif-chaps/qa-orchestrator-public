import { type Company } from '@/types/company'
import { apiClient } from './client'
import type { PaginatedResponse } from '@/types/pagination'
import type { ParsedCompany } from '@/utils/csvParser'

// Types for CSV API endpoints
export interface CSVValidationRequest {
  companies: ParsedCompany[]
}

export interface CSVValidationError {
  row_number: number
  field: string
  error: string
}

export interface CSVValidationResponse {
  valid_count: number
  error_count: number
  errors: CSVValidationError[]
  has_sufficient_tokens: boolean
  tokens_required: number
  tokens_available: number
}

export interface CSVImportRequest {
  companies: ParsedCompany[]
  skip_invalid: boolean
}

export interface CSVImportResult {
  row_number: number
  success: boolean
  company_id: number | null
  name: string
  error: string | null
}

export interface CSVImportResponse {
  total_rows: number
  successful: number
  failed: number
  results: CSVImportResult[]
}

export const getCompanyById = async (companyId: string, language?: string) => {
  const params = language ? `?language=${language}` : ''
  const response = await apiClient.get<Company>(`/companies/${companyId}${params}`)
  return response
}

export const getRecentCompanies = async (limit: number = 5) => {
  const params = new URLSearchParams({
    limit: limit.toString(),
  })
  const response = await apiClient.get<Company[]>(`/companies/recent?${params.toString()}`)
  return response
}

export const getCompanies = async (filters: {
  page: number
  size: number
  name: string
  archived?: boolean
  sort?: string
  order?: 'asc' | 'desc'
}) => {
  const params = new URLSearchParams({
    page: filters.page.toString(),
    size: filters.size.toString(),
  })

  if (filters.name) {
    params.append('name', filters.name)
  }

  if (filters.archived) {
    params.append('archived', 'true')
  }

  if (filters.sort) {
    params.append('sort', filters.sort)
  }

  if (filters.order) {
    params.append('order', filters.order)
  }

  const response = await apiClient.get<PaginatedResponse<Company>>(
    `/companies/?${params.toString()}`,
  )
  return response
}

export const createCompany = async (company: { name: string; website: string }) => {
  const response = await apiClient.post<Company>('/companies/', company)
  return response
}

export const deleteCompany = async (companyId: string) => {
  const response = await apiClient.delete(`/companies/${companyId}`)
  return response
}

export const restoreCompany = async (companyId: string) => {
  const response = await apiClient.post(`/companies/${companyId}/restore`, {})
  return response
}

export const refreshCompany = async (companyId: string) => {
  const response = await apiClient.post<Company>(`/companies/${companyId}/refresh`, {})
  return response
}

export const validateCSV = async (
  request: CSVValidationRequest,
): Promise<CSVValidationResponse> => {
  const response = await apiClient.post<CSVValidationResponse>('/companies/csv/validate', request)
  return response
}

export const importCSV = async (request: CSVImportRequest): Promise<CSVImportResponse> => {
  const response = await apiClient.post<CSVImportResponse>('/companies/csv/import', request)
  return response
}

// Export as a single API object for backward compatibility
export const companiesApi = {
  getCompanyById,
  getCompanies,
  createCompany,
  deleteCompany,
  restoreCompany,
  refreshCompany,
  validateCSV,
  importCSV,
}
