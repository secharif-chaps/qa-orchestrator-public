<template>
  <div
    class="flex items-center justify-between rounded-sm p-3 transition-colors"
    :class="[
      disabled
        ? 'bg-primary-lightest/50 opacity-60'
        : 'bg-primary-lightest hover:bg-primary-lighter/50',
      variant === 'danger' ? 'border-error-stroke/30 border' : '',
    ]"
  >
    <div class="flex items-center gap-3">
      <div
        class="flex h-8 w-8 items-center justify-center rounded-sm"
        :class="[variant === 'danger' ? 'bg-error-light' : 'bg-primary/10']"
      >
        <i
          :class="[
            'fa',
            icon,
            'text-sm',
            variant === 'danger' ? 'text-error-light-content' : 'text-primary',
          ]"
        ></i>
      </div>
      <div class="flex-1">
        <p class="text-sm font-medium">{{ label }}</p>
        <p class="text-neutral-black-font text-xs">{{ description }}</p>
        <p v-if="disabled && disabledReason" class="text-warning-light-content mt-1 text-xs">
          <i class="fa fa-info-circle mr-1"></i>
          {{ disabledReason }}
        </p>
      </div>
    </div>
    <Switch
      :id="`permission-${permission}`"
      :model-value="isChecked"
      :disabled="disabled"
      @update:model-value="handleToggle"
    />
  </div>
</template>

<script setup lang="ts">
import { Switch } from '@owlint/feathers-vue'
import { computed } from 'vue'

const props = defineProps<{
  modelValue: string[]
  permission: string
  label: string
  description: string
  icon: string
  disabled?: boolean
  disabledReason?: string
  variant?: 'default' | 'danger'
}>()

interface Emit {
  'update:modelValue': [value: string[]]
  change: [checked: boolean]
}

const emit = defineEmits<Emit>()

const isChecked = computed(() => props.modelValue.includes(props.permission))

const handleToggle = (checked: boolean) => {
  if (props.disabled) return

  const newValue = [...props.modelValue]

  if (checked) {
    if (!newValue.includes(props.permission)) {
      newValue.push(props.permission)
    }
  } else {
    const index = newValue.indexOf(props.permission)
    if (index > -1) {
      newValue.splice(index, 1)
    }
  }

  emit('update:modelValue', newValue)
  emit('change', checked)
}
</script>
