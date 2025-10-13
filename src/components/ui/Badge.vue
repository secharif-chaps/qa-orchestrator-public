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
export type BadgeColor =
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'accent'
  | 'slate'
  | 'sage'
  | 'almond'
  | 'yellow'
  | 'indigo'
  | 'orange'
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
  color: 'sage',
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
        ? 'bg-success-600 dark:bg-success-400 text-white dark:text-success-800'
        : 'bg-success-light dark:bg-success-400/20 text-success-light-content dark:text-success-400'

    case 'warning':
      return isPrimary
        ? 'bg-warning-600 text-white dark:bg-warning-500 dark:text-warning-950'
        : 'bg-warning-light dark:bg-warning-400/20 text-warning-light-content dark:text-warning-400'

    case 'error':
      return isPrimary
        ? 'bg-error-600 text-white dark:bg-error-500 dark:text-error-950'
        : 'bg-error-light dark:bg-error-400/20 text-error-light-content dark:text-error-400'

    case 'info':
      return isPrimary
        ? 'bg-info-600 text-white dark:bg-info-500 dark:text-info-950'
        : 'bg-info-light dark:bg-info-400/20 text-info-light-content dark:text-info-400'

    case 'accent':
      return isPrimary
        ? 'bg-rose-800 dark:bg-rose-300 text-white dark:text-rose-800'
        : 'bg-rose-100 dark:bg-rose-400/20 text-rose-700 dark:text-rose-400'

    case 'slate':
      return isPrimary
        ? 'bg-gray-800 dark:bg-gray-300 text-white dark:text-gray-800'
        : 'bg-gray-100 dark:bg-gray-400/30 text-secondary dark:text-gray-200'

    case 'sage':
      return isPrimary
        ? 'bg-sage-800 dark:bg-sage-300 text-white dark:text-sage-800'
        : 'bg-sage-100 dark:bg-sage-400/30 text-secondary dark:text-sage-200'

    case 'almond':
      return isPrimary
        ? 'bg-almond-500 dark:bg-almond-300 text-white dark:text-almond-800'
        : 'bg-almond-100 dark:bg-almond-400/30 text-almond-700 dark:text-almond-200'

    case 'yellow':
      return isPrimary
        ? 'bg-yellow-500 dark:bg-yellow-300 text-white dark:text-yellow-800'
        : 'bg-yellow-100 dark:bg-yellow-400/30 text-yellow-700 dark:text-yellow-200'

    case 'orange':
      return isPrimary
        ? 'bg-orange-500 dark:bg-orange-300 text-white dark:text-orange-800'
        : 'bg-orange-100 dark:bg-orange-400/30 text-secondary dark:text-orange-200'

    case 'indigo':
      return isPrimary
        ? 'bg-indigo-500 dark:bg-indigo-300 text-white dark:text-indigo-800'
        : 'bg-indigo-100 dark:bg-indigo-400/30 text-indigo-700 dark:text-indigo-200'

    default: // sage
      return isPrimary
        ? 'bg-primary text-white dark:bg-sage-300 text-white dark:text-sage-900'
        : 'bg-sage-light dark:bg-sage-400/30 text-sage-700 dark:text-sage-200'
  }
})

// Combined badge classes
const badgeClasses = computed(() => {
  return [sizeClasses.value, colorVariantClasses.value].join(' ')
})
</script>
