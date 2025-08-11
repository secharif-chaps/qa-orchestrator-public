<template>
  <span :class="badgeClasses" class="inline-flex items-center gap-1.5 font-medium transition-all">
    <!-- Icon -->
    <i v-if="icon" :class="[icon, iconClasses]"></i>

    <!-- Dot indicator -->
    <span v-else-if="dot" :class="dotClasses" class="w-1.5 h-1.5 rounded-full"></span>

    <!-- Label -->
    <span v-if="label">{{ label }}</span>

    <!-- Custom content slot -->
    <slot v-else />

    <!-- Close button for dismissible badges -->
    <button
      v-if="dismissible"
      @click="$emit('dismiss')"
      class="ml-1 -mr-0.5 hover:opacity-80 transition-opacity"
      :aria-label="dismissLabel || 'Dismiss'"
    >
      <i class="fa fa-times" :class="closeIconClasses"></i>
    </button>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type BadgeVariant = 'primary' | 'success' | 'warning' | 'error' | 'info' | 'slate'
type BadgeSize = 'xs' | 'sm' | 'md' | 'lg'

interface Props {
  variant?: BadgeVariant
  size?: BadgeSize
  label?: string
  icon?: string
  dot?: boolean
  rounded?: boolean
  gradient?: boolean
  dismissible?: boolean
  dismissLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'sm',
  rounded: false,
  gradient: true,
  dot: false,
  dismissible: false,
})

defineEmits<{
  dismiss: []
}>()

// Size classes
const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'px-2 py-0.5 text-xs'
    case 'sm':
      return 'px-2.5 py-1 text-xs'
    case 'md':
      return 'px-3 py-1.5 text-sm'
    case 'lg':
      return 'px-4 py-2 text-base'
    default:
      return 'px-2.5 py-1 text-xs'
  }
})

// Icon size classes
const iconClasses = computed(() => {
  switch (props.size) {
    case 'xs':
    case 'sm':
      return 'text-[10px]'
    case 'md':
      return 'text-xs'
    case 'lg':
      return 'text-sm'
    default:
      return 'text-[10px]'
  }
})

// Close icon size
const closeIconClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-[8px]'
    case 'sm':
      return 'text-[9px]'
    case 'md':
      return 'text-[10px]'
    case 'lg':
      return 'text-xs'
    default:
      return 'text-[9px]'
  }
})

// Variant classes with gradient support
const variantClasses = computed(() => {
  const isRounded = props.rounded ? 'rounded-full' : 'rounded-md'
  
  // Gradient or solid background
  const gradient = props.gradient ? 'bg-gradient-to-br' : ''

  switch (props.variant) {
    case 'success':
      return props.gradient
        ? `${isRounded} ${gradient} from-green-200 dark:from-green-400/10 to-green-300 dark:to-green-400/30 text-green-700 dark:text-green-400`
        : `${isRounded} bg-green-200 dark:bg-green-400/20 text-green-700 dark:text-green-400`

    case 'warning':
      return props.gradient
        ? `${isRounded} ${gradient} from-yellow-200 dark:from-yellow-400/10 to-yellow-300 dark:to-yellow-400/30 text-yellow-700 dark:text-yellow-400`
        : `${isRounded} bg-yellow-200 dark:bg-yellow-400/20 text-yellow-700 dark:text-yellow-400`

    case 'error':
      return props.gradient
        ? `${isRounded} ${gradient} from-red-200 dark:from-red-400/10 to-red-300 dark:to-red-400/30 text-red-700 dark:text-red-400`
        : `${isRounded} bg-red-200 dark:bg-red-400/20 text-red-700 dark:text-red-400`

    case 'info':
      return props.gradient
        ? `${isRounded} ${gradient} from-blue-200 dark:from-blue-400/10 to-blue-300 dark:to-blue-400/30 text-blue-700 dark:text-blue-400`
        : `${isRounded} bg-blue-200 dark:bg-blue-400/20 text-blue-700 dark:text-blue-400`

    case 'slate':
      // Slate is always subtle, no gradient
      return `${isRounded} bg-slate-200 dark:bg-slate-700/30 text-slate-700 dark:text-slate-400`

    default: // primary
      return props.gradient
        ? `${isRounded} ${gradient} from-primary/20 dark:from-primary/10 to-primary/30 dark:to-primary/20 text-primary`
        : `${isRounded} bg-primary/20 dark:bg-primary/15 text-primary`
  }
})

// Dot color classes
const dotClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-green-500'
    case 'warning':
      return 'bg-yellow-500'
    case 'error':
      return 'bg-red-500'
    case 'info':
      return 'bg-blue-500'
    case 'slate':
      return 'bg-secondary'
    default: // primary
      return 'bg-primary'
  }
})

// Combined badge classes
const badgeClasses = computed(() => {
  return [sizeClasses.value, variantClasses.value].join(' ')
})
</script>
