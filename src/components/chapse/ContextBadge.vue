<template>
  <div
    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all"
    :class="badgeClasses"
  >
    <i :class="icon" class="text-xs"></i>
    <span>{{ label }}</span>
    <button
      v-if="dismissible"
      @click="$emit('dismiss')"
      class="hover:bg-black/10 dark:hover:bg-white/10 rounded p-0.5 transition-colors"
      :aria-label="`Remove ${label} context`"
    >
      <i class="fa fa-times text-xs"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ChapseContext } from '@/composables/useChapseChat'

const props = defineProps<{
  context: ChapseContext
  dismissible?: boolean
}>()

defineEmits<{
  dismiss: []
}>()

const label = computed(() => `@${props.context.name}`)

const icon = computed(() => {
  switch (props.context.type) {
    case 'company':
      return 'fa fa-building'
    case 'folder':
      return 'fa fa-folder'
    case 'organization':
      return 'fa fa-users'
    default:
      return 'fa fa-tag'
  }
})

const badgeClasses = computed(() => {
  const baseClasses = 'border'

  switch (props.context.type) {
    case 'company':
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:border-sage-300/40`
    case 'folder':
      return `${baseClasses} bg-almond-300 text-almond-950 border-almond-300/30 dark:bg-almond-300/20 dark:text-almond-300 dark:border-almond-300/40`
    case 'organization':
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:text-sage-300 dark:border-sage-300/40`
    default:
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:text-sage-300 dark:border-sage-300/40`
  }
})
</script>
