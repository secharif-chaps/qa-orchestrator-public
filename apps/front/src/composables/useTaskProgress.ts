import type { TaskResponse } from '@/types/task'
import { computed, type Ref } from 'vue'

export interface ProgressSegment {
  percentage: number
  color: string
}

export interface RenderedSegment extends ProgressSegment {
  dashoffset: number
}

export const useTaskProgress = (tasks: Ref<TaskResponse[] | undefined>) => {
  const isInProgress = computed(() => {
    if (!tasks.value || tasks.value.length === 0) return false
    return tasks.value.some((t) => t.status === 'running' || t.status === 'pending')
  })

  const segments = computed<ProgressSegment[]>(() => {
    if (!tasks.value || tasks.value.length === 0) return []

    const total = tasks.value.length
    const perTask = 100 / total
    const succeeded = tasks.value.filter((t) => t.status === 'succeeded').length
    const rest = total - succeeded

    const result: ProgressSegment[] = []

    if (succeeded > 0) {
      result.push({
        percentage: succeeded * perTask,
        color: 'stroke-success',
      })
    }

    if (rest > 0) {
      result.push({
        percentage: rest * perTask,
        color: 'stroke-sage-200',
      })
    }

    return result
  })

  return { segments, isInProgress }
}
