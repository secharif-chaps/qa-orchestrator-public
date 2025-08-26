import { computed, type ComputedRef } from 'vue'
import type { TaskType, TaskResponse } from '@/types/task'

interface Company {
  tasks?: TaskResponse[]
  [key: string]: any
}

interface TaskStateResult {
  isLoading: ComputedRef<boolean>
  hasErrors: ComputedRef<boolean>
  isComplete: ComputedRef<boolean>
  errorMessages: ComputedRef<string[]>
  taskProgress: ComputedRef<{
    completed: number
    total: number
  }>
  getTaskStatus: (taskType: TaskType) => ComputedRef<'pending' | 'running' | 'succeeded' | 'error' | 'not-found'>
}

/**
 * Composable for managing task states across company pages
 * 
 * @param company - Reactive company data
 * @param taskTypes - Task types to monitor (single or array)
 * @returns Task state management utilities
 */
export function useTaskState(
  company: ComputedRef<Company | undefined>,
  taskTypes: TaskType | TaskType[]
): TaskStateResult {
  
  const taskTypeArray = computed(() => 
    Array.isArray(taskTypes) ? taskTypes : [taskTypes]
  )

  // Get task status for a specific task type
  const getTaskStatus = (taskType: TaskType) => computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return 'not-found'
    
    const task = tasks.find(t => t.type === taskType)
    return task?.status || 'not-found'
  })

  // Check if any monitored tasks are loading (pending or running)
  const isLoading = computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return false
    
    return taskTypeArray.value.some(taskType => {
      const task = tasks.find(t => t.type === taskType)
      return task && (task.status === 'pending' || task.status === 'running')
    })
  })

  // Check if any monitored tasks have errors
  const hasErrors = computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return false
    
    return taskTypeArray.value.some(taskType => {
      const task = tasks.find(t => t.type === taskType)
      return task && task.status === 'error'
    })
  })

  // Check if all monitored tasks are complete (succeeded)
  const isComplete = computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return false
    
    return taskTypeArray.value.every(taskType => {
      const task = tasks.find(t => t.type === taskType)
      return task && task.status === 'succeeded'
    })
  })

  // Get error messages from failed tasks
  const errorMessages = computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return []
    
    return taskTypeArray.value
      .map(taskType => {
        const task = tasks.find(t => t.type === taskType)
        return task && task.status === 'error' ? task.error || `${taskType} task failed` : null
      })
      .filter(Boolean) as string[]
  })

  // Calculate task progress
  const taskProgress = computed(() => {
    const tasks = company.value?.tasks
    if (!tasks) return { completed: 0, total: taskTypeArray.value.length }
    
    const total = taskTypeArray.value.length
    const completed = taskTypeArray.value.filter(taskType => {
      const task = tasks.find(t => t.type === taskType)
      return task && task.status === 'succeeded'
    }).length
    
    return { completed, total }
  })

  return {
    isLoading,
    hasErrors,
    isComplete,
    errorMessages,
    taskProgress,
    getTaskStatus
  }
}

/**
 * Utility function to check if data exists for a specific section
 * 
 * @param company - Company data
 * @param section - Section to check (e.g., 'jobs', 'timeline', 'team')
 * @param dataPath - Optional specific path to check within the section
 * @returns Boolean indicating if data exists
 */
export function hasDataForSection(
  company: Company | undefined,
  section: string,
  dataPath?: string
): boolean {
  if (!company || !company[section]) return false
  
  const sectionData = company[section]
  
  // If no specific data path, check if section exists and is not empty
  if (!dataPath) {
    if (Array.isArray(sectionData)) return sectionData.length > 0
    if (typeof sectionData === 'object') return Object.keys(sectionData).length > 0
    return !!sectionData
  }
  
  // Check specific data path
  const pathParts = dataPath.split('.')
  let currentData = sectionData
  
  for (const part of pathParts) {
    if (!currentData || typeof currentData !== 'object') return false
    currentData = currentData[part]
  }
  
  if (Array.isArray(currentData)) return currentData.length > 0
  if (typeof currentData === 'object') return Object.keys(currentData).length > 0
  return !!currentData
}