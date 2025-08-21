<template>
  <div v-if="show" :class="alertClasses" class="relative overflow-hidden rounded-xl border-3">
    <!-- Background decoration -->
    <div
      v-if="decorationIcon"
      class="absolute top-0 right-0 w-32 h-32 opacity-[0.1] dark:opacity-[0.08]"
    >
      <i :class="[decorationIcon, iconColorClasses]" class="text-6xl"></i>
    </div>

    <!-- Content -->
    <div class="relative p-6">
      <div class="flex items-start gap-4">
        <!-- Icon -->
        <div v-if="icon" class="flex-shrink-0">
          <div
            :class="iconBackgroundClasses"
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
                class="text-secondary text-sm leading-relaxed"
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
              class="text-secondary hover:text-base transition-colors p-1 ml-4 flex-shrink-0"
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

type AlertVariant = 'info' | 'success' | 'warning' | 'error'

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
  const baseClasses = 'border-border-2 bg-gradient-to-r from-bg1 to-bg2'

  switch (props.variant) {
    case 'success':
      return `${baseClasses} border-green-500/40`
    case 'warning':
      return `${baseClasses} border-yellow-500/40`
    case 'error':
      return `${baseClasses} border-red-500/40`
    default:
      return `${baseClasses} border-blue-500/40`
  }
})

const iconColorClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'text-green-500'
    case 'warning':
      return 'text-yellow-500'
    case 'error':
      return 'text-red-500'
    default:
      return 'text-blue-500'
  }
})

const iconBackgroundClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-green-500/10'
    case 'warning':
      return 'bg-yellow-500/10'
    case 'error':
      return 'bg-red-500/10'
    default:
      return 'bg-blue-500/10'
  }
})
</script>
