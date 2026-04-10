<template>
  <div class="border-primary-lighter-stroke flex items-center rounded-sm border bg-white p-6">
    <div class="flex w-full items-center">
      <div class="flex-shrink-0">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-sm"
          :class="iconBackgroundClass"
        >
          <i :class="iconClass" class="text-xl"></i>
        </div>
      </div>
      <div class="ml-4 flex-1">
        <h4 class="text-sm font-medium tracking-wide uppercase">{{ title }}</h4>
        <p class="text-2xl font-bold">{{ formattedValue }}</p>
        <p v-if="subtitle" class="text-neutral-black-font mt-1 text-xs">
          {{ subtitle }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title: string
  value: number | string | undefined
  subtitle?: string | null
  icon: string
  color?: 'blue' | 'green' | 'purple' | 'orange' | 'red' | 'yellow' | 'indigo'
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  subtitle: null,
  color: 'indigo',
  loading: false,
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
