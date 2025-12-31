<template>
  <!-- Rich layout (when description is provided) -->
  <button
    v-if="description"
    type="button"
    class="w-full flex items-center gap-3 px-3 py-2 text-left rounded-md transition-colors"
    :class="[richVariantClasses, disabledClasses]"
    :disabled="disabled"
    @click="handleClick"
  >
    <!-- Icon box -->
    <div
      v-if="icon"
      class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
      :class="iconBgColor"
    >
      <i :class="[icon, iconColor, 'text-sm']"></i>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
      <div class="font-medium text-sm">{{ label }}</div>
      <div class="text-xs text-secondary">{{ description }}</div>
    </div>

    <!-- Suffix slot for extra content (e.g., "Soon" tag) -->
    <slot name="suffix" />
  </button>

  <!-- Simple layout (backward compatible) -->
  <button
    v-else
    type="button"
    class="w-full text-left px-4 py-2 text-sm transition-colors flex items-center gap-2"
    :class="[simpleVariantClasses, disabledClasses]"
    :disabled="disabled"
    @click="handleClick"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'default' | 'danger' | 'warning' | 'info'
  disabled?: boolean
  // Rich content props
  icon?: string
  iconBgColor?: string
  iconColor?: string
  label?: string
  description?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  disabled: false,
  iconBgColor: 'bg-base-200',
  iconColor: 'text-secondary',
})

const emit = defineEmits<{
  click: []
}>()

// Simple layout variant classes (backward compatible)
const simpleVariantClasses = computed(() => {
  if (props.disabled) {
    return 'text-secondary/50 cursor-not-allowed'
  }

  const variants = {
    default: 'text-base hover:bg-base-200',
    danger: 'text-error hover:bg-error-light',
    warning: 'text-warning hover:bg-warning-light',
    info: 'text-info hover:bg-info-light',
  }
  return variants[props.variant]
})

// Rich layout variant classes
const richVariantClasses = computed(() => {
  if (props.disabled) {
    return 'opacity-50 cursor-not-allowed'
  }
  return 'hover:bg-base-300'
})

const disabledClasses = computed(() => {
  return props.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
})

const handleClick = () => {
  if (!props.disabled) {
    emit('click')
  }
}
</script>
