<template>
  <div class="flex flex-col gap-1">
    <Input
      :id="id"
      v-model="modelValue"
      :label="label"
      :placeholder="placeholder"
      :type="type"
      :icon="icon"
      :disabled="disabled"
      :required="required"
      :class="{ 'border-error': error }"
      v-bind="$attrs"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    />
    <p v-if="error" class="text-error text-sm">
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { Input } from '@owlint/feathers-vue'

interface Props {
  id: string
  label?: string
  placeholder?: string
  type?: string
  icon?: string
  disabled?: boolean
  required?: boolean
  error?: string
}

interface Emits {
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
}

const modelValue = defineModel<string | number>({ required: true })

const {
  id,
  label = undefined,
  placeholder = undefined,
  type = 'text',
  icon = undefined,
  disabled = false,
  required = false,
  error = undefined,
} = defineProps<Props>()

const emit = defineEmits<Emits>()
</script>
