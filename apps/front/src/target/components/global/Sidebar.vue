<template>
  <div
    ref="sidebarEl"
    class="bg-sage-950 dark:bg-sidebar flex h-screen w-full flex-col justify-between overflow-hidden text-white"
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
      <div v-show="sidebarStore.isMinimized"></div>
    </Transition>
  </div>
</template>

<script lang="ts" setup>
import { useSidebarStore } from '@target/stores/sidebar'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const sidebarStore = useSidebarStore()
const sidebarEl = ref<HTMLElement>()

const pendingAssistAction = ref<unknown>(null)

// Handle Chapse Assist quick action events
const handleAssistActionEvent = (event: CustomEvent) => {
  // Store the event data for ChapseSidebar to pick up
  pendingAssistAction.value = event.detail

  // Switch to chapse sidebar
  sidebarStore.setState('chapse')
}

onMounted(() => {
  window.addEventListener('chapse-assist-action', handleAssistActionEvent as EventListener)
})

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

// Determine transition direction based on button order
const isTransitioningRight = computed(() => {
  return sidebarStore.isTransitioningRight(sidebarStore.previousState, sidebarStore.state)
})

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
</script>
