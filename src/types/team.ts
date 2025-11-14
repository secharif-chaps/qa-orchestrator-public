export interface OrganizationUser {
  id: number
  email: string
  username: string
  first_name: string
  last_name: string
  created_at: string
  updated_at: string
  is_disabled: boolean
  permissions: string[]
  created_by: number | null
}

export interface OrganizationUserListItem extends OrganizationUser {
  display_name: string
}

export interface OrganizationUserQueryParams {
  page: number
  limit: number
  search: string
  sort: 'name' | 'email' | 'created_at' | 'username'
  order: 'asc' | 'desc'
  status: 'active' | 'disabled' | 'all'
}

export interface OrganizationUserResponse {
  data: OrganizationUser[]
  pagination: {
    page: number
    limit: number
    total: number
    totalPages: number
    hasNext: boolean
    hasPrev: boolean
  }
}

export interface CreateOrganizationUserRequest {
  email: string
  username: string
  password: string
  first_name: string
  last_name: string
  permissions?: string[]
}

export interface UpdateOrganizationUserRequest {
  is_disabled?: boolean
  permissions?: string[]
  first_name?: string
  last_name?: string
}