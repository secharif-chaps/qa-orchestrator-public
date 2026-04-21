import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Stream permissions composable.
 * Provides granular permission checks for stream-related actions.
 */
export function useStreamPermissions() {
  const authStore = useAuthStore()

  /** Can view streams and deliveries */
  const canReadStreams = computed(() => authStore.hasPermission('stream.read'))

  /** Can create, edit, delete, and manage streams */
  const canWriteStreams = computed(() => authStore.hasPermission('stream.write'))

  return {
    canReadStreams,
    canWriteStreams,
  }
}
