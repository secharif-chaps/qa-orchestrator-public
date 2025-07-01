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
    error.value = null

    // Optimistic update: immediately set task as running
    const optimisticTask: TaskResponse = {
      id: Date.now(), // Temporary ID
      company_id: task.company_id,
      type: task.type,
      status: 'running',
      error: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    }

    // Update UI immediately
    const tasks = companyTasks.value.get(task.company_id) || []
    const existingIndex = tasks.findIndex(t => t.type === task.type)
    
    if (existingIndex !== -1) {
      // Update existing task
      tasks[existingIndex] = optimisticTask
    } else {
      // Add new task
      tasks.push(optimisticTask)
    }
    companyTasks.value.set(task.company_id, tasks)

    loading.value = true
    try {
      const newTask = await repository.createTask(task)
      
      // Replace optimistic task with real task data
      const currentTasks = companyTasks.value.get(task.company_id) || []
      const optimisticIndex = currentTasks.findIndex(t => t.type === task.type)
      if (optimisticIndex !== -1) {
        currentTasks[optimisticIndex] = newTask
        companyTasks.value.set(task.company_id, currentTasks)
      }
      
      // Start polling if not already polling
      if (!pollingIntervals.value.has(task.company_id)) {
        startPolling(task.company_id)
      }
      
      return newTask
    } catch (err) {
      // Revert optimistic update on error
      const currentTasks = companyTasks.value.get(task.company_id) || []
      const failedIndex = currentTasks.findIndex(t => t.type === task.type)
      if (failedIndex !== -1) {
        currentTasks[failedIndex] = {
          ...optimisticTask,
          status: 'error',
          error: `Failed to start task: ${(err as Error).message}`
        }
        companyTasks.value.set(task.company_id, currentTasks)
      }
      
      error.value = (err as Error).message
      console.error('Failed to create task:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function restartTask(taskId: number) {
    const repository = useTaskRepository()
    error.value = null

    // Find the task and apply optimistic update
    let originalTask: TaskResponse | null = null
    let companyId: number | null = null
    
    for (const [cId, tasks] of companyTasks.value.entries()) {
      const taskIndex = tasks.findIndex(t => t.id === taskId)
      if (taskIndex !== -1) {
        originalTask = { ...tasks[taskIndex] }
        companyId = cId
        
        // Optimistic update: immediately set as running
        tasks[taskIndex] = {
          ...tasks[taskIndex],
          status: 'running',
          error: null,
          updated_at: new Date().toISOString()
        }
        companyTasks.value.set(cId, tasks)
        break
      }
    }

    if (!originalTask || !companyId) {
      throw new Error(`Task with ID ${taskId} not found`)
    }

    loading.value = true
    try {
      const restartedTask = await repository.restartTask(taskId)
      
      // Update task with real data from server
      const tasks = companyTasks.value.get(companyId) || []
      const taskIndex = tasks.findIndex(t => t.id === taskId)
      if (taskIndex !== -1) {
        tasks[taskIndex] = restartedTask
        companyTasks.value.set(companyId, tasks)
      }
      
      return restartedTask
    } catch (err) {
      // Revert optimistic update on error
      if (companyId) {
        const tasks = companyTasks.value.get(companyId) || []
        const taskIndex = tasks.findIndex(t => t.id === taskId)
        if (taskIndex !== -1) {
          tasks[taskIndex] = {
            ...originalTask,
            status: 'error',
            error: `Failed to restart task: ${(err as Error).message}`,
            updated_at: new Date().toISOString()
          }
          companyTasks.value.set(companyId, tasks)
        }
      }
      
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

  // Utility function to update task status optimistically
  function updateTaskStatusOptimistically(companyId: number, taskType: string, status: TaskStatus, error?: string) {
    const tasks = companyTasks.value.get(companyId) || []
    const taskIndex = tasks.findIndex(t => t.type === taskType)
    
    if (taskIndex !== -1) {
      tasks[taskIndex] = {
        ...tasks[taskIndex],
        status,
        error: error || null,
        updated_at: new Date().toISOString()
      }
      companyTasks.value.set(companyId, tasks)
    }
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
    clearCompanyTasks,
    updateTaskStatusOptimistically
  }
}) 