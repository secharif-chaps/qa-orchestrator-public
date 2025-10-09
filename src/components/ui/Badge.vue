<template>
  <div :class="badgeClasses" class="flex items-center justify-center rounded-full flex-shrink-0">
    <!-- Icon -->
    <i v-if="icon" :class="[icon, iconClasses]"></i>

    <!-- Number -->
    <span v-else-if="number !== undefined" :class="numberClasses">{{ displayNumber }}</span>

    <!-- Logo/Custom content slot -->
    <slot v-else />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export type BadgeVariant = 'primary' | 'secondary'
export type BadgeColor = 'primary' | 'success' | 'warning' | 'error' | 'info' | 'accent' | 'slate'
export type BadgeSize = 'xs' | 'sm' | 'md' | 'lg'

interface Props {
  variant?: BadgeVariant
  color?: BadgeColor
  size?: BadgeSize
  icon?: string
  number?: number
  maxNumber?: number
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  color: 'primary',
  size: 'md',
  maxNumber: 99,
})

// Display number with max limit (e.g., 99+)
const displayNumber = computed(() => {
  if (props.number === undefined) return ''
  return props.number > props.maxNumber ? `${props.maxNumber}+` : props.number.toString()
})

// Size classes
const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'w-5 h-5'
    case 'sm':
      return 'w-6 h-6'
    case 'md':
      return 'w-10 h-10'
    case 'lg':
      return 'w-12 h-12'
    default:
      return 'w-10 h-10'
  }
})

// Icon size classes
const iconClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-[10px]'
    case 'sm':
      return 'text-xs'
    case 'md':
      return 'text-base'
    case 'lg':
      return 'text-lg'
    default:
      return 'text-base'
  }
})

// Number text size classes
const numberClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-[10px] font-semibold'
    case 'sm':
      return 'text-xs font-semibold'
    case 'md':
      return 'text-sm font-semibold'
    case 'lg':
      return 'text-base font-semibold'
    default:
      return 'text-sm font-semibold'
  }
})

// Badge color and variant classes
const colorVariantClasses = computed(() => {
  const isPrimary = props.variant === 'primary'

  switch (props.color) {
    case 'success':
      return isPrimary
        ? 'bg-success-500 dark:bg-success-400 text-white dark:text-success-800'
        : 'bg-success-light dark:bg-success-400/20 text-success-light-content dark:text-success-400'

    case 'warning':
      return isPrimary
        ? 'bg-warning-500 text-white dark:text-warning-900'
        : 'bg-warning-light dark:bg-warning-400/20 text-warning-light-content dark:text-warning-400'

    case 'error':
      return isPrimary
        ? 'bg-error-500 text-white dark:text-error-900'
        : 'bg-error-light dark:bg-error-400/20 text-error-light-content dark:text-error-400'

    case 'info':
      return isPrimary
        ? 'bg-info-500 text-white dark:text-info-800'
        : 'bg-info-light dark:bg-info-400/20 text-info-light-content dark:text-info-400'

    case 'accent':
      return isPrimary
        ? 'bg-accent-800 dark:bg-accent-300 text-white dark:text-accent-800'
        : 'bg-accent-light dark:bg-accent-400/20 text-accent-light-content dark:text-accent-400'

    case 'slate':
      return isPrimary
        ? 'bg-gray-800 dark:bg-gray-300 text-white dark:text-gray-800'
        : 'bg-base-200 dark:bg-gray-400/30 text-primary-light-content dark:text-gray-200'

    default: // primary
      return isPrimary
        ? 'bg-primary text-white dark:bg-sage-300 text-white dark:text-sage-900'
        : 'bg-primary-light dark:bg-sage-400/30 text-primary-light-content dark:text-sage-200'
  }
})

// Combined badge classes
const badgeClasses = computed(() => {
  return [sizeClasses.value, colorVariantClasses.value].join(' ')
})
</script>
