<template>
  <div v-if="state === 'error'" class="flex flex-col items-center">
    <!-- Chapse Error Image -->
    <div class="mb-6">
      <img :src="chapseErrorImage" alt="Error" class="h-auto w-24" />
    </div>

    <!-- Rate limit: countdown + retry -->
    <template v-if="isRateLimit && countdown !== null">
      <h3 class="text-neutral-black-font mb-2 text-xl font-semibold">
        {{ t('screen.company.analysisCard.error.rateLimit.title') }}
      </h3>
      <p class="text-neutral-black-font mx-auto mb-6 max-w-112 text-center">
        {{
          countdown > 0
            ? t('screen.company.analysisCard.error.rateLimit.description', { seconds: countdown })
            : t('screen.company.analysisCard.error.recoverable.description')
        }}
      </p>
      <Button
        v-if="task"
        variant="primary"
        :icon="countdown > 0 ? 'fa fa-hourglass-half' : 'fa fa-rotate-right'"
        :label="
          countdown > 0
            ? t('screen.company.analysisCard.error.rateLimit.waitingLabel', { seconds: countdown })
            : t('screen.company.analysisCard.error.retry')
        "
        :disabled="countdown > 0"
        :loading="isRestarting === task.type"
        @click="restartTask(task.type)"
      />
    </template>

    <!-- Recoverable error -->
    <template v-else-if="isRecoverable">
      <h3 class="text-neutral-black-font mb-2 text-xl font-semibold">
        {{ t('screen.company.analysisCard.error.recoverable.title') }}
      </h3>
      <p class="text-neutral-black-font mx-auto mb-6 max-w-112 text-center">
        {{ t('screen.company.analysisCard.error.recoverable.description') }}
      </p>
      <Button
        v-if="task"
        variant="primary"
        icon="fa fa-rotate-right"
        :label="t('screen.company.analysisCard.error.retry')"
        :loading="isRestarting === task.type"
        @click="restartTask(task.type)"
      />
    </template>

    <!-- Generic / permanent error -->
    <template v-else>
      <h3 class="text-neutral-black-font mb-2 text-xl font-semibold">
        {{ t('screen.company.analysisCard.error.generic.title') }}
      </h3>
      <p class="text-neutral-black-font mx-auto mb-6 max-w-112 text-center">
        {{ t('screen.company.analysisCard.error.generic.description') }}
      </p>
      <Button
        v-if="task"
        variant="primary"
        icon="fa fa-rotate-right"
        :label="t('screen.company.analysisCard.error.retry')"
        :loading="isRestarting === task.type"
        @click="restartTask(task.type)"
      />
    </template>
  </div>
</template>

<script lang="ts" setup>
import chapseErrorImage from '@/assets/chapse/error_light.svg'
import { useRestartTask } from '@/mutations/tasks'
import type { TaskResponse, TaskType } from '@/types/task'
import { Button } from '@owlint/feathers-vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  task?: TaskResponse
}

const state = 'error'

const props = defineProps<Props>()

const RATE_LIMIT_TYPES = ['rate_limit_llm', 'rate_limit_api']

const isRateLimit = computed(
  () =>
    props.task?.error_details != null &&
    RATE_LIMIT_TYPES.includes(props.task.error_details.error_type),
)

const isRecoverable = computed(() => props.task?.error_details?.is_recoverable === true)

const countdown = ref<number | null>(null)
let countdownInterval: ReturnType<typeof setInterval> | null = null

const clearCountdownInterval = () => {
  if (countdownInterval !== null) {
    clearInterval(countdownInterval)
    countdownInterval = null
  }
}

// Compute remaining seconds accounting for time already elapsed since the error occurred.
// This ensures the countdown resumes correctly after page refresh or modal close/reopen.
const getRemainingSeconds = (retryAfterSeconds: number): number => {
  const updatedAt = props.task?.updated_at
  if (!updatedAt) return retryAfterSeconds
  const elapsedSeconds = Math.floor((Date.now() - new Date(updatedAt).getTime()) / 1000)
  return Math.max(0, retryAfterSeconds - elapsedSeconds)
}

const startCountdown = (retryAfterSeconds: number) => {
  countdown.value = getRemainingSeconds(retryAfterSeconds)
  clearCountdownInterval()

  if (countdown.value <= 0) return

  countdownInterval = setInterval(() => {
    if (countdown.value !== null && countdown.value > 0) {
      countdown.value--
    } else {
      clearCountdownInterval()
    }
  }, 1000)
}

onMounted(() => {
  if (isRateLimit.value && props.task?.error_details?.retry_after_seconds) {
    startCountdown(props.task.error_details.retry_after_seconds)
  }
})

watch(
  () => props.task?.error_details,
  (details) => {
    clearCountdownInterval()
    if (details?.retry_after_seconds && RATE_LIMIT_TYPES.includes(details.error_type)) {
      startCountdown(details.retry_after_seconds)
    } else {
      countdown.value = null
    }
  },
)

onBeforeUnmount(() => {
  clearCountdownInterval()
})

const { mutate: restart } = useRestartTask()
const isRestarting = ref<TaskType | null>(null)

const restartTask = async (taskType: TaskType) => {
  isRestarting.value = taskType
  try {
    const task = props.task
    if (task) {
      await restart(task.id)
    }
  } catch (error) {
    console.error('❌ Error restarting task:', error)
  } finally {
    isRestarting.value = null
  }
}
</script>
