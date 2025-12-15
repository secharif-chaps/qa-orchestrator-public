<template>
  <div v-if="show" :class="alertClasses" class="relative rounded-xl border p-4">
    <div class="flex items-start gap-4">
      <!-- Icon Badge -->
      <Badge v-if="icon" :color="badgeColor" variant="primary" size="md" :icon="icon" />

      <!-- Main Content -->
      <div class="flex-1 min-w-0">
        <!-- Title -->
        <h3 v-if="title" class="font-semibold mb-1">
          {{ title }}
        </h3>

        <!-- Message -->
        <p v-if="message" class="text-sm leading-relaxed opacity-90">
          {{ message }}
        </p>

        <!-- Custom content slot -->
        <div v-if="$slots.default" class="text-sm opacity-90">
          <slot />
        </div>

        <!-- Actions slot -->
        <div v-if="$slots.actions" class="mt-3 flex items-center gap-2">
          <slot name="actions" />
        </div>
      </div>

      <!-- Action Button (if provided) -->
      <div v-if="$slots.action" class="flex-shrink-0">
        <slot name="action" />
      </div>

      <!-- Close button -->
      <Button
        variant="tertiary"
        v-if="dismissible || closable"
        @click="handleClose"
        :title="dismissLabel || 'Close'"
        icon="fa fa-times"
      >
      </Button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { Button } from '@owlint/feathers-vue'
import Badge from './Badge.vue'

type AlertVariant = 'info' | 'success' | 'warning' | 'error' | 'accent' | 'neutral'

interface Props {
  variant?: AlertVariant
  title?: string
  message?: string
  icon?: string
  show?: boolean
  dismissible?: boolean
  closable?: boolean
  dismissLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'info',
  show: true,
  dismissible: false,
  closable: false,
})

const slots = useSlots()

const emit = defineEmits<{
  dismiss: []
  close: []
}>()

function handleClose() {
  emit('dismiss')
  emit('close')
}

// Map alert variant to badge color
const badgeColor = computed(() => {
  switch (props.variant) {
    case 'neutral':
      return 'slate'
    default:
      return props.variant
  }
})

// Border and background color classes based on variant
const alertClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'border-success-stroke bg-success-50 text-success-light-950 dark:border-success-400/30 dark:bg-success-400/30 dark:text-success-50'
    case 'warning':
      return 'border-warning-stroke bg-warning-50 text-warning-950 dark:border-warning-400/30 dark:bg-warning-400/30 dark:text-warning-50'
    case 'error':
      return 'border-error-stroke bg-error-50 text-error-950 dark:border-error-400/30 dark:bg-error-400/30 dark:text-error-50'
    case 'accent':
      return 'border-accent-stroke bg-rose-50 text-rose-950 dark:border-accent-400/30 dark:bg-rose-400/30 dark:text-rose-50'
    case 'neutral':
      return 'border-gray-300 bg-gray-50 text-gray-950 dark:border-gray-400/30 dark:bg-gray-400/30 dark:text-gray-50'
    default: // info
      return 'border-info-stroke bg-info-50 text-info-950 dark:border-info-400/30 dark:bg-info-400/30 dark:text-info-50'
  }
})
</script>
