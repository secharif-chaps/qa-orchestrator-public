<template>
  <div v-if="show" :class="alertClasses" class="relative overflow-hidden rounded-block">

    <!-- Content -->
    <div class="relative p-6">
      <div class="flex items-start gap-4">
        <!-- Icon -->
        <div v-if="icon" class="flex-shrink-0">
          <div
            class="w-12 h-12 rounded-full flex items-center justify-center"
          >
            <i :class="[icon, iconColorClasses]" class="text-lg"></i>
          </div>
        </div>

        <!-- Main content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between">
            <div>
              <!-- Title -->
              <h3 v-if="title" class="text-lg font-semibold mb-1">
                {{ title }}
              </h3>

              <!-- Message -->
              <p
                v-if="message"
                class="text-primary-light-content dark:text-white text-sm leading-relaxed"
                :class="{ 'mb-4': hasActions }"
              >
                {{ message }}
              </p>

              <!-- Custom content slot -->
              <div v-if="$slots.default" :class="{ 'mb-4': hasActions }">
                <slot />
              </div>
            </div>

            <!-- Close button -->
            <button
              v-if="dismissible || closable"
              @click="handleClose"
              class="text-primary-light-content hover:text-base transition-colors p-1 ml-4 flex-shrink-0"
              :title="dismissLabel || 'Close'"
            >
              <i class="fa fa-times"></i>
            </button>
          </div>

          <!-- Actions Row -->
          <div v-if="hasActions" class="flex items-center justify-between">
            <!-- Left content slot (e.g., status indicators) -->
            <div v-if="$slots.status" class="flex-1">
              <slot name="status" />
            </div>

            <!-- Actions slot -->
            <div v-if="$slots.actions" class="flex items-center gap-3">
              <slot name="actions" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'

type AlertVariant = 'info' | 'success' | 'warning' | 'error' | 'accent' | 'gradient'

interface Props {
  variant?: AlertVariant
  title?: string
  message?: string
  icon?: string
  decorationIcon?: string
  show?: boolean
  dismissible?: boolean
  closable?: boolean
  dismissLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'info',
  show: true,
  dismissible: true,
  closable: false,
})

const slots = useSlots()

const emit = defineEmits<{
  dismiss: []
  close: []
}>()

const handleClose = () => {
  emit('dismiss')
  emit('close')
}

// Check if there are action slots
const hasActions = computed(() => {
  return !!(slots.actions || slots.status)
})

// Dynamic classes based on variant
const alertClasses = computed(() => {
  const baseClasses = 'shadow-sm'

  switch (props.variant) {
    case 'success':
      return `${baseClasses} bg-success-100 dark:bg-success-400/50`
    case 'warning':
      return `${baseClasses} bg-warning-100 dark:bg-warning-400/50`
    case 'error':
      return `${baseClasses} bg-error-100 dark:bg-error-400/50`
    case 'accent':
      return `${baseClasses} bg-accent-100 dark:bg-accent-400/50`
    case 'gradient':
      return `${baseClasses} bg-gradient-to-r from-tertiary-100 to-sage-100 dark:from-tertiary-400/20 dark:to-tertiary-400/70`
    default:
      return `${baseClasses} bg-info-100 dark:bg-info-400/50`
  }
})

const iconColorClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'text-success-500 dark:text-success-200'
    case 'warning':
      return 'text-warning-500 dark:text-warning-200'
    case 'error':
      return 'text-error-500 dark:text-error-200'
    case 'accent':
      return 'text-accent-500 dark:text-accent-200'
    case 'gradient':
      return 'text-accent-500 dark:text-primary-200'
    default:
      return 'text-info-500 dark:text-info-200'
  }
})


</script>
