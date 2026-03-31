<template>
  <div
    ref="sidebarEl"
    class="dark:bg-sidebar flex w-full flex-col justify-between overflow-hidden text-white"
    @wheel="handleWheel"
  >
    <Transition
      mode="out-in"
      :enter-active-class="transitionClasses.enterActive"
      :leave-active-class="transitionClasses.leaveActive"
      :enter-from-class="transitionClasses.enterFrom"
      :enter-to-class="transitionClasses.enterTo"
      :leave-from-class="transitionClasses.leaveFrom"
      :leave-to-class="transitionClasses.leaveTo"
    >
      <component
        :is="currentComponent"
        :key="sidebarStore.state"
        :pending-assist-action="pendingAssistAction"
        @assist-action-processed="pendingAssistAction = null"
      />
    </Transition>

    <!-- Footer Actions -->
    <div v-if="!sidebarStore.isFullscreen" class="w-[320px] px-6 delay-500">
      <div class="border-sage-300 dark:border-sage-800 z-50 grid grid-cols-2 border-t py-3">
        <button
          class="text-sage-700 dark:text-sage-300 hover:text-sage-950 flex flex-col items-center gap-1 transition-colors dark:hover:text-white"
          @click="$router.push('/settings')"
        >
          <Icon icon="fa-cog" class="text-lg" />
          <span class="text-xs">{{ $t('common.sidebar.footer.settings') }}</span>
        </button>
        <button
          class="text-sage-700 dark:text-sage-300 hover:text-sage-950 flex flex-col items-center gap-1 transition-colors dark:hover:text-white"
          @click="toggleAccessibilityMode()"
        >
          <Icon icon="fa-universal-access" class="text-lg" />
          <span class="text-xs">{{ $t('common.sidebar.footer.accessibility') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import ChapseSidebar from '@/components/sidebar/ChapseSidebar.vue'
import FoldersSidebar from '@/components/sidebar/FoldersSidebar.vue'
import NotificationsSidebar from '@/components/sidebar/NotificationsSidebar.vue'
import TokenSidebar from '@/components/sidebar/TokenSidebar.vue'
import { useSidebarStore } from '@/stores/sidebar'
import { toast } from '@/utils/toast'
import { Icon } from '@owlint/feathers-vue'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const sidebarStore = useSidebarStore()
const sidebarEl = ref<HTMLElement>()
const { t } = useI18n()

// Store pending assist action event data
const pendingAssistAction = ref<Record<string, unknown> | null>(null)

// Handle Chapse Assist quick action events
const handleAssistActionEvent = (event: CustomEvent) => {
  console.log('📩 Global sidebar received chapse-assist-action event:', event.detail)

  // Store the event data for ChapseSidebar to pick up
  pendingAssistAction.value = event.detail

  // Switch to chapse sidebar
  sidebarStore.setState('chapse')

  console.log('✅ Sidebar switched to chapse state')
}

// Add event listener on mount
onMounted(() => {
  console.log('🎧 Global sidebar: Adding event listener for chapse-assist-action')
  window.addEventListener('chapse-assist-action', handleAssistActionEvent as EventListener)
  console.log('✅ Global sidebar: Event listener added')
})

// Remove event listener on unmount
onUnmounted(() => {
  window.removeEventListener('chapse-assist-action', handleAssistActionEvent as EventListener)
})

// Horizontal scroll configuration
const SCROLL_THRESHOLD = 50 // Pixels of accumulated horizontal scroll needed to switch tabs
let scrollDelta = 0
const isNavigating = ref(false) // Prevent multiple navigations in one gesture

// Handle horizontal scroll/swipe gestures
function handleWheel(event: WheelEvent) {
  // Detect horizontal scroll (deltaX for trackpad swipe, deltaY with shift for mouse wheel)
  const horizontalDelta =
    Math.abs(event.deltaX) > Math.abs(event.deltaY)
      ? event.deltaX
      : event.shiftKey
        ? event.deltaY
        : 0

  // If no horizontal scroll detected, allow normal vertical/horizontal scrolling
  if (horizontalDelta === 0) return

  // Check if the target element or its parents are scrollable horizontally
  let target = event.target as HTMLElement
  while (target && target !== sidebarEl.value) {
    const hasHorizontalScroll = target.scrollWidth > target.clientWidth
    const computedStyle = window.getComputedStyle(target)
    const overflowX = computedStyle.overflowX

    // If element is scrollable horizontally, allow native scroll
    if (hasHorizontalScroll && (overflowX === 'auto' || overflowX === 'scroll')) {
      return
    }
    target = target.parentElement as HTMLElement
  }

  // If already navigating, ignore additional scroll events
  if (isNavigating.value) {
    event.preventDefault()
    return
  }

  // Only prevent default when we're using it for tab navigation
  event.preventDefault()

  // Accumulate scroll delta
  scrollDelta += horizontalDelta

  // Check if threshold is reached for next tab (scroll right)
  if (scrollDelta >= SCROLL_THRESHOLD) {
    const success = sidebarStore.navigateNext()
    if (success) {
      isNavigating.value = true
      scrollDelta = 0
      // Reset navigation lock after a short delay to allow new gestures
      setTimeout(() => {
        isNavigating.value = false
      }, 300)
    } else {
      // At boundary, limit accumulation
      scrollDelta = SCROLL_THRESHOLD
    }
  }
  // Check if threshold is reached for previous tab (scroll left)
  else if (scrollDelta <= -SCROLL_THRESHOLD) {
    const success = sidebarStore.navigatePrevious()
    if (success) {
      isNavigating.value = true
      scrollDelta = 0
      // Reset navigation lock after a short delay to allow new gestures
      setTimeout(() => {
        isNavigating.value = false
      }, 300)
    } else {
      // At boundary, limit accumulation
      scrollDelta = -SCROLL_THRESHOLD
    }
  }
}

const currentComponent = computed(() => {
  switch (sidebarStore.state) {
    case 'tokens':
      return TokenSidebar
    case 'chapse':
      return ChapseSidebar
    case 'notifications':
      return NotificationsSidebar
    case 'folders':
      return FoldersSidebar
    default:
      return FoldersSidebar
  }
})

// Determine transition direction based on button order
const isTransitioningRight = computed(() => {
  return sidebarStore.isTransitioningRight(sidebarStore.previousState, sidebarStore.state)
})

// Dynamic transition classes based on direction
const transitionClasses = computed(() => {
  if (isTransitioningRight.value) {
    // Moving to the right (higher index) - slide from right to left
    return {
      enterActive: 'transition-all duration-150 ease-in',
      leaveActive: 'transition-all duration-150 ease-out',
      enterFrom: 'translate-x-32 opacity-0',
      enterTo: 'translate-x-0 opacity-100',
      leaveFrom: 'translate-x-0 opacity-100',
      leaveTo: '-translate-x-32 opacity-0',
    }
  } else {
    // Moving to the left (lower index) - slide from left to right
    return {
      enterActive: 'transition-all duration-150 ease-out',
      leaveActive: 'transition-all duration-150 ease-in',
      enterFrom: '-translate-x-32 opacity-0',
      enterTo: 'translate-x-0 opacity-100',
      leaveFrom: 'translate-x-0 opacity-100',
      leaveTo: 'translate-x-32 opacity-0',
    }
  }
})

const toggleAccessibilityMode = () => {
  toast.info(t('common.comingSoon'), {
    duration: 3000,
  })
}
</script>
