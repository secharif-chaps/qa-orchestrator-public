<template>
  <div class="space-y-2" :class="[dark ? 'dark' : '']">
    <!-- Label -->
    <label v-if="label" :for="id" class="block text-sm font-medium">
      {{ label }}
      <span v-if="required" class="text-warning ml-1">*</span>
    </label>

    <!-- Input Container -->
    <div class="relative">
      <!-- Icon -->
      <div
        v-if="icon"
        :class="{
          'text-sage-200': dark,
          'text-secondary/60': !dark,
        }"
        class="absolute left-4 top-1/2 transform -translate-y-1/2 pointer-events-none"
      >
        <i :class="icon" class="text-sm"></i>
      </div>

      <!-- Input Field -->
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :class="[
          inputClasses,
          {
            'bg-sage-700 placeholder:text-secondary dark:placeholder:text-white border-primary-700':
              dark,
            'bg-base-100 border-primary-stroke placeholder:text-secondary/60 dark:placeholder:text-sage-300':
              !dark,
          },
        ]"
        class="w-full border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-200 dark:focus:ring-primary-700 dark:focus:border-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
        @keyup.enter="$emit('enter', $event)"
      />

      <!-- Clear Button -->
      <button
        v-if="clearable && modelValue && !disabled"
        type="button"
        @click="$emit('update:modelValue', '')"
        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-secondary hover:text-base transition-colors p-1"
      >
        <i class="fa fa-times text-xs"></i>
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="flex items-center gap-2 text-sm text-warning">
      <i class="fa fa-exclamation-circle text-xs"></i>
      <span>{{ error }}</span>
    </div>

    <!-- Helper Text -->
    <div v-if="helper && !error" class="text-xs text-secondary">
      {{ helper }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  id?: string
  modelValue?: string | number
  type?: 'text' | 'email' | 'password' | 'url' | 'tel' | 'number'
  label?: string
  placeholder?: string
  error?: string
  helper?: string
  icon?: string
  disabled?: boolean
  required?: boolean
  clearable?: boolean
  size?: 'sm' | 'md' | 'lg'
  dark?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  size: 'md',
  clearable: false,
  disabled: false,
  required: false,
})

defineEmits<{
  'update:modelValue': [value: string]
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
  enter: [event: KeyboardEvent]
}>()

// Dynamic classes based on props
const inputClasses = computed(() => {
  const classes = []

  // Size variations
  switch (props.size) {
    case 'sm':
      classes.push('px-3 py-2 text-sm')
      break
    case 'lg':
      classes.push('px-4 py-4 text-lg')
      break
    default:
      classes.push('px-4 py-3')
      break
  }

  // Icon padding
  if (props.icon) {
    classes.push('pl-10')
  }

  // Clear button padding
  if (props.clearable && props.modelValue) {
    classes.push('pr-10')
  }

  // Error state
  if (props.error) {
    classes.push('border-warning focus:border-warning focus:ring-warning/20')
  }

  return classes.join(' ')
})
</script>
