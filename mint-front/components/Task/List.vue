<template>
  <!-- Show TaskFlow for all users except 'nmr' -->
  <TaskFlow 
    :company-id="companyId" 
  />
  
</template>

<script setup lang="ts">
import type { TaskType, TaskStatus, TaskCreate, TaskResponse } from '~/types/task'

interface Props {
  companyId: number
}

const props = defineProps<Props>()
const isOpen = ref(false)

const taskStore = useTaskStore()
const { user } = useAuth()

// Check if current user is 'nmr'
const isNmrUser = computed(() => {
  return user.value?.profile?.preferred_username === 'nmr'
})

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