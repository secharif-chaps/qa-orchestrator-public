<template>
  <div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex items-center">
      <div class="flex-shrink-0">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center"
             :class="iconBackgroundClass">
          <i :class="iconClass"></i>
        </div>
      </div>
      <div class="ml-4">
        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wide">{{ title }}</h4>
        <p class="text-2xl font-bold text-gray-900">{{ formattedValue }}</p>
        <p v-if="subtitle" class="text-xs text-gray-400 mt-1">{{ subtitle }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [Number, String],
    required: true
  },
  subtitle: {
    type: String,
    default: null
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'indigo',
    validator: (value) => ['blue', 'green', 'purple', 'orange', 'red', 'yellow', 'indigo'].includes(value)
  },
  loading: {
    type: Boolean,
    default: false
  }
})

// Computed properties for styling
const iconClass = computed(() => `fas fa-${props.icon} text-${props.color}-600`)

const iconBackgroundClass = computed(() => `bg-${props.color}-100`)

const formattedValue = computed(() => {
  if (props.loading) return '...'
  
  // Format numbers with commas for better readability
  if (typeof props.value === 'number') {
    return props.value.toLocaleString()
  }
  
  return props.value || '0'
})
</script>