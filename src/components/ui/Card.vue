<template>
  <div
    :class="cardClasses"
    class="bg-bg1 rounded-card p-4 shadow-shadow-2 flex flex-col gap-2 border border-sage-100"
  >
    <!-- Default slot for card content -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  /** Whether the card should be clickable */
  clickable?: boolean
  /** Custom padding (overrides default p-4) */
  padding?: string
}

const props = withDefaults(defineProps<Props>(), {
  hoverable: false,
  clickable: false,
})

const cardClasses = computed(() => {
  const classes: string[] = []

  if (props.clickable) {
    classes.push(
      'cursor-pointer transition-colors duration-150 hover:shadow-shadow-1 transition-shadow duration-150',
    )
  }

  if (props.padding) {
    classes.push(props.padding)
  }

  return classes.join(' ')
})
</script>
