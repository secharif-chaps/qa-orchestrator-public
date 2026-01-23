import { apiClient } from './client'
import type {
  TeamMember,
  TeamMemberListResponse,
  TeamMemberPermissions,
  UpdateTeamMemberPermissions,
  ResetPasswordRequest,
  TeamMemberPasswordReset,
} from '@/types/team'

export interface TeamMembersParams {
  page: number
  limit: number
  search?: string
}

/**
 * List team members in the user's organization with pagination
 */
export const getTeamMembers = async (params: TeamMembersParams) => {
  const searchParams = new URLSearchParams()
  searchParams.set('page', params.page.toString())
  searchParams.set('limit', params.limit.toString())
  if (params.search) {
    searchParams.set('search', params.search)
  }

  return apiClient.get<TeamMemberListResponse>(`/team/members?${searchParams}`)
}

/**
 * Get permission tier for a specific team member (lazy-loaded)
 */
export const getMemberPermissions = async (userId: string) => {
  return apiClient.get<TeamMemberPermissions>(`/team/members/${userId}/permissions`)
}

/**
 * Update team member permission tier
 */
export const updateMemberPermissions = async (
  userId: string,
  data: UpdateTeamMemberPermissions,
) => {
  return apiClient.patch<TeamMember>(`/team/members/${userId}`, data)
}

/**
 * Reset team member password with a custom temporary password
 */
export const resetMemberPassword = async (userId: string, data: ResetPasswordRequest) => {
  return apiClient.post<TeamMemberPasswordReset>(`/team/members/${userId}/reset-password`, data)
}
