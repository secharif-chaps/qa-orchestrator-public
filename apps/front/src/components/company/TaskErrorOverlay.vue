<template>
  <div
    class="bg-base-100/80 rounded-card absolute inset-0 flex items-center justify-center p-6 backdrop-blur-sm"
  >
    <div class="flex w-full flex-col items-center gap-4">
      <!-- Rate limit with countdown -->
      <Alert
        v-if="isRateLimit && countdown !== null"
        variant="warning"
        :title="t('screen.company.analysisCard.error.rateLimit.title')"
        :description="
          countdown > 0
            ? t('screen.company.analysisCard.error.rateLimit.description', { seconds: countdown })
            : t('screen.company.analysisCard.error.recoverable.description')
        "
        icon="fa-clock"
      >
        <template #actions>
          <Button
            variant="secondary"
            size="sm"
            :label="
              countdown > 0
                ? t('screen.company.analysisCard.error.rateLimit.waitingLabel', {
                    seconds: countdown,
                  })
                : t('screen.company.analysisCard.error.retry')
            "
            :icon="countdown > 0 ? 'fa fa-hourglass-half' : 'fa fa-rotate-right'"
            :disabled="countdown > 0"
            :loading="isRetrying"
            @click="handleRetry"
          />
        </template>
      </Alert>

      <!-- Recoverable error (no specific countdown) -->
      <Alert
        v-else-if="isRecoverable"
        variant="warning"
        :title="t('screen.company.analysisCard.error.recoverable.title')"
        :description="t('screen.company.analysisCard.error.recoverable.description')"
        icon="fa-triangle-exclamation"
      >
        <template #actions>
          <Button
            variant="secondary"
            size="sm"
            :label="t('screen.company.analysisCard.error.retry')"
            icon="fa fa-rotate-right"
            :loading="isRetrying"
            @click="handleRetry"
          />
        </template>
      </Alert>

      <!-- Permanent / generic error -->
      <Alert
        v-else
        variant="danger"
        :title="t('screen.company.analysisCard.error.generic.title')"
        :description="t('screen.company.analysisCard.error.generic.description')"
        icon="fa-circle-xmark"
      >
        <template #actions>
          <Button
            variant="secondary"
            size="sm"
            :label="t('screen.company.analysisCard.error.retry')"
            icon="fa fa-rotate-right"
            :loading="isRetrying"
            @click="handleRetry"
          />
        </template>
      </Alert>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Alert, Button } from '@owlint/feathers-vue'
import type { AgentErrorDetails } from '@/types/task'

const { t } = useI18n()

interface Props {
  errorDetails?: AgentErrorDetails | null
  taskId?: number | null
  taskUpdatedAt?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  errorDetails: null,
  taskId: null,
  taskUpdatedAt: null,
})

const emit = defineEmits<{
  restart: [taskId: number]
}>()

const isRetrying = ref(false)
const countdown = ref<number | null>(null)
let countdownInterval: ReturnType<typeof setInterval> | null = null

const RATE_LIMIT_TYPES = ['rate_limit_llm', 'rate_limit_api']

const isRateLimit = computed(
  () => props.errorDetails != null && RATE_LIMIT_TYPES.includes(props.errorDetails.error_type),
)

const isRecoverable = computed(() => props.errorDetails?.is_recoverable === true)

// Compute remaining seconds accounting for time already elapsed since the error occurred.
// This ensures the countdown resumes correctly after page refresh or modal close/reopen.
const getRemainingSeconds = (retryAfterSeconds: number): number => {
  if (!props.taskUpdatedAt) return retryAfterSeconds
  const elapsedSeconds = Math.floor((Date.now() - new Date(props.taskUpdatedAt).getTime()) / 1000)
  return Math.max(0, retryAfterSeconds - elapsedSeconds)
}

const clearCountdownInterval = () => {
  if (countdownInterval !== null) {
    clearInterval(countdownInterval)
    countdownInterval = null
  }
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

// Start countdown when component mounts (if rate limit with retry_after)
onMounted(() => {
  if (isRateLimit.value && props.errorDetails?.retry_after_seconds) {
    startCountdown(props.errorDetails.retry_after_seconds)
  }
})

// Restart countdown if errorDetails change (e.g., task retried and failed again)
watch(
  () => props.errorDetails,
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

const handleRetry = async () => {
  if (!props.taskId) return
  isRetrying.value = true
  try {
    emit('restart', props.taskId)
  } finally {
    isRetrying.value = false
  }
}
</script>
