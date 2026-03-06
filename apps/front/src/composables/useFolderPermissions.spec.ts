import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useFolderPermissions } from './useFolderPermissions'
import type { Folder } from '@/types/folder'

// Mock the auth store
const mockHasPermission = vi.fn()

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    hasPermission: mockHasPermission,
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
    owner: 'testuser',
    organization_id: 'org-1',
    owner_id: 'user-123',
    is_owner: false,
    share_role: null,
    items: [],
    items_count: 0,
    ...overrides,
  }
}

describe('useFolderPermissions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('canCreateFolder', () => {
    it('returns true when user has organization.write permission', () => {
      mockHasPermission.mockImplementation((permission: string) => {
        return permission === 'organization.write'
      })

      const { canCreateFolder } = useFolderPermissions()

      expect(canCreateFolder.value).toBe(true)
      expect(mockHasPermission).toHaveBeenCalledWith('organization.write')
    })

    it('returns false when user lacks organization.write permission', () => {
      mockHasPermission.mockReturnValue(false)

      const { canCreateFolder } = useFolderPermissions()

      expect(canCreateFolder.value).toBe(false)
    })
  })

  describe('canEditFolder', () => {
    it('returns true when user is folder owner', () => {
      const folder = ref(createMockFolder({ is_owner: true }))

      const { canEditFolder } = useFolderPermissions(folder)

      expect(canEditFolder.value).toBe(true)
    })

    it('returns false when user is not folder owner', () => {
      const folder = ref(createMockFolder({ is_owner: false, share_role: 'writer' }))

      const { canEditFolder } = useFolderPermissions(folder)

      expect(canEditFolder.value).toBe(false)
    })

    it('returns false when folder is null', () => {
      const folder = ref<Folder | null>(null)

      const { canEditFolder } = useFolderPermissions(folder)

      expect(canEditFolder.value).toBe(false)
    })
  })

  describe('canDeleteFolder', () => {
    it('returns true when user is folder owner', () => {
      const folder = ref(createMockFolder({ is_owner: true }))

      const { canDeleteFolder } = useFolderPermissions(folder)

      expect(canDeleteFolder.value).toBe(true)
    })

    it('returns false when user is writer but not owner', () => {
      const folder = ref(createMockFolder({ is_owner: false, share_role: 'writer' }))

      const { canDeleteFolder } = useFolderPermissions(folder)

      expect(canDeleteFolder.value).toBe(false)
    })

    it('returns false when user is reader', () => {
      const folder = ref(createMockFolder({ is_owner: false, share_role: 'reader' }))

      const { canDeleteFolder } = useFolderPermissions(folder)

      expect(canDeleteFolder.value).toBe(false)
    })
  })

  describe('canManageSharing', () => {
    it('returns true when user is folder owner', () => {
      const folder = ref(createMockFolder({ is_owner: true }))

      const { canManageSharing } = useFolderPermissions(folder)

      expect(canManageSharing.value).toBe(true)
    })

    it('returns false when user is not owner (even with writer role)', () => {
      const folder = ref(createMockFolder({ is_owner: false, share_role: 'writer' }))

      const { canManageSharing } = useFolderPermissions(folder)

      expect(canManageSharing.value).toBe(false)
    })
  })

  describe('canCreateItems', () => {
    it('returns true when owner has company.create permission', () => {
      mockHasPermission.mockImplementation((permission: string) => {
        return permission === 'company.create'
      })

      const folder = ref(createMockFolder({ is_owner: true }))

      const { canCreateItems } = useFolderPermissions(folder)

      expect(canCreateItems.value).toBe(true)
      expect(mockHasPermission).toHaveBeenCalledWith('company.create')
    })

    it('returns true when writer has company.create permission', () => {
      mockHasPermission.mockImplementation((permission: string) => {
        return permission === 'company.create'
      })

      const folder = ref(createMockFolder({ is_owner: false, share_role: 'writer' }))

      const { canCreateItems } = useFolderPermissions(folder)

      expect(canCreateItems.value).toBe(true)
    })

    it('returns false when reader (even with company.create permission)', () => {
      mockHasPermission.mockImplementation((permission: string) => {
        return permission === 'company.create'
      })

      const folder = ref(createMockFolder({ is_owner: false, share_role: 'reader' }))

      const { canCreateItems } = useFolderPermissions(folder)

      expect(canCreateItems.value).toBe(false)
    })

    it('returns false when owner lacks company.create permission', () => {
      mockHasPermission.mockReturnValue(false)

      const folder = ref(createMockFolder({ is_owner: true }))

      const { canCreateItems } = useFolderPermissions(folder)

      expect(canCreateItems.value).toBe(false)
    })
  })

  describe('isSharedWithMe', () => {
    it('returns true when user is not owner but has share role', () => {
      const folder = ref(createMockFolder({ is_owner: false, share_role: 'reader' }))

      const { isSharedWithMe } = useFolderPermissions(folder)

      expect(isSharedWithMe.value).toBe(true)
    })

    it('returns false when user is owner', () => {
      const folder = ref(createMockFolder({ is_owner: true }))

      const { isSharedWithMe } = useFolderPermissions(folder)

      expect(isSharedWithMe.value).toBe(false)
    })
  })
})
