<template>
  <div class="border-primary-stroke bg-base-100 inline-flex rounded-lg border p-1">
    <button
      v-for="(option, index) in options"
      :key="option.value"
      @click="$emit('update:modelValue', option.value)"
      :class="[
        'px-3 py-1.5 text-sm font-medium transition-all duration-200',
        modelValue === option.value
          ? 'bg-almond-200 text-almond-900 shadow-sm'
          : 'text-secondary hover:text-secondary hover:bg-base-200',
        index === 0 ? 'rounded-lg' : '',
        index === options.length - 1 ? 'rounded-lg' : '',
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
