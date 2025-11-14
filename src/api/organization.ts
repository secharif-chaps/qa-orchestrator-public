import { apiClient } from './client'
import type {
  OrganizationResponse,
  OrganizationAdminResponse,
  PaginatedOrganizationsResponse,
  OrganizationQueryParams,
  Activity
} from '@/types/organization'

// Admin organization management endpoints
// Organizations are managed in Keycloak, fetched via admin API
export const getAllOrganizations = async (params: OrganizationQueryParams = {}): Promise<PaginatedOrganizationsResponse> => {
  const searchParams = new URLSearchParams()

  if (params.page) searchParams.set('page', params.page.toString())
  if (params.limit) searchParams.set('limit', params.limit.toString())
  if (params.sort) searchParams.set('sort', params.sort)
  if (params.order) searchParams.set('order', params.order)
  if (params.search) searchParams.set('search', params.search)

  const queryString = searchParams.toString()
  const url = `/organizations${queryString ? `?${queryString}` : ''}`

  const response = await apiClient.get<PaginatedOrganizationsResponse>(url)
  return response
}

export const getOrganizationById = async (id: string): Promise<OrganizationAdminResponse> => {
  const response = await apiClient.get<OrganizationAdminResponse>(`/organizations/${id}`)
  return response
}

// User's current organization endpoints
// DEPRECATED: Organization info now comes from JWT token
// Use useAuthStore().organizationId and useAuthStore().organizationName instead
export const getCurrentOrganization = async (): Promise<OrganizationResponse> => {
  const response = await apiClient.get<OrganizationResponse>('/current')
  return response
}

// Organization activities (recent companies/folders created by other users)
export const getOrganizationActivities = async (): Promise<Activity[]> => {
  const response = await apiClient.get<Activity[]>('/activities')
  return response
}
