<template>
  <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow cursor-pointer group h-full"
       @click="handleClick">
    <div class="flex items-center h-full">
      <div class="flex-shrink-0">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center transition-colors"
             :class="iconBackgroundClass">
          <i :class="iconClass" class="text-xl"></i>
        </div>
      </div>
      <div class="ml-4 flex-1">
        <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
        <p class="text-sm text-gray-500">{{ description }}</p>
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
  description: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'purple', 'orange', 'red', 'yellow', 'indigo'].includes(value)
  },
  to: {
    type: String,
    default: null
  },
  href: {
    type: String,
    default: null
  },
  external: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

// Computed properties for styling
const iconClass = computed(() => `fas fa-${props.icon} text-${props.color}-600`)

const iconBackgroundClass = computed(() => {
  const baseClasses = `bg-${props.color}-100 group-hover:bg-${props.color}-200`
  return baseClasses
})

// Handle click events
const handleClick = () => {
  if (props.to) {
    navigateTo(props.to)
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