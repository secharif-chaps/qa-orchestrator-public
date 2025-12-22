import type { Company } from './company'

export interface FolderCreate {
  name: string
  color?: string
  icon?: string
  tags?: string[]
}

export interface FolderUpdate {
  name?: string
  color?: string
  icon?: string
  tags?: string[]
  // Note: is_favorite is managed via dedicated POST/DELETE /folders/{id}/favorite endpoints
}

export interface FolderItem {
  id: string
  item_id: string
  type: 'company'
  position?: number
  created_at: string
  // Company metadata for preview
  name: string
  owner?: string
  created_at_item: string
  website?: string
  showFallbackIcon?: boolean // For logo fallback state
}

export interface Folder {
  id: string
  name: string
  color?: string
  icon?: string
  tags?: string[]
  is_favorite: boolean
  is_deleted: boolean
  created_at: string
  updated_at: string
  owner: string
  organization_id: string

  // Sharing-related fields (added for private folders feature)
  owner_id: string // Keycloak user UUID of the folder owner
  owner_username: string // Username of the folder owner (for global view)
  is_owner: boolean // Whether the current user is the folder owner
  share_role?: ShareRole | null // Current user's share role (null if owner)

  // Items contained in this folder (populated when getting folder details)
  items?: FolderItem[]
  // Item count for list view
  items_count?: number
}

export interface FolderItemAdd {
  item_id: string
  type: 'company'
  position?: number
}

// Folder sharing types
export type ShareRole = 'reader' | 'writer'

export interface FolderShare {
  id: string
  folder_id: string
  user_id: string
  user_username: string
  role: ShareRole
  created_at: string
  // User permission info for UI (whether user can be assigned as writer)
  has_write_permission?: boolean
}

export interface FolderShareCreate {
  user_id: string
  user_username: string
  role: ShareRole
}

export interface FolderShareUpdate {
  role: ShareRole
}

// User search result for sharing modal
export interface ShareableUser {
  user_id: string
  username: string
  email?: string
  has_write_permission: boolean // Whether user has organization.write permission
}
