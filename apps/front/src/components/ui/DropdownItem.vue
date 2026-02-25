<template>
  <!-- Rich layout (when description is provided) -->
  <button
    v-if="description"
    type="button"
    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition-colors"
    :class="richVariantClasses"
    :disabled="disabled"
    @click="handleClick"
  >
    <!-- Icon box -->
    <div
      v-if="icon"
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
      :class="iconColors.bg"
    >
      <i :class="[icon, iconColors.text, 'text-sm']"></i>
    </div>

    <!-- Content -->
    <div class="min-w-0 flex-1">
      <div class="text-sm font-medium">{{ label }}</div>
      <div class="text-secondary text-xs">{{ description }}</div>
    </div>

    <!-- Suffix slot for extra content (e.g., "Soon" tag) -->
    <slot name="suffix" />
  </button>

  <!-- Simple layout (backward compatible) -->
  <button
    v-else
    type="button"
    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition-colors"
    :class="simpleVariantClasses"
    :disabled="disabled"
    @click="handleClick"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type IconColor = 'blue' | 'green' | 'purple' | 'red' | 'yellow' | 'gray' | 'sage' | 'pink'

interface Props {
  variant?: 'default' | 'danger' | 'warning' | 'info'
  disabled?: boolean
  // Rich content props
  icon?: string
  color?: IconColor
  label?: string
  description?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  disabled: false,
  color: 'gray',
})

// Color mapping for icon box - encapsulates Tailwind classes
const iconColors = computed(() => {
  const colorMap: Record<IconColor, { bg: string; text: string }> = {
    blue: { bg: 'bg-blue-100 dark:bg-blue-900/20', text: 'text-blue-600 dark:text-blue-400' },
    green: { bg: 'bg-green-100 dark:bg-green-900/20', text: 'text-green-600 dark:text-green-400' },
    purple: {
      bg: 'bg-purple-100 dark:bg-purple-900/20',
      text: 'text-purple-600 dark:text-purple-400',
    },
    red: { bg: 'bg-red-100 dark:bg-red-900/20', text: 'text-red-600 dark:text-red-400' },
    yellow: {
      bg: 'bg-yellow-100 dark:bg-yellow-900/20',
      text: 'text-yellow-600 dark:text-yellow-400',
    },
    gray: { bg: 'bg-gray-100 dark:bg-gray-900/20', text: 'text-gray-600 dark:text-gray-400' },
    sage: { bg: 'bg-sage-100 dark:bg-sage-900/20', text: 'text-sage-600 dark:text-sage-400' },
    pink: { bg: 'bg-pink-100 dark:bg-pink-900/20', text: 'text-pink-600 dark:text-pink-400' },
  }
  return colorMap[props.color]
})

const emit = defineEmits<{
  click: []
}>()

// Simple layout variant classes (backward compatible)
const simpleVariantClasses = computed(() => {
  if (props.disabled) {
    return 'text-secondary/50 cursor-not-allowed opacity-50'
  }

  const variants = {
    default: 'text-base hover:bg-base-200 cursor-pointer',
    danger: 'text-error hover:bg-error-light cursor-pointer',
    warning: 'text-warning hover:bg-warning-light cursor-pointer',
    info: 'text-info hover:bg-info-light cursor-pointer',
  }
  return variants[props.variant]
})

// Rich layout variant classes
const richVariantClasses = computed(() => {
  if (props.disabled) {
    return 'opacity-50 cursor-not-allowed'
  }
  return 'hover:bg-base-300 cursor-pointer'
})

const handleClick = () => {
  if (!props.disabled) {
    emit('click')
  }
}
</script>
