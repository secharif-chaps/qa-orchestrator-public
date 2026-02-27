<template>
  <div class="flex flex-col gap-1">
    <Input
      :id="id"
      :model-value="modelValue"
      :label="label"
      :placeholder="placeholder"
      :type="type"
      :icon="icon"
      :disabled="disabled"
      :required="required"
      :class="{ 'border-error': error }"
      v-bind="$attrs"
      @update:model-value="$emit('update:model-value', $event)"
      @blur="$emit('blur', $event)"
      @focus="$emit('focus', $event)"
    />
    <p v-if="error" class="text-error text-sm">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { Input } from '@owlint/feathers-vue'

interface Props {
  id?: string
  modelValue: string | number
  label?: string
  placeholder?: string
  type?: string
  icon?: string
  disabled?: boolean
  required?: boolean
  error?: string
}

const props = withDefaults(defineProps<Props>(), {
  id: undefined,
  label: undefined,
  placeholder: undefined,
  type: 'text',
  icon: undefined,
  disabled: false,
  required: false,
  error: undefined,
})

defineEmits<{
  'update:model-value': [value: string | number]
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
}>()
</script>

<style scoped>
/* Apply red border to Input when it has error */
.border-error :deep(input) {
  border-color: rgb(var(--error)) !important;
}

.border-error :deep(input:focus) {
  border-color: rgb(var(--error)) !important;
  outline-color: rgb(var(--error)) !important;
}
</style>
