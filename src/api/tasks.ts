import { type TaskCreate, type TaskResponse } from '@/types/task'
import { apiClient } from './client'

export const getCompanyTasks = async (companyId: string) => {
  const response = await apiClient.get<TaskResponse[]>(`/tasks/company/${companyId}`)
  return response
}

export const createTask = async (task: TaskCreate) => {
  const response = await apiClient.post<TaskResponse>('/tasks', task)
  return response
}

export const restartTask = async (taskId: number) => {
  const response = await apiClient.post<TaskResponse>(`/tasks/${taskId}/restart`, {})
  return response
}