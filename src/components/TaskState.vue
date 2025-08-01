<template>
  <div class="flex flex-col gap-2">
    <!-- Loading state -->
    <OAlert
      v-if="hasPendingTasks"
      :title="loadingTitle"
      :description="loadingDescription"
      color="blue"
    />

    <!-- Error state -->
    <OAlert
      v-if="hasFailedTasks"
      title="Some tasks failed"
      :description="errorDescription"
      color="red"
    >
      <template #action>
        <OButton v-if="failedTaskTypes.length > 0" @click="restartFailedTasks" type="secondary">
          <i class="fa-solid fa-rotate-right"></i>
          Restart Failed Tasks
        </OButton>
      </template>
    </OAlert>

    <!-- Not started state -->
    <OAlert
      v-if="hasNotStartedTasks"
      title="Tasks not started"
      :description="notStartedDescription"
      color="gray"
    >
      <template #action>
        <OButton
          v-if="notStartedTaskTypes.length > 0"
          @click="startNotStartedTasks"
          type="secondary"
        >
          <i class="fa-solid fa-play"></i>
          Start Tasks
        </OButton>
      </template>
    </OAlert>

    <!-- Partial success state -->
    <OAlert
      v-if="hasPartialSuccess"
      title="Partial data available"
      :description="partialSuccessDescription"
      color="yellow"
    >
      <template #action>
        <OButton v-if="failedTaskTypes.length > 0" @click="restartFailedTasks" type="secondary">
          <i class="fa-solid fa-rotate-right"></i>
          Restart Failed Tasks
        </OButton>
      </template>
    </OAlert>
  </div>
</template>

<script setup lang="ts">
import { OAlert, OButton } from '@owlint/feathers-vue'
import type { TaskType, TaskStatus } from '@/types/task'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { useCreateTask, useRestartTask } from '@/mutations/tasks'

interface Props {
  requiredTaskTypes: TaskType[]
  loadingTitle?: string
  loadingDescription?: string
}

const props = withDefaults(defineProps<Props>(), {
  loadingTitle: 'Loading data...',
  loadingDescription: 'Please wait while we fetch the required data.',
})

const route = useRoute()

const companyId = computed(() => route.params.companyId as string)

const { data: company } = useQuery(companyByIdQuery, () => ({
  id: companyId.value as string,
}))

const { mutate: restartTask } = useRestartTask()
const { mutate: createTask } = useCreateTask()

// Computed properties for task states
const tasks = computed(() => {
  return company.value?.tasks || []
})

const taskStatuses = computed(() => {
  return props.requiredTaskTypes.map((type) => {
    const task = tasks.value.find((t: { type: TaskType; status: TaskStatus }) => t.type === type)
    return {
      type,
      status: task?.status || null,
    }
  })
})

const hasPendingTasks = computed(() => {
  return taskStatuses.value.some((task) => task.status === 'pending' || task.status === 'running')
})

const hasFailedTasks = computed(() => {
  return taskStatuses.value.some((task) => task.status === 'error')
})

const hasNotStartedTasks = computed(() => {
  return taskStatuses.value.some((task) => task.status === null)
})

const hasPartialSuccess = computed(() => {
  const hasSuccess = taskStatuses.value.some((task) => task.status === 'succeeded')
  return hasSuccess && (hasFailedTasks.value || hasNotStartedTasks.value)
})

const failedTaskTypes = computed(() => {
  return taskStatuses.value.filter((task) => task.status === 'error').map((task) => task.type)
})

const notStartedTaskTypes = computed(() => {
  return taskStatuses.value.filter((task) => task.status === null).map((task) => task.type)
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
      await restartTask(task.id)
    }
  }
}

const startNotStartedTasks = async () => {
  for (const taskType of notStartedTaskTypes.value) {
    await createTask({
      company_id: Number(companyId.value),
      type: taskType,
      status: 'pending',
    })
  }
}
</script>
