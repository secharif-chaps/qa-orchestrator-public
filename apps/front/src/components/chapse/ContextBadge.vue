<template>
  <div
    class="inline-flex items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-medium transition-all"
    :class="badgeClasses"
  >
    <Icon :icon class="text-xs" />
    <span class="max-w-[120px] truncate">{{ label }}</span>
    <button
      v-if="dismissible"
      @click="$emit('dismiss')"
      class="shrink-0 rounded p-0.5 transition-colors hover:bg-black/10 dark:hover:bg-white/10"
      :aria-label="`Remove ${label} context`"
    >
      <i class="fa fa-times text-xs"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

// Support both old ChapseContext format and new CompanyContext format
interface ContextProp {
  type?: 'company' | 'folder' | 'organization' | string
  id: string | number
  name: string
}

const props = defineProps<{
  context: ContextProp
  dismissible?: boolean
}>()

defineEmits<{
  dismiss: []
}>()

const label = computed(() => props.context.name)

const icon = computed(() => {
  switch (props.context.type) {
    case 'company':
      return 'fa-building'
    case 'folder':
      return 'fa-folder'
    case 'organization':
      return 'fa-users'
    default:
      return 'fa-building' // Default to company icon
  }
})

const badgeClasses = computed(() => {
  const baseClasses = 'border'

  switch (props.context.type) {
    case 'company':
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:text-sage-200 dark:border-sage-300/40`
    case 'folder':
      return `${baseClasses} bg-almond-300 text-almond-950 border-almond-300/30 dark:bg-almond-300/20 dark:text-almond-300 dark:border-almond-300/40`
    case 'organization':
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:text-sage-300 dark:border-sage-300/40`
    default:
      // Default styling for company context (when type is not specified)
      return `${baseClasses} bg-sage-300 text-sage-950 border-sage-300/30 dark:bg-sage-300/20 dark:text-sage-200 dark:border-sage-300/40`
  }
})
</script>
