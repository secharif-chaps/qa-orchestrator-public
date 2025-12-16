<template>
  <div
    class="flex items-center justify-between p-3 rounded-lg transition-colors"
    :class="[
      disabled ? 'bg-base-200/50 opacity-60' : 'bg-base-200 hover:bg-base-300/50',
      variant === 'danger' ? 'border border-error-stroke/30' : '',
    ]"
  >
    <div class="flex items-center gap-3">
      <div
        class="w-8 h-8 rounded-lg flex items-center justify-center"
        :class="[
          variant === 'danger' ? 'bg-error-light' : 'bg-primary/10',
        ]"
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
        <p class="font-medium text-sm">{{ label }}</p>
        <p class="text-xs text-secondary">{{ description }}</p>
        <p v-if="disabled && disabledReason" class="text-xs text-warning-light-content mt-1">
          <i class="fa fa-info-circle mr-1"></i>
          {{ disabledReason }}
        </p>
      </div>
    </div>
    <label class="relative inline-flex items-center cursor-pointer shrink-0">
      <input
        type="checkbox"
        :checked="isChecked"
        :disabled="disabled"
        class="sr-only peer"
        @change="handleChange"
      />
      <div
        class="w-11 h-6 rounded-full peer peer-focus:ring-2 peer-focus:ring-primary/20 transition-colors"
        :class="[
          isChecked
            ? variant === 'danger'
              ? 'bg-error'
              : 'bg-primary'
            : 'bg-base-300',
          disabled ? 'cursor-not-allowed' : 'cursor-pointer',
        ]"
      >
        <div
          class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform"
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

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

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
}
</script>
