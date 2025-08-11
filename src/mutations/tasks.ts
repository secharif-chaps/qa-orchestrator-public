import { computed, ref } from 'vue'
import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { createTask, restartTask } from '@/api/tasks'
import type { TaskCreate, TaskResponse, TaskType } from '@/types/task'
import { useRoute } from 'vue-router'
import { TASK_QUERY_KEYS } from '@/queries/tasks'

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
  const companyId = computed(() => route.params.companyId as string)

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => restartTask(id),
    onMutate: (id: number) => {
      console.log('getting cache', TASK_QUERY_KEYS.byCompanyId(companyId.value))

      const previousTasks = queryCache.getQueryData(
        TASK_QUERY_KEYS.byCompanyId(companyId.value),
      ) as TaskResponse[]

      const taskToUpdate = previousTasks?.find((task: TaskResponse) => task.id === id)
      
      if (!taskToUpdate) {
        throw new Error('Task not found')
      }

      const newTasks = [...previousTasks]
      newTasks.find((task: TaskResponse) => task.id === id)!.status = 'running'

      // Set the optimistic update to the cache
      queryCache.setQueryData(TASK_QUERY_KEYS.byCompanyId(companyId.value), newTasks)

      return { previousTasks, newTasks }
    },
    onError: (error, variables, { previousTasks }) => {
      queryCache.setQueryData(TASK_QUERY_KEYS.byCompanyId(companyId.value), previousTasks)

      console.error(`An error occurred when updating a task "${variables}"`, error)
    },
    onSettled: (data, error, variables, { newTasks }) => {
      console.log('tasks mutation settled invalidating cache to get fresh data')
      if (newTasks) {
        queryCache.invalidateQueries({ key: TASK_QUERY_KEYS.byCompanyId(companyId.value) })
      }
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
