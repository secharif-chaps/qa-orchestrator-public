import { ref } from 'vue'
import { defineMutation, useMutation } from '@pinia/colada'
import { createTask, restartTask } from '@/api/tasks'
import type { TaskCreate, TaskType } from '@/types/task'

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

export const useRestartTask = defineMutation(() => {
  const taskId = ref<number | null>(null)

  const { mutate, ...mutation } = useMutation({
    mutation: (id: number) => restartTask(id),
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
})
