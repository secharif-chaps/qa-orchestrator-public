import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { Folder, ShareRole } from '@/types/folder'

/**
 * Folder permissions composable
 * Provides granular permission checks for folder-related actions
 * based on ownership, share role, and global permissions.
 *
 * Permission Model:
 * - Owner: Full control (view, edit, delete, manage sharing, create/delete items)
 * - Writer: View folder/items, create items (if has company.create)
 * - Reader: View folder/items only (all actions disabled)
 *
 * Global permissions:
 * - organization.read: Can read folders and companies shared with them
 * - organization.write: Can create folders, create items, edit/share/delete owned folders
 * - company.create: Can add items (company screens) to folders
 */
export function useFolderPermissions(folder?: Ref<Folder | null | undefined>) {
  const authStore = useAuthStore()

  // Global permission to create new folders
  // Requires organization.write permission
  const canCreateFolder = computed(() => authStore.hasPermission('organization.write'))

  /**
   * Check if current user can edit the folder (name, color, icon, tags)
   * Only the folder owner can edit
   */
  const canEditFolder = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner === true
  })

  /**
   * Check if current user can delete the folder
   * Only the folder owner can delete
   */
  const canDeleteFolder = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner === true
  })

  /**
   * Check if current user can manage sharing (add/remove/update shares)
   * Only the folder owner can manage sharing
   */
  const canManageSharing = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner === true
  })

  /**
   * Check if current user can create items in the folder
   * Requires:
   * - Being owner OR having Writer share role
   * - Having company.create permission for adding company items
   */
  const canCreateItems = computed(() => {
    if (!folder?.value) return false

    // Check if user is owner or writer
    const isOwner = folder.value.is_owner === true
    const isWriter = folder.value.share_role === 'writer'

    if (!isOwner && !isWriter) return false

    // Additionally requires company.create permission
    return authStore.hasPermission('company.create')
  })

  /**
   * Check if current user can delete items from the folder
   * Only the folder owner can delete items
   */
  const canDeleteItems = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner === true
  })

  /**
   * Check if current user can move items from/to the folder
   * Requires being owner OR having Writer share role
   */
  const canMoveItems = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner === true || folder.value.share_role === 'writer'
  })

  /**
   * Get the current user's role in the folder context
   * Returns 'owner', 'writer', 'reader', or null if no access
   */
  const userFolderRole = computed((): 'owner' | ShareRole | null => {
    if (!folder?.value) return null
    if (folder.value.is_owner) return 'owner'
    return folder.value.share_role ?? null
  })

  /**
   * Check if current user has any access to the folder
   * (owner or shared)
   */
  const hasAnyAccess = computed(() => {
    if (!folder?.value) return false
    return folder.value.is_owner || folder.value.share_role != null
  })

  /**
   * Check if current user is a reader (view-only access)
   * Useful for showing read-only indicators
   */
  const isReadOnly = computed(() => {
    if (!folder?.value) return true
    if (folder.value.is_owner) return false
    return folder.value.share_role === 'reader'
  })

  /**
   * Check if the folder is shared with the current user (not owned)
   */
  const isSharedWithMe = computed(() => {
    if (!folder?.value) return false
    return !folder.value.is_owner && folder.value.share_role != null
  })

  return {
    // Global permissions
    canCreateFolder,

    // Folder-specific permissions
    canEditFolder,
    canDeleteFolder,
    canManageSharing,
    canCreateItems,
    canDeleteItems,
    canMoveItems,

    // Role information
    userFolderRole,
    hasAnyAccess,
    isReadOnly,
    isSharedWithMe,
  }
}

/**
 * Helper function to check folder permissions without a reactive folder reference
 * Useful for one-off permission checks with folder data
 */
export function checkFolderPermission(
  folder: Folder | null | undefined,
  permission: 'edit' | 'delete' | 'share' | 'createItems' | 'deleteItems',
): boolean {
  if (!folder) return false

  const authStore = useAuthStore()

  switch (permission) {
    case 'edit':
    case 'delete':
    case 'share':
    case 'deleteItems':
      // Owner-only permissions
      return folder.is_owner === true

    case 'createItems':
      // Owner or Writer + company.create permission
      const isOwnerOrWriter = folder.is_owner || folder.share_role === 'writer'
      return isOwnerOrWriter && authStore.hasPermission('company.create')

    default:
      return false
  }
}

/**
 * Get user's role in a folder for display purposes
 */
export function getUserFolderRoleLabel(folder: Folder | null | undefined): string {
  if (!folder) return ''
  if (folder.is_owner) return 'owner'
  return folder.share_role ?? ''
}
