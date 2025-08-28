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
  is_favorite?: boolean
}

export interface FolderItem {
  id: string
  item_id: string
  item_type: 'company'
  position?: number
  created_at: string
  // Company metadata for preview
  name: string
  owner_username?: string
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
  owner_username: string
  workspace_id: number

  // Items contained in this folder (populated when getting folder details)
  items?: FolderItem[]
  // Item count for list view
  items_count?: number
}

export interface FolderItemAdd {
  item_id: string
  item_type: 'company'
  position?: number
}
