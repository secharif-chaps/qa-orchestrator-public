<template>
  <div
    class="bg-base-200 rounded-card border-primary-stroke hover:shadow-shadow-2 group relative flex h-full min-h-52 cursor-pointer flex-col gap-4 overflow-hidden border p-6 transition-all duration-300"
    :class="{
      'cursor-not-allowed opacity-60': disabled,
      'hover:border-primary/50': !disabled && !isLoading,
    }"
    @click="handleClick"
  >
    <!-- Status Badge (top-right) -->
    <div class="absolute top-4 right-4">
      <Tag v-if="taskStatus" :variant="statusVariant" :label="statusLabel" size="xs" dot />
    </div>

    <!-- Header -->
    <div class="flex items-start gap-4">
      <div
        class="rounded-card flex size-8 flex-shrink-0 items-center justify-center transition-colors"
      >
        <i v-if="isLoading" class="fas fa-spinner fa-spin text-xl" :class="iconColorClass"></i>
        <i v-else :class="[icon, 'text-xl', iconColorClass]"></i>
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="mb-1 text-lg font-semibold transition-colors">
          {{ title }}
        </h3>
      </div>
    </div>

    <!-- AI Insights Preview -->
    <div v-if="hasInsights" class="flex flex-1 flex-col">
      <p class="text-secondary text-sm">
        {{ insights }}
      </p>
      <button
        class="text-secondary hover:text-secondary/80 mt-2 flex items-center gap-1 self-start text-xs font-medium transition-colors"
      >
        <span>{{ $t('company.analysisCard.viewMore', 'View more') }}</span>
        <i class="fas fa-arrow-right text-[10px]"></i>
      </button>
    </div>

    <!-- Loading State Overlay -->
    <div
      v-if="isLoading"
      class="bg-base-100/80 rounded-card absolute inset-0 flex items-center justify-center backdrop-blur-sm"
    >
      <div class="text-secondary flex items-center gap-3 text-base">
        <i class="fas fa-spinner fa-spin text-xl"></i>
        <span>{{ $t('company.analysisCard.loading', 'Analysis in progress...') }}</span>
      </div>
    </div>

    <!-- Error State Overlay -->
    <TaskErrorOverlay
      v-if="hasError"
      :error-details="errorDetails"
      :task-id="taskId"
      :task-updated-at="taskUpdatedAt"
      @restart="(id) => emit('restart', id)"
    />

    <!-- No Data State -->
    <div v-else-if="!hasInsights && !isLoading" class="border-primary-stroke mt-4 border-t pt-4">
      <p class="text-secondary text-sm italic">
        {{ $t('company.analysisCard.noData', 'No data available for this section') }}
      </p>
    </div>

    <!-- Disabled Overlay -->
    <div
      v-if="disabled"
      class="bg-base-100/80 rounded-card absolute inset-0 flex items-center justify-center backdrop-blur-sm"
    >
      <Tag
        variant="accent"
        :label="$t('company.analysisCard.comingSoon', 'Coming soon')"
        size="sm"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Tag from '@/components/ui/Tag.vue'
import TaskErrorOverlay from '@/components/company/TaskErrorOverlay.vue'
import type { AgentErrorDetails, TaskStatus } from '@/types/task'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

interface Props {
  title: string
  description: string
  icon: string
  insights?: string | null
  taskStatus?: TaskStatus | null
  errorDetails?: AgentErrorDetails | null
  disabled?: boolean
  taskId?: number | null
  taskUpdatedAt?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  insights: null,
  taskStatus: null,
  errorDetails: null,
  taskId: null,
  taskUpdatedAt: null,
})

const emit = defineEmits<{
  click: []
  restart: [taskId: number]
}>()

// Computed properties
const isLoading = computed(() => props.taskStatus === 'running' || props.taskStatus === 'pending')
const hasError = computed(() => props.taskStatus === 'error')
const hasInsights = computed(() => !!props.insights && props.insights.trim().length > 0)

const statusVariant = computed(() => {
  switch (props.taskStatus) {
    case 'succeeded':
      return 'success'
    case 'error':
      return 'error'
    case 'running':
      return 'warning'
    case 'pending':
      return 'info'
    default:
      return 'accent'
  }
})

const statusLabel = computed(() => {
  switch (props.taskStatus) {
    case 'succeeded':
      return t('company.analysisCard.status.succeeded', 'Completed')
    case 'error':
      return t('company.analysisCard.status.error', 'Error')
    case 'running':
      return t('company.analysisCard.status.running', 'In progress')
    case 'pending':
      return t('company.analysisCard.status.pending', 'Pending')
    default:
      return t('company.analysisCard.status.notStarted', 'Not started')
  }
})

const iconColorClass = computed(() => {
  if (props.disabled) {
    return 'text-secondary'
  }
  if (isLoading.value) {
    return 'text-warning-500'
  }
  if (hasError.value) {
    return 'text-error-500'
  }
  return 'text-secondary'
})

const handleClick = () => {
  if (!props.disabled) {
    emit('click')
  }
}
</script>

<style scoped>
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
