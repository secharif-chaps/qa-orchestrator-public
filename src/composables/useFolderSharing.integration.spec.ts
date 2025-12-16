import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useFolderPermissions } from './useFolderPermissions'
import type { Folder, ShareRole } from '@/types/folder'

/**
 * Integration tests for folder sharing and permission enforcement
 *
 * These tests verify the complete folder sharing workflow:
 * - Owner can share folders with other users
 * - Recipients can see shared folders
 * - Permission enforcement based on share role
 * - Admin permission management
 */

// Mock the auth store with configurable permissions
const mockUserPermissions = ref<string[]>([])

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    hasPermission: (permission: string) => mockUserPermissions.value.includes(permission),
    user: {
      profile: {
        sub: 'current-user-id',
        preferred_username: 'testuser',
      },
    },
  }),
}))

/**
 * Test factory to create mock folder data
 */
function createMockFolder(overrides: Partial<Folder> = {}): Folder {
  return {
    id: 'folder-1',
    name: 'Test Folder',
    color: 'blue',
    icon: 'fas fa-folder',
    tags: [],
    is_favorite: false,
    is_deleted: false,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
    owner: 'owneruser',
    organization_id: 'org-1',
    owner_id: 'owner-user-id',
    is_owner: false,
    share_role: null,
    items: [],
    items_count: 0,
    ...overrides,
  }
}

describe('Folder Sharing Integration Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockUserPermissions.value = []
  })

  describe('Full sharing flow: owner shares folder, recipient sees it', () => {
    it('owner can see their own folder and has full control', () => {
      // Setup: User is the owner with organization.write and screen.create permissions
      mockUserPermissions.value = ['organization.write', 'screen.create', 'organization.read']

      const ownedFolder = ref(createMockFolder({
        is_owner: true,
        share_role: null,
        owner_id: 'current-user-id',
      }))

      const {
        canEditFolder,
        canDeleteFolder,
        canManageSharing,
        canCreateItems,
        canDeleteItems,
        userFolderRole,
        isReadOnly,
      } = useFolderPermissions(ownedFolder)

      // Owner should have full control
      expect(canEditFolder.value).toBe(true)
      expect(canDeleteFolder.value).toBe(true)
      expect(canManageSharing.value).toBe(true)
      expect(canCreateItems.value).toBe(true)
      expect(canDeleteItems.value).toBe(true)
      expect(userFolderRole.value).toBe('owner')
      expect(isReadOnly.value).toBe(false)
    })

    it('shared user with writer role can see folder and create items', () => {
      // Setup: User is a writer with screen.create permission
      mockUserPermissions.value = ['organization.read', 'organization.write', 'screen.create']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'writer' as ShareRole,
        owner_id: 'different-owner-id',
      }))

      const {
        canEditFolder,
        canDeleteFolder,
        canManageSharing,
        canCreateItems,
        canDeleteItems,
        userFolderRole,
        isSharedWithMe,
        hasAnyAccess,
      } = useFolderPermissions(sharedFolder)

      // Writer can see and create, but not edit/delete folder or manage sharing
      expect(hasAnyAccess.value).toBe(true)
      expect(isSharedWithMe.value).toBe(true)
      expect(canCreateItems.value).toBe(true)
      expect(canEditFolder.value).toBe(false)
      expect(canDeleteFolder.value).toBe(false)
      expect(canManageSharing.value).toBe(false)
      expect(canDeleteItems.value).toBe(false)
      expect(userFolderRole.value).toBe('writer')
    })

    it('shared user with reader role can only view folder', () => {
      // Setup: User is a reader with only organization.read permission
      mockUserPermissions.value = ['organization.read']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'reader' as ShareRole,
        owner_id: 'different-owner-id',
      }))

      const {
        canEditFolder,
        canDeleteFolder,
        canManageSharing,
        canCreateItems,
        canDeleteItems,
        userFolderRole,
        isReadOnly,
        isSharedWithMe,
        hasAnyAccess,
      } = useFolderPermissions(sharedFolder)

      // Reader has view-only access
      expect(hasAnyAccess.value).toBe(true)
      expect(isSharedWithMe.value).toBe(true)
      expect(isReadOnly.value).toBe(true)
      expect(canEditFolder.value).toBe(false)
      expect(canDeleteFolder.value).toBe(false)
      expect(canManageSharing.value).toBe(false)
      expect(canCreateItems.value).toBe(false)
      expect(canDeleteItems.value).toBe(false)
      expect(userFolderRole.value).toBe('reader')
    })
  })

  describe('Permission enforcement: writer cannot delete folder', () => {
    it('writer cannot delete the folder even with write permissions', () => {
      // Setup: User has organization.write but is only a writer on this folder
      mockUserPermissions.value = ['organization.read', 'organization.write', 'screen.create']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'writer' as ShareRole,
      }))

      const { canDeleteFolder, canEditFolder } = useFolderPermissions(sharedFolder)

      // Even with global write permissions, folder delete is owner-only
      expect(canDeleteFolder.value).toBe(false)
      expect(canEditFolder.value).toBe(false)
    })

    it('writer cannot manage sharing on the folder', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write', 'screen.create']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'writer' as ShareRole,
      }))

      const { canManageSharing } = useFolderPermissions(sharedFolder)

      expect(canManageSharing.value).toBe(false)
    })

    it('writer cannot delete items from the folder', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write', 'screen.create']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'writer' as ShareRole,
      }))

      const { canDeleteItems } = useFolderPermissions(sharedFolder)

      expect(canDeleteItems.value).toBe(false)
    })
  })

  describe('Permission enforcement: reader cannot create items', () => {
    it('reader cannot create items even with screen.create permission', () => {
      // Edge case: reader with screen.create (unusual but possible)
      mockUserPermissions.value = ['organization.read', 'screen.create']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'reader' as ShareRole,
      }))

      const { canCreateItems } = useFolderPermissions(sharedFolder)

      // Reader role overrides global permission for this folder
      expect(canCreateItems.value).toBe(false)
    })

    it('reader cannot perform any write actions', () => {
      mockUserPermissions.value = ['organization.read']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'reader' as ShareRole,
      }))

      const {
        canEditFolder,
        canDeleteFolder,
        canManageSharing,
        canCreateItems,
        canDeleteItems,
        isReadOnly,
      } = useFolderPermissions(sharedFolder)

      expect(canEditFolder.value).toBe(false)
      expect(canDeleteFolder.value).toBe(false)
      expect(canManageSharing.value).toBe(false)
      expect(canCreateItems.value).toBe(false)
      expect(canDeleteItems.value).toBe(false)
      expect(isReadOnly.value).toBe(true)
    })
  })

  describe('Global permission checks', () => {
    it('user with organization.write can create new folders', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write']

      const { canCreateFolder } = useFolderPermissions()

      expect(canCreateFolder.value).toBe(true)
    })

    it('user with only organization.read cannot create folders', () => {
      mockUserPermissions.value = ['organization.read']

      const { canCreateFolder } = useFolderPermissions()

      expect(canCreateFolder.value).toBe(false)
    })

    it('writer needs screen.create to add items to folder', () => {
      // Writer without screen.create
      mockUserPermissions.value = ['organization.read', 'organization.write']

      const sharedFolder = ref(createMockFolder({
        is_owner: false,
        share_role: 'writer' as ShareRole,
      }))

      const { canCreateItems } = useFolderPermissions(sharedFolder)

      // Writer role allows, but module permission is missing
      expect(canCreateItems.value).toBe(false)
    })

    it('owner needs screen.create to add items to their own folder', () => {
      // Owner without screen.create
      mockUserPermissions.value = ['organization.read', 'organization.write']

      const ownedFolder = ref(createMockFolder({
        is_owner: true,
        share_role: null,
      }))

      const { canCreateItems } = useFolderPermissions(ownedFolder)

      // Even owner needs module permission
      expect(canCreateItems.value).toBe(false)
    })
  })

  describe('Edge cases', () => {
    it('handles null folder gracefully', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write']

      const folder = ref<Folder | null>(null)

      const {
        canEditFolder,
        canDeleteFolder,
        canManageSharing,
        canCreateItems,
        canDeleteItems,
        userFolderRole,
        hasAnyAccess,
      } = useFolderPermissions(folder)

      expect(canEditFolder.value).toBe(false)
      expect(canDeleteFolder.value).toBe(false)
      expect(canManageSharing.value).toBe(false)
      expect(canCreateItems.value).toBe(false)
      expect(canDeleteItems.value).toBe(false)
      expect(userFolderRole.value).toBe(null)
      expect(hasAnyAccess.value).toBe(false)
    })

    it('handles undefined folder gracefully', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write']

      const folder = ref<Folder | undefined>(undefined)

      const { canEditFolder, hasAnyAccess } = useFolderPermissions(folder)

      expect(canEditFolder.value).toBe(false)
      expect(hasAnyAccess.value).toBe(false)
    })

    it('user without share cannot access folder', () => {
      mockUserPermissions.value = ['organization.read', 'organization.write', 'screen.create']

      // User is not owner and has no share role (shouldn't happen in practice, but test defensive code)
      const folder = ref(createMockFolder({
        is_owner: false,
        share_role: null,
      }))

      const { hasAnyAccess, userFolderRole } = useFolderPermissions(folder)

      expect(hasAnyAccess.value).toBe(false)
      expect(userFolderRole.value).toBe(null)
    })
  })
})
