import { ref, computed } from 'vue'
import { useMutation, useQueryCache } from '@pinia/colada'
import { restartAdminTasks } from '@/api/admin'
import { ADMIN_QUERY_KEYS } from '@/queries/admin'
import type { BulkRestartResponse } from '@/types/admin'

/**
 * Mutation for bulk restarting admin tasks
 * Invalidates task list and stats queries on success
 */
export const useRestartAdminTasks = () => {
  const queryCache = useQueryCache()
  const selectedTaskIds = ref<number[]>([])

  const { mutate, mutateAsync, ...mutation } = useMutation({
    mutation: (taskIds: number[]) => restartAdminTasks(taskIds),
    onSuccess: (data: BulkRestartResponse) => {
      // Invalidate all admin task queries to refresh the data
      queryCache.invalidateQueries({ key: ADMIN_QUERY_KEYS.tasks() })

      // Also invalidate task stats since counts may have changed
      queryCache.invalidateQueries({
        key: ADMIN_QUERY_KEYS.root,
        predicate: (query) => {
          const key = query.key as readonly unknown[]
          return key.length > 1 && key[1] === 'taskStats'
        },
      })

      // Clear selection after successful restart
      selectedTaskIds.value = []

      return data
    },
  })

  /**
   * Restart the currently selected tasks
   */
  function restartSelected() {
    if (selectedTaskIds.value.length === 0) {
      throw new Error('No tasks selected for restart')
    }
    if (selectedTaskIds.value.length > 50) {
      throw new Error('Cannot restart more than 50 tasks at once')
    }
    return mutate(selectedTaskIds.value)
  }

  /**
   * Restart specific task IDs and return the result
   */
  async function restartTasks(taskIds: number[]): Promise<BulkRestartResponse> {
    if (taskIds.length === 0) {
      throw new Error('No task IDs provided')
    }
    if (taskIds.length > 50) {
      throw new Error('Cannot restart more than 50 tasks at once')
    }
    return mutateAsync(taskIds)
  }

  /**
   * Toggle a task in the selection
   */
  function toggleTaskSelection(taskId: number) {
    const index = selectedTaskIds.value.indexOf(taskId)
    if (index === -1) {
      selectedTaskIds.value.push(taskId)
    } else {
      selectedTaskIds.value.splice(index, 1)
    }
  }

  /**
   * Select all provided task IDs
   */
  function selectAll(taskIds: number[]) {
    selectedTaskIds.value = [...taskIds]
  }

  /**
   * Clear all selections
   */
  function clearSelection() {
    selectedTaskIds.value = []
  }

  /**
   * Check if a task is selected
   */
  function isSelected(taskId: number) {
    return selectedTaskIds.value.includes(taskId)
  }

  return {
    ...mutation,
    mutate,
    mutateAsync,
    selectedTaskIds,
    restartSelected,
    restartTasks,
    toggleTaskSelection,
    selectAll,
    clearSelection,
    isSelected,
    isPending: computed(() => mutation.status.value === 'pending'),
  }
}
