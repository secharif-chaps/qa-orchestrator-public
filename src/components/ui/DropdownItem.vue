<template>
  <button
    type="button"
    class="w-full text-left px-4 py-2 text-sm transition-colors flex items-center gap-2"
    :class="[variantClasses, disabledClasses]"
    :disabled="disabled"
    @click="handleClick"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'default' | 'danger'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  disabled: false,
})

const emit = defineEmits<{
  click: []
}>()

const variantClasses = computed(() => {
  if (props.disabled) {
    return 'text-secondary/50 cursor-not-allowed'
  }

  const variants = {
    default: 'text-base hover:bg-base-200',
    danger: 'text-error hover:bg-error-light',
  }
  return variants[props.variant]
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
