import { useApiService } from './useApiService'
import type { TaskCreate, TaskResponse } from '~/types/company'

const tasksUrl = '/tasks'

export const useTaskRepository = () => {
  const api = useApiService()

  return {
    /**
     * Create a new task
     */
    createTask: (task: TaskCreate) => {
      return api.post<TaskResponse>(`${tasksUrl}/`, task)
    },

    /**
     * Get all tasks for a company
     */
    getCompanyTasks: (companyId: number) => {
      return api.get<TaskResponse[]>(`${tasksUrl}/company/${companyId}`)
    },

    /**
     * Restart a specific task
     */
    restartTask: (taskId: number) => {
      return api.post<TaskResponse>(`${tasksUrl}/${taskId}/restart`, {})
    }
  }
} 