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

export type BadgeVariant =
  | 'primary'
  | 'secondary'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'accent'
  | 'slate'
export type BadgeSize = 'xs' | 'sm' | 'md' | 'lg'
export type BadgeAppearance = 'light' | 'outline'

interface Props {
  variant?: BadgeVariant
  size?: BadgeSize
  appearance?: BadgeAppearance
  label?: string
  icon?: string
  dot?: boolean
  rounded?: boolean
  dismissible?: boolean
  dismissLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'sm',
  appearance: 'light',
  rounded: false,
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

// Variant classes with appearance support (light/outline)
const variantClasses = computed(() => {
  const isRounded = 'rounded-full'
  const isOutline = props.appearance === 'outline'

  switch (props.variant) {
    case 'secondary':
      return isOutline
        ? `${isRounded} bg-transparent text-secondary-light-content border-2 border-secondary-stroke`
        : `${isRounded} bg-secondary-200 text-secondary-950 border border-secondary-200`

    case 'success':
      return isOutline
        ? `${isRounded} bg-transparent text-success-light-content border-2 border-success-stroke`
        : `${isRounded} bg-success-200 text-success-950 border border-success-200`

    case 'warning':
      return isOutline
        ? `${isRounded} bg-transparent text-warning-light-content border-2 border-warning-stroke`
        : `${isRounded} bg-warning-200 text-warning-950 border border-warning-200`

    case 'error':
      return isOutline
        ? `${isRounded} bg-transparent text-error-light-content border-2 border-error-stroke`
        : `${isRounded} bg-error-200 text-error-950 border border-error-200`

    case 'info':
      return isOutline
        ? `${isRounded} bg-transparent text-info-light-content border-2 border-info-stroke`
        : `${isRounded} bg-info-200 text-info-950 border border-info-200`

    case 'accent':
      return isOutline
        ? `${isRounded} bg-transparent text-accent-light-content border-2 border-accent-stroke`
        : `${isRounded} bg-accent-200 text-accent-950 border border-accent-200`

    case 'slate':
      return isOutline
        ? `${isRounded} bg-transparent text-primary-light-content border-2 border-base-200`
        : `${isRounded} bg-base-200 text-primary-950`

    default: // primary
      return isOutline
        ? `${isRounded} bg-transparent text-primary-light-content border-2 border-primary-stroke`
        : `${isRounded} bg-primary-200 text-primary-950 border border-primary-200`
  }
})

// Dot color classes using semantic tokens
const dotClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-success'
    case 'warning':
      return 'bg-warning'
    case 'error':
      return 'bg-error'
    case 'info':
      return 'bg-info'
    case 'accent':
      return 'bg-accent'
    case 'slate':
      return 'bg-base-300'
    default: // primary
      return 'bg-primary'
  }
})

// Combined badge classes
const badgeClasses = computed(() => {
  return [sizeClasses.value, variantClasses.value].join(' ')
})
</script>
