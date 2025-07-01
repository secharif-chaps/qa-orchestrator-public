<template>
  <div class="bg-white rounded-lg overflow-hidden">
    <button
      class="w-full px-4 py-3 bg-white flex items-center justify-between text-left border-b border-gray-200 cursor-pointer"
      @click="isOpen = !isOpen"
      :class="{
        'border-b-0': !isOpen
      }"
    >
      <span class="font-medium">Tasks</span>
      <i
        class="fa"
        :class="!isOpen ? 'fa-chevron-up' : 'fa-chevron-down'"
      ></i>
    </button>
    
    <div v-show="isOpen" class="p-4">
      <div class="grid grid-cols-2 gap-4">
        <TaskItem
          v-for="taskType in taskTypes"
          :key="taskType"
          :type="taskType"
          :status="getTaskStatus(taskType)"
          :error="getTaskError(taskType)"
          @start="createTask(taskType)"
          @restart="restartTask(taskType)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { TaskType, TaskStatus, TaskCreate, TaskResponse } from '~/types/task'

interface Props {
  companyId: number
}

const props = defineProps<Props>()
const isOpen = ref(false)

const taskStore = useTaskStore()

// Define all possible task types
const taskTypes: TaskType[] = ['profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press', 'team']

// Fetch tasks when component is mounted
onMounted(async () => {
  await taskStore.fetchCompanyTasks(props.companyId)
})

// Watch for company ID changes
watch(() => props.companyId, async (newId) => {
  await taskStore.fetchCompanyTasks(newId)
})

// Get tasks from store
const tasks = computed(() => taskStore.getCompanyTasks(props.companyId))

// Get status for a specific task type
const getTaskStatus = (type: TaskType): TaskStatus | null => {
  const task = tasks.value.find((t: TaskResponse) => t.type === type)
  return task?.status || null
}

// Get error message for a specific task type
const getTaskError = (type: TaskType): string | null => {
  const task = tasks.value.find((t: TaskResponse) => t.type === type)
  return task?.error || null
}

// Create a new task with optimistic UI
const createTask = async (type: TaskType) => {
  try {
    const task: TaskCreate = {
      type,
      status: 'pending',
      company_id: props.companyId
    }
    await taskStore.createTask(task)
  } catch (error) {
    // Error handling is done in the store with optimistic updates
    console.error('Error creating task:', error)
  }
}

// Restart a task with optimistic UI
const restartTask = async (type: TaskType) => {
  try {
    const task = tasks.value.find((t: TaskResponse) => t.type === type)
    if (task) {
      await taskStore.restartTask(task.id)
    }
  } catch (error) {
    // Error handling is done in the store with optimistic updates
    console.error('Error restarting task:', error)
  }
}

// Clean up when component is unmounted
onUnmounted(() => {
  taskStore.clearCompanyTasks(props.companyId)
})
</script> 