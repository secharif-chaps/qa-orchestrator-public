<template>
  <div class="flex items-center gap-2">
    <div class="h-3 w-3 flex-shrink-0 rounded-full" :style="{ backgroundColor: color }"></div>
    <div class="min-w-0 flex-1">
      <div class="truncate text-sm font-medium">{{ label }}</div>
      <div class="text-neutral-black-font text-xs">{{ formattedValue }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Single legend item for credit usage chart.
 * Displays color indicator, module label, and value.
 */
import { computed } from 'vue'

interface Props {
  color: string
  label: string
  value: number
  percentage: number
}

const props = defineProps<Props>()

const formattedValue = computed(() => {
  const formatted = props.value.toLocaleString()
  return `${formatted} (${props.percentage.toFixed(1)}%)`
})
</script>
