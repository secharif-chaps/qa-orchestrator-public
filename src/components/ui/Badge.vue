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
      return isPrimary ? 'bg-success-500 text-white' : 'bg-success-light text-success-light-content'

    case 'warning':
      return isPrimary ? 'bg-warning-500 text-white' : 'bg-warning-light text-warning-light-content'

    case 'error':
      return isPrimary ? 'bg-error-500 text-white' : 'bg-error-light text-error-light-content'

    case 'info':
      return isPrimary ? 'bg-info-500 text-white' : 'bg-info-light text-info-light-content'

    case 'accent':
      return isPrimary ? 'bg-accent-800 text-white' : 'bg-accent-light text-accent-light-content'

    case 'slate':
      return isPrimary ? 'bg-gray-800 text-white' : 'bg-base-200 text-primary-light-content'

    default: // primary
      return isPrimary ? 'bg-primary text-white' : 'bg-primary-light text-primary-light-content'
  }
})

// Combined badge classes
const badgeClasses = computed(() => {
  return [sizeClasses.value, colorVariantClasses.value].join(' ')
})
</script>
