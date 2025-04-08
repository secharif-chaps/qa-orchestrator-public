import { useAuthStore } from '~/stores/auth'
import { useAuth } from '#imports'

export default defineNuxtPlugin(async () => {
  const authStore = useAuthStore()
  const { status } = useAuth()

  // Écouter les changements d'état d'authentification
  watch(status, async (newStatus) => {
    if (newStatus === 'authenticated') {
      // Récupérer les informations de l'utilisateur
      const { data: user } = await useFetch('/api/auth/user')
      
      if (user.value) {
        authStore.user = {
          id: user.value.id,
          firstName: user.value.firstName,
          lastName: user.value.lastName,
          email: user.value.email,
          avatar: user.value.avatar
        }
      }
    } else if (newStatus === 'unauthenticated') {
      // Réinitialiser l'état de l'utilisateur
      authStore.user = null
    }
  }, { immediate: true })
}) 