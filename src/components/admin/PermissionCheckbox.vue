<template>
  <div
    class="flex items-center justify-between rounded-lg p-3 transition-colors"
    :class="[
      disabled ? 'bg-base-200/50 opacity-60' : 'bg-base-200 hover:bg-base-300/50',
      variant === 'danger' ? 'border-error-stroke/30 border' : '',
    ]"
  >
    <div class="flex items-center gap-3">
      <div
        class="flex h-8 w-8 items-center justify-center rounded-lg"
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
        <p class="text-secondary text-xs">{{ description }}</p>
        <p v-if="disabled && disabledReason" class="text-warning-light-content mt-1 text-xs">
          <i class="fa fa-info-circle mr-1"></i>
          {{ disabledReason }}
        </p>
      </div>
    </div>
    <label class="relative inline-flex shrink-0 cursor-pointer items-center">
      <input
        type="checkbox"
        :checked="isChecked"
        :disabled="disabled"
        class="peer sr-only"
        @change="handleChange"
      />
      <div
        class="peer peer-focus:ring-primary/20 h-6 w-11 rounded-full transition-colors peer-focus:ring-2"
        :class="[
          isChecked ? (variant === 'danger' ? 'bg-error' : 'bg-primary') : 'bg-base-300',
          disabled ? 'cursor-not-allowed' : 'cursor-pointer',
        ]"
      >
        <div
          class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
          :class="[isChecked ? 'translate-x-5' : 'translate-x-0']"
        ></div>
      </div>
    </label>
  </div>
</template>

<script setup lang="ts">
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

function handleChange(event: Event) {
  if (props.disabled) return

  const target = event.target as HTMLInputElement
  const newValue = [...props.modelValue]

  if (target.checked) {
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
  emit('change', target.checked)
}
</script>
