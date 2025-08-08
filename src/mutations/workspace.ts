import { ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createWorkspace, updateWorkspace, deleteWorkspace, pickWorkspace } from '@/api/workspace'
import type { WorkspaceCreate, WorkspaceUpdate } from '@/types/workspace'
import { WORKSPACE_QUERY_KEYS } from '@/queries/workspace'
import { toast } from '@/utils/toast'

// Create workspace mutation
export const useCreateWorkspace = defineMutation(() => {
  const name = ref('')
  const description = ref('')
  const slug = ref('')

  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (workspace: WorkspaceCreate) => createWorkspace(workspace),
    onSuccess: (newWorkspace) => {
      // Show success notification
      toast.success(`Workspace "${newWorkspace.name}" created successfully!`)

      // Invalidate workspaces list to refresh data
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminAll })

      // Reset form
      name.value = ''
      description.value = ''
      slug.value = ''
    },
    onError: (error: any) => {
      // Show error notification
      const errorMessage = error?.message || 'Failed to create workspace'
      toast.error(errorMessage)
    },
  })

  // Auto-generate slug from name
  const generateSlug = () => {
    slug.value = name.value
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim()
  }

  const createWorkspaceWithForm = () => {
    if (!name.value.trim()) {
      throw new Error('Workspace name is required')
    }

    if (!slug.value.trim()) {
      generateSlug()
    }

    return mutate({
      name: name.value.trim(),
      description: description.value.trim() || undefined,
      slug: slug.value.trim(),
    })
  }

  return {
    ...mutation,
    name,
    description,
    slug,
    generateSlug,
    createWorkspace: createWorkspaceWithForm,
    mutate,
  }
})

// Update workspace mutation
export const useUpdateWorkspace = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ id, workspace }: { id: number; workspace: WorkspaceUpdate }) =>
      updateWorkspace(id, workspace),
    onSuccess: (updatedWorkspace, { id }) => {
      // Show success notification
      toast.success(`Workspace "${updatedWorkspace.name}" updated successfully!`)

      // Invalidate specific workspace and all workspaces list
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminById(id) })
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminAll })
    },
    onError: (error: any) => {
      // Show error notification
      const errorMessage = error?.message || 'Failed to update workspace'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    updateWorkspace: mutate,
  }
})

// Delete workspace mutation
export const useDeleteWorkspace = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => deleteWorkspace(id),
    onSuccess: (_, id) => {
      // Show success notification
      toast.success('Workspace deleted successfully!')

      // Remove from cache and invalidate list
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminAll })
    },
    onError: (error: any) => {
      // Show error notification
      let errorMessage = 'Failed to delete workspace'

      // Handle specific error cases from backend
      if (error?.message?.includes('Cannot delete default workspace')) {
        errorMessage = 'Cannot delete the default workspace'
      } else if (error?.message?.includes('companies')) {
        errorMessage = 'Cannot delete workspace that contains companies'
      } else if (error?.message?.includes('members')) {
        errorMessage = 'Cannot delete workspace that has active members'
      } else if (error?.message) {
        errorMessage = error.message
      }

      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    deleteWorkspace: mutate,
  }
})

// Pick workspace mutation
export const usePickWorkspace = defineMutation(() => {
  const queryCache = useQueryCache()

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => pickWorkspace(id),
    onSuccess: (pickedWorkspace, id) => {
      // Show success notification
      toast.success(`Switched to workspace "${pickedWorkspace.name}"!`)

      // Invalidate current workspace queries to refresh data
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.current })
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.currentWithMembers })

      // Also invalidate admin workspace list to refresh current workspace indicator
      queryCache.invalidateQueries({ key: WORKSPACE_QUERY_KEYS.adminAll })
    },
    onError: (error: any) => {
      // Show error notification
      const errorMessage = error?.message || 'Failed to switch workspace'
      toast.error(errorMessage)
    },
  })

  return {
    ...mutation,
    pickWorkspace: mutate,
  }
})
