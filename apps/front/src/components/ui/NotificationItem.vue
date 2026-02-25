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
      <Badge variant="secondary" :color="iconColor" :icon="icon" />

      <!-- Content -->
      <div class="min-w-0 flex-1">
        <!-- Title and Unread Indicator -->
        <div class="mb-1 flex items-start justify-between gap-2">
          <h4 class="text-sm font-medium text-white">{{ title }}</h4>
          <Bullet v-if="!read" intent="info" aria-label="Unread" />
        </div>

        <!-- Message -->
        <p class="text-sage-400 mb-2 line-clamp-2 text-xs">{{ message }}</p>

        <!-- Time and Category -->
        <div class="text-sage-500 flex items-center gap-3 text-xs">
          <span>{{ time }}</span>
          <Tag v-if="category" :label="category" variant="secondary" size="xs" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Badge, Bullet, Tag } from '@owlint/feathers-vue'

// Vuellar Badge colors
type BadgeColor = 'sage' | 'almond' | 'pink' | 'indigo' | 'yellow' | 'cherry' | 'cyan'

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
