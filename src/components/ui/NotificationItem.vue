<template>
  <div
    role="button"
    tabindex="0"
    :class="containerClasses"
    :aria-label="`Notification: ${title}. ${read ? 'Read' : 'Unread'}`"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <div class="flex items-start gap-3">
      <!-- Icon Badge -->
      <Badge variant="secondary" :color="iconColor" :icon="icon" size="md" />

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <!-- Title and Unread Indicator -->
        <div class="flex items-start justify-between gap-2 mb-1">
          <h4 class="text-sm font-medium text-white">{{ title }}</h4>
          <span
            v-if="!read"
            class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-1.5"
            aria-label="Unread"
          ></span>
        </div>

        <!-- Message -->
        <p class="text-xs text-sage-400 mb-2 line-clamp-2">{{ message }}</p>

        <!-- Time and Category -->
        <div class="flex items-center gap-3 text-xs text-sage-500">
          <span>{{ time }}</span>
          <span v-if="category" class="flex items-center gap-1">
            <i class="fa fa-tag"></i>
            {{ category }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Badge, { type BadgeColor } from './Badge.vue'

interface Props {
  id: string | number
  icon: string
  iconColor: BadgeColor
  title: string
  message: string
  time: string
  read: boolean
  category?: string
  actionUrl?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  click: [id: string | number]
  'mark-as-read': [id: string | number]
}>()

// Container classes with read/unread states and hover
const containerClasses = computed(() => {
  const baseClasses =
    'px-4 py-3 cursor-pointer transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-inset'
  const stateClasses = props.read ? 'hover:bg-sage-800/30' : 'bg-sage-800/20 hover:bg-sage-800/30'

  return `${baseClasses} ${stateClasses}`
})

// Handle click event
function handleClick() {
  emit('click', props.id)
  if (!props.read) {
    emit('mark-as-read', props.id)
  }
}
</script>
