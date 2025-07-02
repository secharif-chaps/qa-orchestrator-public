<template>
  <div
    class="relative p-4 rounded-xl shadow-lg border-2 transition-all duration-300 cursor-pointer transform hover:scale-105 w-40 "
    :class="getNodeClass()"
    @click="handleClick"
  >

    <!-- Task icon and info -->
    <div class="flex items-center space-x-3">
      <div 
        class="w-10 h-10 rounded-lg flex items-center justify-center relative "
        :class="getIconContainerClass()"
      >
        

        <i v-if="data.status === 'running'" class="fa fa-spinner-third animate-spin"></i>
        <i v-else
          :class="getTaskIcon(data.type)"
          class="text-lg"
        ></i>


        <!-- Animated ring for running tasks -->
        <div 
          v-if="data.status === 'running'"
          class="absolute inset-0 rounded-lg border-2 border-current  opacity-75"
        ></div>
      </div>
      
      <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-sm truncate">{{ data.name }}</h3>
        <!-- <p class="text-xs text-gray-500 truncate">{{ data.description }}</p> -->

        
        <!-- Error message -->
        <div v-if="data.status === 'error' && data.error" class="mt-1 text-xs text-red-600 truncate">
          {{ data.error }}
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
      class=" !size-3 !border-2 !bg-white"
    />
    <Handle
      v-if="!data.last"
      type="source"
      :position="Position.Right"
      class="!size-3 !border-2 !bg-white !right-0"
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
import type { TaskType, TaskStatus } from '~/types/task'

interface TaskNodeData {
  type: TaskType
  name: string
  description: string
  status: TaskStatus | null
  error: string | null
  canTrigger: boolean
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
    team: 'fas fa-users'
  }
  return iconMap[taskType] || 'fas fa-question'
}

// Get node styling based on status
const getNodeClass = (): string => {
  const baseClasses = 'bg-white border-gray-200'
  
  switch (props.data.status) {
    case 'succeeded':
      return `${baseClasses} border-green-400 shadow-green-100`
    case 'error':
      return `${baseClasses} border-red-400 shadow-red-100`
    case 'running':
      return `${baseClasses} border-orange-400 shadow-orange-100 `
    case 'pending':
      return `${baseClasses} border-blue-400 shadow-blue-100`
    default:
      if (props.data.canTrigger) {
        return `${baseClasses} border-blue-300 shadow-blue-50 hover:shadow-blue-200`
      }
      return `${baseClasses} opacity-60`
  }
}

// Get icon container styling
const getIconContainerClass = (): string => {
  switch (props.data.status) {
    case 'succeeded':
      return 'bg-green-100 text-green-600'
    case 'error':
      return 'bg-red-100 text-red-600'
    case 'running':
      return 'bg-orange-100 text-orange-600'
    case 'pending':
      return 'bg-blue-100 text-blue-600'
    default:
      if (props.data.canTrigger) {
        return 'bg-blue-50 text-blue-500'
      }
      return 'bg-gray-100 text-gray-400'
  }
}

// Get status indicator styling
const getStatusIndicatorClass = (): string => {
  switch (props.data.status) {
    case 'succeeded':
      return 'bg-green-500'
    case 'error':
      return 'bg-red-500'
    case 'running':
      return 'bg-orange-500'
    case 'pending':
      return 'bg-blue-500'
    default:
      return 'bg-gray-300'
  }
}

// Handle node click
const handleClick = () => {
  if (props.data.canTrigger && !props.data.status) {
    // Trigger task on click if available
  }
}
</script>

