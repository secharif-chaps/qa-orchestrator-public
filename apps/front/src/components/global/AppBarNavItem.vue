<template>
  <button
    class="text-sage-900 dark:text-sage-300 focus-visible:ring-accent-700 relative flex size-9 cursor-pointer items-center justify-center rounded p-2 transition-colors focus-visible:ring-2 focus-visible:outline-none"
    :class="[activeClass]"
    :aria-label="badgeLabel"
  >
    <Icon :icon class="text-xl" :class="{ 'mb-1': active }" />
    <!-- Active indicator bar -->
    <span
      v-if="active"
      class="bg-sage-400 absolute bottom-px left-1/2 h-1 w-4 -translate-x-1/2 rounded-full"
    />
    <!-- Unread badge -->
    <span
      v-if="showBadge"
      class="bg-error text-error-content absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-semibold"
    >
      {{ badgeText }}
    </span>
  </button>
</template>

<script lang="ts" setup>
import { Icon } from '@owlint/feathers-vue'
import { computed } from 'vue'

interface Props {
  icon: string
  active: boolean
  unreadCount?: number
}

const { icon, active, unreadCount = 0 } = defineProps<Props>()

const showBadge = computed(() => unreadCount > 0)
const badgeText = computed(() => (unreadCount > 9 ? '9+' : String(unreadCount)))
const badgeLabel = computed(() =>
  showBadge.value ? `${unreadCount} unread notifications` : undefined,
)

const pressedClass = 'active:bg-sage-300 active:text-almond-950'
const hoverClass = 'hover:bg-sage-950 hover:text-white'

const activeClass = computed(() => {
  if (active) {
    return ''
  }
  return [pressedClass, hoverClass]
})
</script>
