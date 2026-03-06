import type { AdminUserListItem, AdminUserListResponse } from '@/types/admin-user'
import type {
  Activity,
  OrganizationAdminResponse,
  OrganizationQueryParams,
  OrganizationResponse,
  PaginatedOrganizationsResponse,
} from '@/types/organization'
import type { PermissionTier } from '@/types/team'
import { apiClient } from './client'

// Backend organization member response (raw Keycloak format)
interface OrganizationMemberRaw {
  id: string
  username: string
  email: string
  firstName?: string
  lastName?: string
  enabled: boolean
  emailVerified?: boolean
  createdTimestamp?: number
  permission_tier: PermissionTier | null
}

interface OrganizationMembersRawResponse {
  data: OrganizationMemberRaw[]
  meta: {
    total: number
    page: number
    per_page: number
    last_page: number
  }
}

// Admin organization management endpoints
// Organizations are managed in Keycloak, fetched via admin API
export const getAllOrganizations = async (
  params: OrganizationQueryParams = {},
): Promise<PaginatedOrganizationsResponse> => {
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

// Organization members (for admin org detail page)
export interface OrganizationMembersParams {
  organizationId: string
  organizationName: string
  page: number
  limit: number
  search?: string
}

export const getOrganizationMembers = async (
  params: OrganizationMembersParams,
): Promise<AdminUserListResponse> => {
  const searchParams = new URLSearchParams()
  searchParams.set('page', params.page.toString())
  searchParams.set('limit', params.limit.toString())
  if (params.search) searchParams.set('search', params.search)

  const response = await apiClient.get<OrganizationMembersRawResponse>(
    `/organizations/${params.organizationId}/users?${searchParams}`,
  )

  const responseData = response.data ?? []

  // Map raw Keycloak format to AdminUserListItem format
  const mappedData: AdminUserListItem[] = responseData.map((member) => ({
    user_id: member.id,
    username: member.username,
    email: member.email,
    first_name: member.firstName ?? null,
    last_name: member.lastName ?? null,
    status: member.enabled ? 'active' : 'revoked',
    created_at: member.createdTimestamp
      ? new Date(member.createdTimestamp).toISOString()
      : new Date().toISOString(),
    permission_tier: member.permission_tier,
    organization_id: params.organizationId,
    organization_name: params.organizationName,
  }))

  const meta = response.meta ?? {
    total: mappedData.length,
    page: params.page,
    per_page: params.limit,
    last_page: Math.ceil(mappedData.length / params.limit) || 1,
  }

  return {
    data: mappedData,
    pagination: {
      total: meta.total,
      page: meta.page,
      per_page: meta.per_page,
      last_page: meta.last_page,
    },
  }
}
