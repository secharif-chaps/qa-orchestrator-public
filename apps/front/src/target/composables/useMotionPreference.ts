import { ref, readonly, computed, onMounted, onUnmounted } from 'vue'

/**
 * Composable to detect user's motion preference
 * Returns a reactive boolean indicating if user prefers reduced motion
 *
 * @returns {Object} Object containing:
 *   - prefersReducedMotion: Reactive ref<boolean> - true if user prefers reduced motion
 *   - allowAnimations: Reactive computed<boolean> - convenience getter (inverse of prefersReducedMotion)
 */
export function useMotionPreference() {
  const prefersReducedMotion = ref(false)
  let mediaQuery: MediaQueryList | null = null
  let cleanup: (() => void) | null = null

  // Handler for media query changes
  const handleChange = (event: MediaQueryListEvent) => {
    prefersReducedMotion.value = event.matches
  }

  onMounted(() => {
    // Check if we're in a browser environment
    if (typeof window !== 'undefined' && 'matchMedia' in window) {
      mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')

      // Set initial value
      prefersReducedMotion.value = mediaQuery.matches

      // Listen for changes
      if (mediaQuery.addEventListener) {
        // Modern browsers
        mediaQuery.addEventListener('change', handleChange)
        cleanup = () => mediaQuery?.removeEventListener('change', handleChange)
      }
    }
  })

  // Clean up event listeners
  onUnmounted(() => {
    cleanup?.()
  })

  return {
    prefersReducedMotion: readonly(prefersReducedMotion),
    // Convenience getter for easier usage
    allowAnimations: computed(() => !prefersReducedMotion.value),
  }
}
