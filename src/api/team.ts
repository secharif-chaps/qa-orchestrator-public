import { apiClient } from './client'
import { useAuthStore } from '@/stores/auth'
import type {
  OrganizationUser,
  OrganizationUserResponse,
  OrganizationUserQueryParams,
  CreateOrganizationUserRequest,
  UpdateOrganizationUserRequest,
} from '@/types/team'

const getOrganizationId = (): string => {
  const authStore = useAuthStore()
  const orgId = authStore.organizationId
  if (!orgId) {
    throw new Error('No organization context available. User must be logged in.')
  }
  return orgId
}

export const getOrganizationUsers = async (
  params?: OrganizationUserQueryParams,
): Promise<OrganizationUserResponse> => {
  const organizationId = getOrganizationId()
  const searchParams = new URLSearchParams()

  if (params?.page) searchParams.set('page', params.page.toString())
  if (params?.limit) searchParams.set('limit', params.limit.toString())
  if (params?.sort) searchParams.set('sort', params.sort)
  if (params?.order) searchParams.set('order', params.order)
  if (params?.search) searchParams.set('search', params.search)
  if (params?.status) searchParams.set('status', params.status)

  const queryString = searchParams.toString()
  const url = `/organizations/${organizationId}/users${queryString ? `?${queryString}` : ''}`

  const response = await apiClient.get<OrganizationUserResponse>(url)
  return response
}

export const createOrganizationUser = async (
  user: CreateOrganizationUserRequest,
): Promise<OrganizationUser> => {
  const organizationId = getOrganizationId()
  const response = await apiClient.post<OrganizationUser>(
    `/organizations/${organizationId}/users`,
    user,
  )
  return response
}

export const updateOrganizationUser = async (
  userId: number,
  updates: UpdateOrganizationUserRequest,
): Promise<OrganizationUser> => {
  const organizationId = getOrganizationId()
  const response = await apiClient.patch<OrganizationUser>(
    `/organizations/${organizationId}/users/${userId}`,
    updates,
  )
  return response
}

export const getOrganizationUser = async (userId: number): Promise<OrganizationUser> => {
  const organizationId = getOrganizationId()
  const response = await apiClient.get<OrganizationUser>(
    `/organizations/${organizationId}/users/${userId}`,
  )
  return response
}

export const disableOrganizationUser = async (userId: number): Promise<OrganizationUser> => {
  return updateOrganizationUser(userId, { is_disabled: true })
}

export const enableOrganizationUser = async (userId: number): Promise<OrganizationUser> => {
  return updateOrganizationUser(userId, { is_disabled: false })
}
