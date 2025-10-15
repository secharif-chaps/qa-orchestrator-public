import { defineStore } from 'pinia'
import { ref } from 'vue'

export type SidebarState = 'folders' | 'tokens' | 'chapse' | 'notifications' | 'minimized'

const STORAGE_KEY = 'sidebar-state'

export const useSidebarStore = defineStore('sidebar', () => {
  const state = ref<SidebarState>('folders')
  const previousState = ref<SidebarState>('folders')
  const isFullscreen = ref(false)

  // Button order from left to right in the appbar
  const stateOrder: SidebarState[] = ['tokens', 'chapse', 'notifications', 'folders']

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
    if (state.value === 'minimized') {
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
    return state.value !== 'minimized'
  }

  // Navigate to next tab in the order
  function navigateNext() {
    const currentIndex = getStateIndex(state.value)
    const nextIndex = currentIndex + 1

    // Don't navigate if at the end or minimized
    if (nextIndex >= stateOrder.length || state.value === 'minimized') {
      return false
    }

    setState(stateOrder[nextIndex])
    return true
  }

  // Navigate to previous tab in the order
  function navigatePrevious() {
    const currentIndex = getStateIndex(state.value)
    const previousIndex = currentIndex - 1

    // Don't navigate if at the beginning or minimized
    if (previousIndex < 0 || state.value === 'minimized') {
      return false
    }

    setState(stateOrder[previousIndex])
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
  }
})