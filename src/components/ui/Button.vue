<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClasses"
    class="hover:cursor-pointer inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-bg2 disabled:cursor-not-allowed"
    @click="$emit('click', $event)"
  >
    <!-- Loading Spinner -->
    <i
      v-if="loading"
      class="fa-solid fa-spinner animate-spin fa-fw"
      :class="[iconSizeClasses, iconColorClasses]"
    ></i>

    <!-- Left Icon -->
    <i
      v-else-if="icon && iconPosition === 'left'"
      :class="[icon, 'fa-fw', iconSizeClasses, iconColorClasses]"
    ></i>

    <!-- Button Text -->
    <span v-if="!iconOnly">
      <slot>{{ label }}</slot>
    </span>

    <!-- Right Icon -->
    <i
      v-if="!loading && icon && iconPosition === 'right'"
      :class="[icon, 'fa-fw', iconSizeClasses, iconColorClasses]"
    ></i>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type ButtonVariant = 'primary' | 'secondary' | 'tertiary'
type ButtonColor = 'neutral' | 'danger' | 'warning'
type ButtonSize = 'sm' | 'md' | 'lg'
type IconPosition = 'left' | 'right'

interface Props {
  variant?: ButtonVariant
  color?: ButtonColor
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  icon?: string
  iconPosition?: IconPosition
  iconOnly?: boolean
  label?: string
  rounded?: boolean
}

const {
  variant = 'primary',
  color = 'neutral',
  size = 'md',
  type = 'button',
  disabled = false,
  loading = false,
  icon,
  iconPosition = 'left',
  iconOnly = false,
  label,
  rounded = false,
} = defineProps<Props>()

defineEmits<{
  click: [event: MouseEvent]
}>()

const buttonClasses = computed(() => {
  const classes = []

  // Base styles
  classes.push(rounded ? 'rounded-full' : 'rounded-lg')

  // Size classes
  switch (size) {
    case 'sm':
      classes.push(iconOnly ? 'w-8 h-8' : 'px-3 py-1.5', 'text-sm')
      break
    case 'lg':
      classes.push(iconOnly ? 'w-12 h-12' : 'px-6 py-3', 'text-lg')
      break
    default: // md
      classes.push(iconOnly ? 'w-10 h-10' : 'px-4 py-2', 'text-base')
  }

  // Variant and color combination styles
  switch (variant) {
    case 'primary':
      if (color === 'danger') {
        classes.push(
          'bg-gradient-to-br from-red-500 to-red-600',
          'text-white',
          'hover:from-red-600 hover:to-red-700',
          'focus:ring-red-500/30',
          'disabled:from-red-400/20 disabled:to-bg1',
          'disabled:text-white/20',
          'shadow-sm hover:shadow-md',
        )
      } else if (color === 'warning') {
        classes.push(
          'bg-gradient-to-br from-orange-500 to-orange-600',
          'text-white',
          'hover:from-orange-600 hover:to-orange-700',
          'focus:ring-orange-500/30',
          'disabled:from-orange-400/20 disabled:to-bg2/50',
          'disabled:text-white/20',
          'shadow-sm hover:shadow-md',
        )
      } else {
        // neutral
        classes.push(
          'bg-gradient-to-br from-primary to-primary/90',
          'text-white',
          'hover:from-primary/90 hover:to-primary/80',
          'focus:ring-primary/30',
          'disabled:from-bg2 disabled:to-bg2/50',
          'disabled:text-white/20',
          'shadow-sm hover:shadow-md',
        )
      }
      break
    case 'secondary':
      if (color === 'danger') {
        classes.push(
          'bg-bg1',
          'text-red-600',
          'border border-border-2',
          'hover:bg-red-600/10 hover:border-red-600/30',
          'focus:ring-red-500/30',
          'disabled:from-bg1/50 disabled:to-bg1/40',
          'disabled:text-secondary/50',
          'disabled:border-border-2/50',
        )
      } else if (color === 'warning') {
        classes.push(
          'bg-bg1',
          'text-orange-600',
          'border border-border-2',
          'hover:bg-orange-600/10 hover:border-orange-600/30',
          'focus:ring-orange-500/30',
          'disabled:from-bg1/50 disabled:to-bg1/40',
          'disabled:text-secondary/50 ',
          'disabled:border-border-2/50',
        )
      } else {
        // neutral
        classes.push(
          'bg-bg1',
          'text-primary',
          'border border-border-2',
          'hover:bg-primary/10 hover:border-primary/30',
          'focus:ring-primary/30',
          'disabled:from-bg1/50 disabled:to-bg1/40',
          'disabled:text-secondary/50',
          'disabled:border-border-2/50',
        )
      }
      break
    case 'tertiary':
      if (color === 'danger') {
        classes.push(
          'bg-transparent',
          'text-red-600',
          'hover:text-red-600/80',
          'hover:bg-red-600/10 hover:border-red-600/30',
          'focus:ring-red-500/30',
          'disabled:text-secondary/50',
          'disabled:hover:bg-transparent',
        )
      } else if (color === 'warning') {
        classes.push(
          'bg-transparent',
          'text-orange-600',
          'hover:text-orange-600/80',
          'hover:bg-orange-600/10 hover:border-orange-600/30',
          'focus:ring-orange-500/30',
          'disabled:text-secondary/50',
          'disabled:hover:bg-transparent',
        )
      } else {
        // neutral
        classes.push(
          'bg-transparent',
          'text-primary',
          'hover:text-primary/80',
          'hover:bg-primary/10 hover:border-primary/30',
          'focus:ring-primary/30',
          'disabled:text-secondary/50',
          'disabled:hover:bg-transparent',
        )
      }
      break
  }

  return classes.join(' ')
})

const iconSizeClasses = computed(() => {
  switch (size) {
    case 'sm':
      return 'text-sm'
    case 'lg':
      return 'text-lg'
    default:
      return 'text-base'
  }
})

const iconColorClasses = computed(() => {
  if (variant === 'primary') {
    return 'text-white'
  }

  if (color === 'danger') {
    return 'text-red-600'
  }

  if (color === 'warning') {
    return 'text-orange-600'
  }

  return 'text-primary' // neutral secondary/tertiary
})
</script>
