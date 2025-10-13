<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[buttonClasses, dark ? 'dark' : '']"
    class="min-w-10 hover:cursor-pointer rounded-full inline-flex items-center justify-center gap-2 font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-bg2 disabled:cursor-not-allowed"
    @click="$emit('click', $event)"
  >
    <!-- Loading Spinner -->
    <i v-if="loading" class="fa-solid fa-spinner animate-spin fa-fw" :class="[iconSizeClasses]"></i>

    <!-- Left Icon -->
    <i v-else-if="icon && iconPosition === 'left'" :class="[icon, 'fa-fw', iconSizeClasses]"></i>

    <!-- Button Text -->
    <span v-if="!iconOnly">
      <slot>{{ label }}</slot>
    </span>

    <!-- Right Icon -->
    <i
      v-if="!loading && icon && iconPosition === 'right'"
      :class="[icon, 'fa-fw', iconSizeClasses]"
    ></i>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type ButtonVariant = 'primary' | 'secondary' | 'tertiary' | 'accent'
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
        'bg-sage-800 dark:bg-sage-300',
        'text-white dark:text-sage-900',
        'hover:bg-sage-900',
        'active:bg-green-950',
        'focus-visible:bg-sage-800 focus-visible:ring-accent-500',
        'disabled:bg-gray-100 disabled:text-gray-800',
      )
      break
    case 'secondary':
      classes.push(
        'bg-transparent border border-sage-800 dark:border-sage-300 text-sage-800 dark:text-sage-300',
        'hover:bg-sage-100 hover:text-sage-900 dark:hover:bg-sage-200/10',
        'active:bg-sage-200 active:text-sage-950 dark:active:bg-sage-900',
        'focus-visible:bg-transparent focus-visible:ring-accent-500',
        'disabled:border-transparent disabled:text-gray-800 disabled:bg-gray-100',
      )
      break
    case 'tertiary':
      classes.push(
        'bg-transparent text-sage-800 dark:text-sage-300',
        'hover:bg-sage-100 dark:hover:bg-sage-200/10',
        'active:bg-sage-200 dark:active:bg-sage-950',
        'focus-visible:ring-accent-500 focus-visible:bg-transparent',
        'disabled:text-gray-800 disabled:bg-gray-100',
      )
      break
    case 'accent':
      classes.push(
        'bg-rose-200 text-rose-900',
        'hover:bg-rose-100 hover:text-rose-950 dark:hover:bg-rose-400',
        'active:bg-rose-900 active:text-rose-50',
        'focus-visible:ring-accent-500 focus-visible:bg-rose-200 focus-visible:text-rose-900',
        'disabled:bg-gray-100 disabled:text-gray-800',
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
      return 'text-md'
  }
})
</script>
