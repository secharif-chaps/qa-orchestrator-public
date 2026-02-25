<template>
  <component
    :is="href ? 'a' : 'span'"
    :href="href"
    :target="href ? target : undefined"
    :rel="href && target === '_blank' ? 'noopener noreferrer' : undefined"
    :class="[badgeClasses, href ? 'cursor-pointer hover:opacity-80' : '']"
    class="inline-flex items-center gap-1.5 font-medium transition-all"
  >
    <!-- Icon -->
    <i v-if="icon" :class="[icon, iconClasses]"></i>

    <!-- Dot indicator -->
    <span v-else-if="dot" :class="dotClasses" class="h-1.5 w-1.5 rounded-full"></span>

    <!-- Label -->
    <span v-if="label">{{ label }}</span>

    <!-- Custom content slot -->
    <slot v-else />

    <!-- Close button for dismissible badges -->
    <button
      v-if="dismissible"
      @click.prevent.stop="$emit('dismiss')"
      class="-mr-0.5 ml-1 transition-opacity hover:opacity-80"
      :aria-label="dismissLabel || 'Dismiss'"
    >
      <i class="fa fa-times" :class="closeIconClasses"></i>
    </button>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export type BadgeVariant =
  | 'sage'
  | 'almond'
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
  /** URL to link to - renders as <a> instead of <span> */
  href?: string
  /** Link target (e.g., '_blank' for new tab) */
  target?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'sage',
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
    case 'almond':
      return isOutline
        ? `${isRounded} bg-transparent text-almond-700 border border-almond-200 dark:text-almond-50`
        : `${isRounded} bg-almond-200 text-almond-950 border border-almond-200 dark:bg-almond-400/30 dark:text-almond-50 dark:border-almond-400/30`

    case 'success':
      return isOutline
        ? `${isRounded} bg-transparent text-success-light-content border border-success-stroke dark:text-success-50`
        : `${isRounded} bg-success-200 text-success-950 border border-success-200 dark:bg-success-400/30 dark:text-success-50 dark:border-success-400/30`

    case 'warning':
      return isOutline
        ? `${isRounded} bg-transparent text-warning-light-content border border-warning-stroke dark:text-warning-50`
        : `${isRounded} bg-warning-200 text-warning-950 border border-warning-200 dark:bg-warning-400/30 dark:text-warning-50 dark:border-warning-400/30`

    case 'error':
      return isOutline
        ? `${isRounded} bg-transparent text-error-light-content border border-error-stroke dark:text-error-50`
        : `${isRounded} bg-error-200 text-error-950 border border-error-200 dark:bg-error-400/30 dark:text-error-50 dark:border-error-400/30`

    case 'info':
      return isOutline
        ? `${isRounded} bg-transparent text-info-light-content border border-info-stroke dark:text-info-50`
        : `${isRounded} bg-info-200 text-info-950 border border-info-200 dark:bg-info-400/30 dark:text-info-50 dark:border-info-400/30`

    case 'accent':
      return isOutline
        ? `${isRounded} bg-transparent text-rose-700 border border-accent-stroke dark:text-rose-50`
        : `${isRounded} bg-rose-200 text-rose-950 border border-accent-200 dark:bg-rose-400/30 dark:text-rose-50 dark:border-accent-400/30`

    case 'slate':
      return isOutline
        ? `${isRounded} bg-transparent text-gray-500 border border-base-200 dark:text-sage-50`
        : `${isRounded} bg-base-200 text-sage-950 dark:bg-base-400/30 dark:text-sage-50 dark:border-base-400/30`

    default: // primary
      return isOutline
        ? `${isRounded} bg-transparent text-sage-700 border border-primary-stroke dark:text-sage-50`
        : `${isRounded} bg-sage-200 text-sage-950 border border-sage-200 dark:bg-sage-400/30 dark:text-sage-50 dark:border-sage-400/30`
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
