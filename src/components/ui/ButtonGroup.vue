<template>
  <div class="inline-flex rounded-lg border border-border-2 bg-bg1 p-1">
    <button
      v-for="(option, index) in options"
      :key="option.value"
      @click="$emit('update:modelValue', option.value)"
      :class="[
        'px-3 py-1.5 text-sm font-medium transition-all duration-200',
        modelValue === option.value
          ? 'bg-primary text-white shadow-sm'
          : 'text-secondary hover:text-primary hover:bg-bg2',
        index === 0 ? 'rounded-l-md' : '',
        index === options.length - 1 ? 'rounded-r-md' : '',
        index > 0 ? '-ml-px' : '',
      ]"
      :title="option.title"
      :disabled="option.disabled"
    >
      <i v-if="option.icon" :class="[option.icon, option.label ? 'mr-2' : '']"></i>
      <span v-if="option.label">{{ option.label }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
export interface ButtonGroupOption {
  value: string | number
  label?: string
  icon?: string
  title?: string
  disabled?: boolean
}

interface Props {
  options: ButtonGroupOption[]
  modelValue: string | number
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: string | number]
}>()
</script>