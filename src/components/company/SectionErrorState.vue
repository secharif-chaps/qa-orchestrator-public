<template>
  <div v-if="state === 'error'" class="flex flex-col items-center">
    <!-- Chapse Error Image -->
    <div class="mb-6">
      <img :src="chapseErrorImage" alt="Error" class="w-24 h-auto" />
    </div>

    <!-- Error Title -->
    <h3 class="text-xl font-semibold text-primary-light-content mb-2">
      {{ title }}
    </h3>

    <!-- Error Description -->
    <p class="text-primary-light-content max-w-md mx-auto mb-4 text-center">
      {{ description }}
    </p>

    <!-- Error details -->
    <div
      v-if="task?.error"
      class="bg-error-500/10 border border-error-500/20 rounded-lg p-4 max-w-md mx-auto mb-6"
    >
      <p class="text-sm text-error-500">{{ task?.error }}</p>
    </div>

    <!-- Retry action -->
    <Button
      v-if="task"
      variant="primary"
      icon="fa fa-refresh"
      label="Restart task"
      @click="restartTask(task.type)"
      :loading="isRestarting === task.type"
    />
  </div>
</template>

<script lang="ts" setup>
import Button from '@/components/ui/Button.vue'
import chapseErrorImage from '@/assets/chapse/error_light.svg'
import type { TaskResponse, TaskType } from '@/types/task'
import { ref } from 'vue'
import { useRestartTask } from '@/mutations/tasks'

interface Props {
  title?: string
  description?: string
  icon?: string
  task?: TaskResponse
}

const state = 'error'

const props = withDefaults(defineProps<Props>(), {
  title: 'Error',
  description: 'An error occurred while loading the page',
  icon: 'fa fa-exclamation-triangle',
  task: undefined,
})

const { mutate: restart } = useRestartTask()
const isRestarting = ref<TaskType | null>(null)

const restartTask = async (taskType: TaskType) => {
  isRestarting.value = taskType
  try {
    const task = props.task
    if (task) {
      console.log('🔄 Restarting task:', task.id, task.type)
      await restart(task.id)
      console.log('✅ Task restarted successfully')
    } else {
      console.warn('⚠️ Task not found for type:', taskType)
    }
  } catch (error) {
    console.error('❌ Error restarting task:', error)
  } finally {
    isRestarting.value = null
  }
}
</script>
