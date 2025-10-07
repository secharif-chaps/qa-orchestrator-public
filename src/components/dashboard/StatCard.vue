<template>
  <div class="bg-base-100 border border-primary-stroke rounded-lg p-6 flex items-center">
    <div class="flex items-center w-full">
      <div class="flex-shrink-0">
        <div
          class="w-12 h-12 rounded-lg flex items-center justify-center"
          :class="iconBackgroundClass"
        >
          <i :class="iconClass" class="text-xl"></i>
        </div>
      </div>
      <div class="ml-4 flex-1">
        <h4 class="text-sm font-medium uppercase tracking-wide">{{ title }}</h4>
        <p class="text-2xl font-bold">{{ formattedValue }}</p>
        <p v-if="subtitle" class="text-xs mt-1 text-primary-light-content">{{ subtitle }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String, undefined],
    required: true,
  },
  subtitle: {
    type: String,
    default: null,
  },
  icon: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    default: 'indigo',
    validator: (value: string) =>
      ['blue', 'green', 'purple', 'orange', 'red', 'yellow', 'indigo'].includes(value),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Computed properties for styling
const iconClass = computed(() => {
  const colorMap = {
    blue: 'text-blue-600 dark:text-blue-400',
    green: 'text-green-600 dark:text-green-400',
    purple: 'text-purple-600 dark:text-purple-400',
    orange: 'text-orange-600 dark:text-orange-400',
    red: 'text-red-600 dark:text-red-400',
    yellow: 'text-yellow-600 dark:text-yellow-400',
    indigo: 'text-indigo-600 dark:text-indigo-400',
  }
  const colorClass = colorMap[props.color as keyof typeof colorMap] || colorMap.indigo
  return `fas fa-${props.icon} ${colorClass}`
})

const iconBackgroundClass = computed(() => {
  const colorMap = {
    blue: 'bg-blue-100 dark:bg-blue-900/30',
    green: 'bg-green-100 dark:bg-green-900/30',
    purple: 'bg-purple-100 dark:bg-purple-900/30',
    orange: 'bg-orange-100 dark:bg-orange-900/30',
    red: 'bg-red-100 dark:bg-red-900/30',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/30',
    indigo: 'bg-indigo-100 dark:bg-indigo-900/30',
  }
  return colorMap[props.color as keyof typeof colorMap] || colorMap.indigo
})

const formattedValue = computed(() => {
  if (props.loading) return '...'

  // Format numbers with commas for better readability
  if (typeof props.value === 'number') {
    return props.value.toLocaleString()
  }

  return props.value || '0'
})
</script>
