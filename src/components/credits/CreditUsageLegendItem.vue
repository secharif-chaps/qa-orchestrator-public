<template>
  <div class="flex items-center gap-2">
    <div
      class="w-3 h-3 rounded-full flex-shrink-0"
      :style="{ backgroundColor: color }"
    ></div>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-medium truncate">{{ label }}</div>
      <div class="text-xs text-secondary">{{ formattedValue }}</div>
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
