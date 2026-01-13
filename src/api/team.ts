import { apiClient } from './client'
import type { TeamMember, UpdateTeamMemberPermissions, TeamMemberPasswordReset } from '@/types/team'

/**
 * List all team members in the user's organization
 */
export const getTeamMembers = async (search?: string) => {
  const params = new URLSearchParams()
  if (search) {
    params.append('search', search)
  }

  const url = params.toString() ? `/team/members?${params}` : '/team/members'
  return apiClient.get<TeamMember[]>(url)
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
 * Reset team member password
 */
export const resetMemberPassword = async (userId: string) => {
  return apiClient.post<TeamMemberPasswordReset>(`/team/members/${userId}/reset-password`)
}
