/**
 * Organization types for Keycloak Organizations integration.
 *
 * Organizations are managed in Keycloak, not in the application database.
 * These types represent organization context extracted from JWT tokens.
 */

/**
 * Organization context from JWT token (user's current organization)
 */
export interface OrganizationResponse {
  /** Keycloak organization UUID */
  id: string
  /** Organization name */
  name: string
  /** Current user's Keycloak UUID */
  user_id: string
  /** Current user's username */
  username: string
}

/**
 * Organization admin view (for admin endpoints)
 * Fetched from Keycloak Admin API, not from JWT
 */
export interface OrganizationAdminResponse {
  /** Keycloak organization UUID */
  id: string
  /** Organization name */
  name: string
  /** Organization description */
  description: string | null
  /** Organization slug/alias */
  slug: string
  /** Creation timestamp (may be null if not available from Keycloak) */
  created_at: string | null
  /** Update timestamp (may be null if not available from Keycloak) */
  updated_at: string | null
  /** Number of members in organization */
  member_count: number
}

/**
 * Pagination response wrapper for organizations
 */
export interface PaginatedOrganizationsResponse {
  data: OrganizationAdminResponse[]
  meta: {
    page: number
    per_page: number
    total: number
    total_pages: number
  }
}

/**
 * Query parameters for organization list
 */
export interface OrganizationQueryParams {
  page?: number
  limit?: number
  sort?: 'name'
  order?: 'asc' | 'desc'
  search?: string
}

/**
 * Activity item for organization activity feed
 */
export interface Activity {
  /** Activity type: 'company' or 'folder' */
  type: 'company' | 'folder'
  /** Name of the created item */
  name: string
  /** Username of the creator */
  owner: string
  /** When the item was created */
  created_at: string
  /** ID of the item (company or folder) */
  id: string
  /** Folder ID (for companies, the folder containing them) */
  folder_id?: string
}

/**
 * Organization member response (for user management)
 * Members are managed in Keycloak, not the application database
 */
export interface OrganizationMemberResponse {
  /** Member ID (Keycloak user ID) */
  id: string
  /** Organization ID (Keycloak organization ID) */
  organization_id: string
  /** User ID (Keycloak user UUID) */
  user_id: string
  /** Username */
  username: string
  /** Email address */
  email: string
  /** Member status */
  status: 'ACTIVE' | 'REVOKED'
  /** When membership was created */
  created_at: string
  /** When membership was last updated */
  updated_at: string
}
