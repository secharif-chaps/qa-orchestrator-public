import { type Workspace } from '@/types/workspace'
import { apiClient } from './client'

export const getCurrentWorkspace = async () => {
  const response = await apiClient.get<Workspace>('/workspace/current')
  return response
}