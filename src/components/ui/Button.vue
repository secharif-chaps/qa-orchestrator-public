<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[buttonClasses, dark ? 'dark' : '']"
    class="min-w-10 hover:cursor-pointer rounded-full inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-bg2 disabled:cursor-not-allowed"
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

type ButtonVariant = 'primary' | 'secondary' | 'ghost-primary' | 'ghost-black' | 'accent'
type ButtonSize = 'sm' | 'md' | 'lg'
type IconPosition = 'left' | 'right'

interface Props {
  variant?: ButtonVariant
  size?: ButtonSize
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  loading?: boolean
  icon?: string
  iconPosition?: IconPosition
  iconOnly?: boolean
  label?: string
  dark?: boolean
}

const {
  variant = 'primary',
  size = 'md',
  type = 'button',
  disabled = false,
  loading = false,
  icon,
  iconPosition = 'left',
  iconOnly = false,
  label,
} = defineProps<Props>()

defineEmits<{
  click: [event: MouseEvent]
}>()

const buttonClasses = computed(() => {
  const classes = []

  // Base styles

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

  // Variant styles
  switch (variant) {
    case 'primary':
      classes.push(
        'bg-sage-600 dark:bg-sage-300 dark:text-sage-900',
        'text-white',
        'hover:bg-sage-700',
        'active:bg-sage-800',
        'focus:ring-sage-600/30',
        'disabled:bg-sage-300',
        'disabled:text-sage-100',
        'shadow-sm hover:shadow-md',
      )
      break
    case 'secondary':
      classes.push(
        'bg-transparent',
        'text-sage-600 dark:text-sage-300',
        'border border-sage-600 dark:border-sage-300',
        'hover:bg-sage-50 dark:hover:bg-sage-200/10',
        'active:bg-sage-100 dark:active:bg-sage-900',
        'focus:ring-sage-600/30',
        'disabled:border-sage-300 dark:disabled:border-sage-700',
        'disabled:text-sage-300 dark:disabled:text-sage-700',
      )
      break
    case 'ghost-primary':
      classes.push(
        'bg-transparent',
        'text-sage-600 dark:text-sage-300',
        'hover:bg-sage-100 dark:hover:bg-sage-200/10',
        'active:bg-sage-100 dark:active:bg-sage-900',
        'focus:ring-sage-600/30',
        'disabled:text-sage-300 dark:disabled:text-sage-700',
      )
      break
    case 'ghost-black':
      classes.push(
        'bg-transparent',
        'text-gray-900 dark:text-gray-100',
        'hover:bg-gray-100 dark:hover:bg-gray-800',
        'active:bg-gray-200 dark:active:bg-gray-700',
        'focus:ring-gray-500/30',
        'disabled:text-gray-400 dark:disabled:text-gray-600',
      )
      break
    case 'accent':
      classes.push(
        'bg-tertiary',
        'text-black',
        'hover:bg-tertiary-300 dark:hover:bg-tertiary-400',
        'active:bg-tertiary-700',
        'focus:ring-tertiary-30',
        'disabled:bg-tertiary-20',
        'disabled:text-tertiary-100',
        'shadow-sm hover:shadow-md',
      )
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
  // Disabled state
  if (disabled || loading) {
    if (variant === 'primary') return 'text-sage-100 dark:text-sage-900'
    if (variant === 'accent') return 'text-rose-100 dark:text-sage-900'
    if (variant === 'secondary' || variant === 'ghost-primary') return 'text-sage-300 dark:text-sage-700'
    return 'text-sage-400 dark:text-gray-600'
  }

  switch (variant) {
    case 'primary':
      return 'text-white dark:text-sage-900'
    case 'accent':
      return 'text-black'
    case 'secondary':
    case 'ghost-primary':
      return 'text-sage-600 dark:text-sage-300'
    case 'ghost-black':
      return 'text-gray-900 dark:text-gray-100'
    default:
      return 'text-current'
  }
})
</script>
