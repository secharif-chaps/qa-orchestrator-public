<template>
  <div
    class="relative p-4 rounded-xl shadow-lg shadow-bg2 border-2 border-border-2 transition-all duration-300 cursor-pointer transform hover:scale-105 w-40"
    :class="getNodeClass()"
    @click="handleClick"
  >
    <!-- Task icon and info -->
    <div class="flex items-center space-x-3">
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center relative"
        :class="getIconContainerClass()"
      >
        <i v-if="data.status === 'running'" class="fa fa-spinner-third animate-spin"></i>
        <i v-else :class="getTaskIcon(data.type)" class="text-lg"></i>

        <!-- Animated ring for running tasks -->
        <div
          v-if="data.status === 'running'"
          class="absolute inset-0 rounded-lg border-2 border-current opacity-75"
        ></div>
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-sm truncate">{{ data.name }}</h3>
        <!-- <p class="text-xs text-gray-500 truncate">{{ data.description }}</p> -->

        <!-- Error message with restart tooltip -->
        <div
          v-if="data.status === 'error' && data.error"
          class="mt-1 text-xs text-red-600 truncate relative group"
        >
          {{ data.error }}

          <!-- Restart tooltip -->
          <div
            class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 bg-gray-900 text-white text-xs rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto whitespace-nowrap z-10"
          >
            <button
              @click.stop="$emit('restart', data.type)"
              class="flex items-center gap-1 px-2 py-1 text-white hover:text-gray-200 hover:bg-gray-800 rounded transition-colors duration-150"
            >
              <i class="fa fa-rotate-right text-xs"></i>
              <span>Restart</span>
            </button>
            <!-- Tooltip arrow -->
            <div
              class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Connection handles -->
    <Handle
      v-if="!data.first"
      type="target"
      :position="Position.Left"
      :class="{
        '!border-red-400': data.status === 'error',
        '!border-green-400': data.status === 'succeeded',
        '!border-orange-400': data.status === 'running',
        '!border-gray-400': data.status === 'pending',
        '!border-slate-200': data.status === null,
      }"
      class="!size-3 !border-2 !bg-white dark:!bg-slate-800"
    />
    <Handle
      v-if="!data.last"
      type="source"
      :position="Position.Right"
      class="!size-3 !border-2 !bg-white dark:!bg-slate-800 !right-0"
      :class="{
        '!border-red-400': data.status === 'error',
        '!border-green-400': data.status === 'succeeded',
        '!border-orange-400': data.status === 'running',
        '!border-gray-400': data.status === 'pending',
        '!border-slate-200': data.status === null,
      }"
    />
  </div>
</template>

<script setup lang="ts">
import { Handle, Position, type NodeProps } from '@vue-flow/core'
import type { TaskType, TaskStatus } from '@/types/task'

interface TaskNodeData {
  type: TaskType
  name: string
  description: string
  status: TaskStatus | null
  error: string | null
  canTrigger: boolean
  first: boolean
  last: boolean
}

const props = defineProps<NodeProps<TaskNodeData>>()

defineEmits<{
  trigger: [taskType: TaskType]
  restart: [taskType: TaskType]
}>()

// Get task icon
const getTaskIcon = (taskType: TaskType): string => {
  const iconMap: Record<TaskType, string> = {
    profile: 'fas fa-user',
    digital: 'fas fa-globe',
    timeline: 'fas fa-history',
    products: 'fas fa-box',
    jobs: 'fas fa-briefcase',
    csr: 'fas fa-leaf',
    press: 'fas fa-newspaper',
    team: 'fas fa-users',
  }
  return iconMap[taskType] || 'fas fa-question'
}

// Get node styling based on status
const getNodeClass = (): string => {
  const baseClasses = 'bg-bg1 border-gray-200 dark:border-slate-700'

  switch (props.data.status) {
    case 'succeeded':
      return `${baseClasses} border-green-400 shadow-green-100 dark:shadow-slate-900`
    case 'error':
      return `${baseClasses} border-red-400 shadow-red-100 dark:shadow-slate-900`
    case 'running':
      return `${baseClasses} border-orange-400 shadow-orange-100 dark:shadow-slate-900`
    case 'pending':
      return `${baseClasses} border-blue-400 shadow-blue-100 dark:shadow-slate-900`
    default:
      if (props.data.canTrigger) {
        return `${baseClasses} border-blue-300 shadow-blue-50 hover:shadow-blue-200 dark:shadow-slate-900`
      }
      return `${baseClasses} opacity-60 dark:opacity-40`
  }
}

// Get icon container styling
const getIconContainerClass = (): string => {
  switch (props.data.status) {
    case 'succeeded':
      return 'bg-green-100 text-green-600 dark:bg-green-400/10 dark:text-green-400'
    case 'error':
      return 'bg-red-100 text-red-600 dark:bg-red-400/10 dark:text-red-400'
    case 'running':
      return 'bg-orange-100 text-orange-600 dark:bg-orange-400/10 dark:text-orange-400'
    case 'pending':
      return 'bg-blue-100 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400'
    default:
      if (props.data.canTrigger) {
        return 'bg-blue-50 text-blue-500 dark:bg-blue-400/10 dark:text-blue-400'
      }
      return 'bg-gray-100 text-gray-400 dark:bg-gray-400/10 dark:text-gray-400'
  }
}

// Handle node click
const handleClick = () => {
  if (props.data.canTrigger && !props.data.status) {
    // Trigger task on click if available
  }
}
</script>
