import { computed, ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createTask, restartTask } from '@/api/tasks'
import type { TaskCreate, TaskResponse, TaskType } from '@/types/task'
import { useRoute } from 'vue-router'
import { TASK_QUERY_KEYS } from '@/queries/tasks'
import { COMPANY_QUERY_KEYS } from '@/queries/companies'

const queryCache = useQueryCache()

export const useCreateTask = defineMutation(() => {
  const companyId = ref<number | null>(null)
  const taskType = ref<string>('')

  const { mutate, ...mutation } = useMutation({
    mutation: (task: TaskCreate) => createTask(task),
  })

  return {
    ...mutation,
    // Expose a method that uses the refs
    createTask: () => {
      if (!companyId.value || !taskType.value) {
        throw new Error('Company ID and task type are required')
      }
      return mutate({
        company_id: companyId.value,
        type: taskType.value as TaskType,
        status: 'pending',
      })
    },
    // Expose the refs for component binding
    companyId,
    taskType,
    mutate,
  }
})

export const useRestartTask = () => {
  const taskId = ref<number | null>(null)
  const route = useRoute()
  const companyId = computed(() => (route.params as { companyId?: string }).companyId ?? '')

  console.log('🔄 restarting Task ID:', taskId.value)

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => {
      console.log('🔄 Calling restartTask API with ID:', id)
      return restartTask(id)
    },
    onMutate: (id: number) => {
      console.log('⏳ onMutate: Getting cache for company', companyId.value)

      const previousTasks = queryCache.getQueryData(
        TASK_QUERY_KEYS.byCompanyId(companyId.value),
      ) as TaskResponse[]

      if (!previousTasks) {
        console.warn('⚠️ No previous tasks found in cache')
        return { previousTasks: [] }
      }

      const taskToUpdate = previousTasks.find((task: TaskResponse) => task.id === id)

      if (!taskToUpdate) {
        console.warn('⚠️ Task not found in cache:', id)
        return { previousTasks }
      }

      const newTasks = previousTasks.map((task: TaskResponse) =>
        task.id === id ? { ...task, status: 'running' as const } : task,
      )

      // Set the optimistic update to the cache
      queryCache.setQueryData(TASK_QUERY_KEYS.byCompanyId(companyId.value), newTasks)
      console.log('✅ Optimistic update applied')

      return { previousTasks, newTasks }
    },
    onError: (error, variables, context) => {
      console.error('❌ onError: Restoring previous tasks', error)
      if (context?.previousTasks) {
        queryCache.setQueryData(TASK_QUERY_KEYS.byCompanyId(companyId.value), context.previousTasks)
      }
    },
    onSettled: () => {
      console.log('✅ onSettled: Invalidating cache to get fresh data')
      // Invalidate tasks cache
      queryCache.invalidateQueries({ key: TASK_QUERY_KEYS.byCompanyId(companyId.value) })
      // Invalidate company cache to refetch updated data
      queryCache.invalidateQueries({ key: COMPANY_QUERY_KEYS.byId(companyId.value) })
      console.log('✅ Company data will be refetched automatically')
    },
  })

  return {
    ...mutation,
    // Expose a method that uses the ref
    restartTask: () => {
      if (!taskId.value) {
        throw new Error('Task ID is required')
      }
      return mutate(taskId.value)
    },
    // Expose the taskId ref
    taskId,
    mutate,
  }
}
