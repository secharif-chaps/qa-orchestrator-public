import { defineStore } from 'pinia'
import type { TaskCreate, TaskResponse, TaskStatus } from '~/types/company'

export const useTaskStore = defineStore('task', () => {
  // State
  const companyTasks = ref(new Map<number, TaskResponse[]>())
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pollingIntervals = ref(new Map<number, NodeJS.Timeout>())

  // Getters
  const getCompanyTasks = (companyId: number) => {
    return companyTasks.value.get(companyId) || []
  }

  const hasPendingTasks = (companyId: number) => {
    const tasks = companyTasks.value.get(companyId)
    if (!tasks) return false
    return tasks.some(task => task.status === 'pending' || task.status === 'running')
  }

  const getTaskStatus = (companyId: number | undefined, taskType: string): TaskStatus | null => {
    if (!companyId) return null
    const tasks = companyTasks.value.get(companyId)
    if (!tasks) return null
    const task = tasks.find(t => t.type === taskType)
    return task?.status || null
  }

  // Actions
  async function fetchCompanyTasks(companyId: number) {
    const repository = useTaskRepository()
    loading.value = true
    error.value = null

    try {
      const tasks = await repository.getCompanyTasks(companyId)
      companyTasks.value.set(companyId, tasks)
      return tasks
    } catch (err) {
      error.value = (err as Error).message
      console.error(`Failed to fetch tasks for company ID ${companyId}:`, err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTask(task: TaskCreate) {
    const repository = useTaskRepository()
    loading.value = true
    error.value = null

    try {
      const newTask = await repository.createTask(task)
      
      // Update tasks in the map
      const tasks = companyTasks.value.get(task.company_id) || []
      const taskIndex = tasks.findIndex(t => t.id === newTask.id)
      if (taskIndex !== -1) {
        tasks[taskIndex] = newTask
      } else {
        tasks.push(newTask)
      }
      companyTasks.value.set(task.company_id, tasks)
      
      // Start polling if not already polling
      if (!pollingIntervals.value.has(task.company_id)) {
        startPolling(task.company_id)
      }
      
      return newTask
    } catch (err) {
      error.value = (err as Error).message
      console.error('Failed to create task:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function restartTask(taskId: number) {
    const repository = useTaskRepository()
    loading.value = true
    error.value = null

    try {
      const restartedTask = await repository.restartTask(taskId)
      
      // Update task in the map
      for (const [companyId, tasks] of companyTasks.value.entries()) {
        const taskIndex = tasks.findIndex(t => t.id === taskId)
        if (taskIndex !== -1) {
          tasks[taskIndex] = restartedTask
          companyTasks.value.set(companyId, tasks)
          break
        }
      }
      
      return restartedTask
    } catch (err) {
      error.value = (err as Error).message
      console.error(`Failed to restart task with ID ${taskId}:`, err)
      throw err
    } finally {
      loading.value = false
    }
  }

  function startPolling(companyId: number) {
    // Clear any existing polling interval for this company
    if (pollingIntervals.value.has(companyId)) {
      clearInterval(pollingIntervals.value.get(companyId))
    }

    // Start polling every 5 seconds
    const interval = setInterval(async () => {
      try {
        const tasks = await fetchCompanyTasks(companyId)
        
        // Check if all tasks are completed
        const allTasksCompleted = tasks.every(
          (task: TaskResponse) => task.status === 'succeeded' || task.status === 'error'
        )
        
        if (allTasksCompleted) {
          stopPolling(companyId)
        }
      } catch (err) {
        console.error('Error polling company tasks:', err)
        stopPolling(companyId)
      }
    }, 5000)

    pollingIntervals.value.set(companyId, interval)
  }

  function stopPolling(companyId: number) {
    const interval = pollingIntervals.value.get(companyId)
    if (interval) {
      clearInterval(interval)
      pollingIntervals.value.delete(companyId)
    }
  }

  function clearCompanyTasks(companyId: number) {
    companyTasks.value.delete(companyId)
    stopPolling(companyId)
  }

  return {
    // State
    companyTasks,
    loading,
    error,
    pollingIntervals,
    
    // Getters
    getCompanyTasks,
    hasPendingTasks,
    getTaskStatus,
    
    // Actions
    fetchCompanyTasks,
    createTask,
    restartTask,
    startPolling,
    stopPolling,
    clearCompanyTasks
  }
}) 