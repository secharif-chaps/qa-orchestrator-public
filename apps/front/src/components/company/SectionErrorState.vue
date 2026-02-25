<template>
  <div v-if="state === 'error'" class="flex flex-col items-center">
    <!-- Chapse Error Image -->
    <div class="mb-6">
      <img :src="chapseErrorImage" alt="Error" class="h-auto w-24" />
    </div>

    <!-- Error Title -->
    <h3 class="text-secondary mb-2 text-xl font-semibold">
      {{ displayTitle }}
    </h3>

    <!-- Error Description -->
    <p class="text-secondary mx-auto mb-4 max-w-md text-center">
      {{ displayDescription }}
    </p>

    <!-- Error details -->
    <div
      v-if="task?.error"
      class="bg-error-500/10 border-error-500/20 mx-auto mb-6 max-w-md rounded-lg border p-4"
    >
      <p class="text-error-500 text-sm">{{ task?.error }}</p>
    </div>

    <!-- Retry action -->
    <Button
      v-if="task"
      variant="primary"
      icon="fa fa-refresh"
      :label="t('company.taskError.restartTask')"
      @click="restartTask(task.type)"
      :loading="isRestarting === task.type"
    />
  </div>
</template>

<script lang="ts" setup>
import { Button } from '@owlint/feathers-vue'
import chapseErrorImage from '@/assets/chapse/error_light.svg'
import type { TaskResponse, TaskType } from '@/types/task'
import { ref, computed } from 'vue'
import { useRestartTask } from '@/mutations/tasks'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  title?: string
  description?: string
  icon?: string
  task?: TaskResponse
}

const state = 'error'

const props = defineProps<Props>()

const displayTitle = computed(() => props.title ?? t('company.taskError.title'))
const displayDescription = computed(() => props.description ?? t('company.taskError.description'))

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
