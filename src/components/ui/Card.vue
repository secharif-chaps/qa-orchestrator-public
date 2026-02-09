<template>
  <div
    :class="cardClasses"
    class="bg-base-100 rounded-card p-4 shadow-shadow-2 flex flex-col gap-2 border border-primary-stroke"
  >
    <!-- Default slot for card content -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  /** Whether the card should be clickable */
  clickable?: boolean
  /** Custom padding (overrides default p-4) */
  padding?: string
  /** Whether the card should be hoverable */
  hoverable?: boolean
}

const { hoverable = false, clickable = false, padding = '' } = defineProps<Props>()

const cardClasses = computed(() => {
  const classes: string[] = []

  if (clickable) {
    classes.push(
      'cursor-pointer transition-colors',
    )
  }
  if (hoverable || clickable) {
    classes.push(
      'hover:shadow-shadow-1 transition-shadow duration-150',
    )
  }

  if (padding) {
    classes.push(padding)
  }

  return classes.join(' ')
})
</script>
