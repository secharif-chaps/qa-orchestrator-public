<template>
  <div
    class="bg-base-100 border border-primary-stroke rounded-lg p-6 ring-offset-2 ring-offset-bg2 hover:ring-4 hover:ring-primary/70 transition-all cursor-pointer group h-full"
    @click="handleClick"
  >
    <div class="flex items-center h-full">
      <div class="flex-shrink-0">
        <div
          class="w-12 h-12 rounded-lg flex items-center justify-center transition-colors"
          :class="iconBackgroundClass"
        >
          <i :class="iconClass" class="text-xl"></i>
        </div>
      </div>
      <div class="ml-4 flex-1">
        <h3 class="text-lg font-medium group-hover:text-primary transition-colors">{{ title }}</h3>
        <p class="text-sm text-primary-light-content">{{ description }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) =>
      ['blue', 'green', 'purple', 'orange', 'red', 'yellow', 'indigo'].includes(value as string),
  },
  to: {
    type: String,
    default: null,
  },
  href: {
    type: String,
    default: null,
  },
  external: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['click'])

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
  const colorClass = colorMap[props.color as keyof typeof colorMap] || colorMap.blue
  return `fas fa-${props.icon} ${colorClass}`
})

const iconBackgroundClass = computed(() => {
  const colorMap = {
    blue: 'bg-blue-100 dark:bg-blue-900/30 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50',
    green:
      'bg-green-100 dark:bg-green-900/30 group-hover:bg-green-200 dark:group-hover:bg-green-900/50',
    purple:
      'bg-purple-100 dark:bg-purple-900/30 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50',
    orange:
      'bg-orange-100 dark:bg-orange-900/30 group-hover:bg-orange-200 dark:group-hover:bg-orange-900/50',
    red: 'bg-red-100 dark:bg-red-900/30 group-hover:bg-red-200 dark:group-hover:bg-red-900/50',
    yellow:
      'bg-yellow-100 dark:bg-yellow-900/30 group-hover:bg-yellow-200 dark:group-hover:bg-yellow-900/50',
    indigo:
      'bg-indigo-100 dark:bg-indigo-900/30 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/50',
  }
  return colorMap[props.color as keyof typeof colorMap] || colorMap.blue
})

const router = useRouter()

// Handle click events
const handleClick = () => {
  if (props.to) {
    router.push(props.to)
  } else if (props.href) {
    if (props.external) {
      window.open(props.href, '_blank')
    } else {
      window.location.href = props.href
    }
  } else {
    emit('click')
  }
}
</script>
