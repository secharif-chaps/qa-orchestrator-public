import type { Workspace } from "~/types/workspace"

export const useWorkspace = () => {
  const config = useRuntimeConfig()
  const { getAccessToken } = useAuth()
  const api = useApiService()
  
  const fetchCurrentWorkspace = async () => {
    const token = await getAccessToken()
    
    const { data: workspace, pending, error, refresh } = await useFetch('/api/workspace/current', {
      key: 'current-workspace',
      baseURL: config.public.backendApi,
      headers: {
        Authorization: `Bearer ${token}`
      }
    })
    
    return {
      currentWorkspace: workspace,
      loading: pending,
      error,
      refresh
    }
  }
  
  const joinWorkspace = async () => {
    const token = await getAccessToken()
    
    const { data: member, pending, error } = await useFetch('/api/workspace/join', {
      method: 'POST',
      baseURL: config.public.backendApi,
      headers: {
        Authorization: `Bearer ${token}`
      }
    })
    
    return {
      member,
      loading: pending,
      error
    }
  }

  const fetchWorkspaces = async () => {
    try {
      const workspaces = await api.get<Workspace[]>('/api/workspace/admin/all')
      return {
        workspaces: ref(workspaces),
        status: ref('success'),
        error: ref(null),
        refresh: async () => {
          const newWorkspaces = await api.get<Workspace[]>('/api/workspace/admin/all')
          return newWorkspaces
        }
      }
    } catch (error) {
      return {
        workspaces: ref([]),
        status: ref('error'),
        error: ref(error),
        refresh: async () => {
          const newWorkspaces = await api.get<Workspace[]>('/api/workspace/admin/all')
          return newWorkspaces
        }
      }
    }
  }
  
  const createWorkspace = async (workspaceData: { name: string; slug: string; description?: string }) => {
    return api.post<Workspace>('/api/workspace/admin', workspaceData)
  }
  
  const updateWorkspace = async (id: number, workspaceData: { name?: string; slug?: string; description?: string }) => {
    return api.put<Workspace>(`/api/workspace/admin/${id}`, workspaceData)
  }
  
  const deleteWorkspace = async (id: number) => {
    return api.delete(`/api/workspace/admin/${id}`)
  }

  return {
    fetchCurrentWorkspace,
    joinWorkspace,
    fetchWorkspaces,
    createWorkspace,
    updateWorkspace,
    deleteWorkspace
  }
}