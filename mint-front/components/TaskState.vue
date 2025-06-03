<template>
  <div class="flex flex-col gap-2">
    <!-- Loading state -->
    <OAlert
      v-if="hasPendingTasks"
      :title="loadingTitle"
      :description="loadingDescription"
      icon="fa-spinner-third animate-spin"
      color="blue"
    />

    <!-- Error state -->
    <OAlert
      v-if="hasFailedTasks"
      title="Some tasks failed"
      :description="errorDescription"
      icon="fa-exclamation-triangle"
      color="red"
    >
      <template #action>
        <OButton
          v-if="failedTaskTypes.length > 0"
          @click="restartFailedTasks"
          type="secondary"
          icon="fa-refresh"
        >
          Restart Failed Tasks
        </OButton>
      </template>
    </OAlert>

    <!-- Not started state -->
    <OAlert
      v-if="hasNotStartedTasks"
      title="Tasks not started"
      :description="notStartedDescription"
      icon="fa-info-circle"
      color="gray"
    >
      <template #action>
        <OButton
          v-if="notStartedTaskTypes.length > 0"
          @click="startNotStartedTasks"
          type="secondary"
          icon="fa-play"
        >
          Start Tasks
        </OButton>
      </template>
    </OAlert>

    <!-- Partial success state -->
    <OAlert
      v-if="hasPartialSuccess"
      title="Partial data available"
      :description="partialSuccessDescription"
      icon="fa-info-circle"
      color="yellow"
    >
      <template #action>
        <OButton
          v-if="failedTaskTypes.length > 0"
          @click="restartFailedTasks"
          type="secondary"
          icon="fa-refresh"
        >
          Restart Failed Tasks
        </OButton>
      </template>
    </OAlert>
  </div>
</template>

<script setup lang="ts">
import { OAlert, OButton } from '@owlint/feathers-vue'
import { useTaskRepository } from '~/composables/useTaskRepository'
import type { TaskType, TaskStatus } from '~/types/task'

interface Props {
  companyId: number
  requiredTaskTypes: TaskType[]
  loadingTitle?: string
  loadingDescription?: string
}

const props = withDefaults(defineProps<Props>(), {
  loadingTitle: 'Loading data...',
  loadingDescription: 'Please wait while we fetch the required data.'
})

const taskRepository = useTaskRepository()
const { company } = useCompanyData()

// Computed properties for task states
const tasks = computed(() => {
  return company.value?.tasks || []
})

const taskStatuses = computed(() => {
  return props.requiredTaskTypes.map(type => {
    const task = tasks.value.find((t: { type: TaskType; status: TaskStatus }) => t.type === type)
    return {
      type,
      status: task?.status || null
    }
  })
})

const hasPendingTasks = computed(() => {
  return taskStatuses.value.some(
    task => task.status === 'pending' || task.status === 'running'
  )
})

const hasFailedTasks = computed(() => {
  return taskStatuses.value.some(task => task.status === 'error')
})

const hasNotStartedTasks = computed(() => {
  return taskStatuses.value.some(task => task.status === null)
})

const hasPartialSuccess = computed(() => {
  const hasSuccess = taskStatuses.value.some(task => task.status === 'succeeded')
  return hasSuccess && (hasFailedTasks.value || hasNotStartedTasks.value)
})

const failedTaskTypes = computed(() => {
  return taskStatuses.value
    .filter(task => task.status === 'error')
    .map(task => task.type)
})

const notStartedTaskTypes = computed(() => {
  return taskStatuses.value
    .filter(task => task.status === null)
    .map(task => task.type)
})

// Computed descriptions
const errorDescription = computed(() => {
  if (failedTaskTypes.value.length === 1) {
    return `The ${failedTaskTypes.value[0]} task failed. Please try again.`
  }
  return `The following tasks failed: ${failedTaskTypes.value.join(', ')}. Please try again.`
})

const notStartedDescription = computed(() => {
  if (notStartedTaskTypes.value.length === 1) {
    return `The ${notStartedTaskTypes.value[0]} task has not been started yet.`
  }
  return `The following tasks have not been started: ${notStartedTaskTypes.value.join(', ')}.`
})

const partialSuccessDescription = computed(() => {
  const parts = []
  if (failedTaskTypes.value.length > 0) {
    parts.push(`Failed tasks: ${failedTaskTypes.value.join(', ')}`)
  }
  if (notStartedTaskTypes.value.length > 0) {
    parts.push(`Not started tasks: ${notStartedTaskTypes.value.join(', ')}`)
  }
  return `Some data is available, but ${parts.join(' and ')}. The information may be incomplete.`
})

// Methods
const restartFailedTasks = async () => {
  for (const taskType of failedTaskTypes.value) {
    const task = tasks.value.find((t: { type: TaskType; id?: number }) => t.type === taskType)
    if (task?.id) {
      await taskRepository.restartTask(task.id)
    }
  }
}

const { fetchCompany } = useCompanyData()


const startNotStartedTasks = async () => {
  for (const taskType of notStartedTaskTypes.value) {
    await taskRepository.createTask({
      company_id: props.companyId,
      type: taskType,
      status: 'pending'
    })
  }
  // Fetch company data to update the store
  await fetchCompany()
}
</script> 