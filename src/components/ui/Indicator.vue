<template>
  <span :class="containerClasses" class="inline-flex items-center justify-center rounded-full">
    <span :class="dotClasses" class="rounded-full"></span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export type IndicatorColor =
  | 'primary'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'accent'
  | 'slate'
export type IndicatorSize = 'sm' | 'md' | 'lg'

interface Props {
  color?: IndicatorColor
  size?: IndicatorSize
}

const props = withDefaults(defineProps<Props>(), {
  color: 'primary',
  size: 'md',
})

// Size classes for container and dot
const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return {
        container: 'w-3 h-3',
        dot: 'w-1.5 h-1.5',
      }
    case 'md':
      return {
        container: 'w-4 h-4',
        dot: 'w-2 h-2',
      }
    case 'lg':
      return {
        container: 'w-6 h-6',
        dot: 'w-3 h-3',
      }
    default:
      return {
        container: 'w-4 h-4',
        dot: 'w-2 h-2',
      }
  }
})

// Color classes using -200 for container and varying shades for dot
const colorClasses = computed(() => {
  switch (props.color) {
    case 'success':
      return {
        container: 'bg-success-200 dark:bg-success-400/30',
        dot: 'bg-success-800 dark:bg-success-400',
      }
    case 'warning':
      return {
        container: 'bg-warning-200 dark:bg-warning-400/30',
        dot: 'bg-warning-800 dark:bg-warning-400',
      }
    case 'error':
      return {
        container: 'bg-error-200 dark:bg-error-400/30',
        dot: 'bg-error-800 dark:bg-error-400',
      }
    case 'info':
      return {
        container: 'bg-info-200 dark:bg-info-400/30',
        dot: 'bg-info-800 dark:bg-info-400',
      }
    case 'accent':
      return {
        container: 'bg-accent-200 dark:bg-accent-400/30',
        dot: 'bg-accent-800 dark:bg-accent-400',
      }
    case 'slate':
      return {
        container: 'bg-gray-200 dark:bg-gray-400/30',
        dot: 'bg-gray-800 dark:bg-gray-400',
      }
    default: // primary
      return {
        container: 'bg-primary-200 dark:bg-primary-400/30',
        dot: 'bg-primary-800 dark:bg-primary-400',
      }
  }
})

// Combined classes
const containerClasses = computed(() => {
  return [sizeClasses.value.container, colorClasses.value.container].join(' ')
})

const dotClasses = computed(() => {
  return [sizeClasses.value.dot, colorClasses.value.dot].join(' ')
})
</script>
