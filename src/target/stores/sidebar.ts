import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export type SidebarState = 'folders' | 'tokens' | 'chapse' | 'notifications' | 'minimized'

const STORAGE_KEY = 'sidebar-state'

export const useSidebarStore = defineStore('sidebar', () => {
  const state = ref<SidebarState>('minimized')
  const previousState = ref<SidebarState>('folders')
  const isFullscreen = ref(false)

  // Button order from left to right in the appbar
  const stateOrder: SidebarState[] = ['tokens', 'chapse', 'notifications', 'folders']

  const isMinimized = computed(() => state.value === 'minimized')
  const isTokens = computed(() => state.value === 'tokens')
  const isChapse = computed(() => state.value === 'chapse')
  const isNotifications = computed(() => state.value === 'notifications')
  const isFolders = computed(() => state.value === 'folders')

  // Get the index of a state in the order
  function getStateIndex(s: SidebarState): number {
    return stateOrder.indexOf(s)
  }

  // Determine if transitioning to the right (higher index)
  function isTransitioningRight(fromState: SidebarState, toState: SidebarState): boolean {
    const fromIndex = getStateIndex(fromState)
    const toIndex = getStateIndex(toState)
    return toIndex > fromIndex
  }

  // Load state from localStorage
  function loadState() {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored && isValidSidebarState(stored)) {
      state.value = stored as SidebarState
      previousState.value = stored as SidebarState
    }
  }

  // Save state to localStorage
  function saveState() {
    localStorage.setItem(STORAGE_KEY, state.value)
  }

  // Validate state
  function isValidSidebarState(value: string): boolean {
    return ['folders', 'tokens', 'chapse', 'notifications', 'minimized'].includes(value)
  }

  // Set sidebar state
  function setState(newState: SidebarState) {
    // If leaving chapse state while in fullscreen, exit fullscreen
    if (state.value === 'chapse' && isFullscreen.value && newState !== 'chapse') {
      isFullscreen.value = false
    }

    previousState.value = state.value
    state.value = newState
    saveState()
  }

  // Toggle sidebar state (for button clicks)
  function toggleState(targetState: SidebarState) {
    if (isMinimized.value) {
      // If minimized, open to target state
      setState(targetState)
    } else if (state.value === targetState) {
      // If already showing this state, minimize
      setState('minimized')
    } else {
      // If showing different state, switch to target
      setState(targetState)
    }
  }

  // Check if sidebar is open
  function isOpen() {
    return !isMinimized.value
  }

  // Navigate to next tab in the order
  function navigateNext() {
    const currentIndex = getStateIndex(state.value)
    const nextIndex = currentIndex + 1

    // Don't navigate if at the end or minimized
    if (nextIndex >= stateOrder.length || isMinimized.value) {
      return false
    }

    if (stateOrder[nextIndex]) {
      setState(stateOrder[nextIndex])
    }
    return true
  }

  // Navigate to previous tab in the order
  function navigatePrevious() {
    const currentIndex = getStateIndex(state.value)
    const previousIndex = currentIndex - 1

    // Don't navigate if at the beginning or minimized
    if (previousIndex < 0 || isMinimized.value) {
      return false
    }
    if (stateOrder[previousIndex]) {
      setState(stateOrder[previousIndex])
    }

    return true
  }

  function setFullscreen(value: boolean) {
    isFullscreen.value = value
  }

  return {
    state,
    previousState,
    isFullscreen,
    loadState,
    setState,
    toggleState,
    isOpen,
    isTransitioningRight,
    navigateNext,
    navigatePrevious,
    setFullscreen,

    isMinimized,
    isTokens,
    isChapse,
    isNotifications,
    isFolders,
  }
})
