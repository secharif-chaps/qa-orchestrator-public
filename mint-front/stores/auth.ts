import { defineStore } from 'pinia'
import { useAuth } from '#imports'

interface User {
  id: string
  firstName: string
  lastName: string
  email: string
  avatar?: string
}

interface AuthState {
  user: User | null
  loading: boolean
  error: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    loading: false,
    error: null
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    fullName: (state) => state.user ? `${state.user.firstName} ${state.user.lastName}` : '',
    userAvatar: (state) => state.user?.avatar || 'https://via.placeholder.com/32'
  },

  actions: {
    async login(email: string, password: string) {
      try {
        this.loading = true
        this.error = null
        
        const { signIn } = useAuth()
        const result = await signIn({
          email,
          password
        })

        // Mettre à jour l'état avec les informations de l'utilisateur
        this.user = {
          id: result.user.id,
          firstName: result.user.firstName,
          lastName: result.user.lastName,
          email: result.user.email,
          avatar: result.user.avatar
        }

        return result
      } catch (error: any) {
        this.error = error.message || 'Une erreur est survenue lors de la connexion'
        throw error
      } finally {
        this.loading = false
      }
    },

    async signup(userData: { firstName: string; lastName: string; email: string; password: string }) {
      try {
        this.loading = true
        this.error = null
        
        const { signUp } = useAuth()
        const result = await signUp(userData)

        // Mettre à jour l'état avec les informations de l'utilisateur
        this.user = {
          id: result.user.id,
          firstName: result.user.firstName,
          lastName: result.user.lastName,
          email: result.user.email,
          avatar: result.user.avatar
        }

        return result
      } catch (error: any) {
        this.error = error.message || 'Une erreur est survenue lors de l\'inscription'
        throw error
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        this.loading = true
        this.error = null
        
        const { signOut } = useAuth()
        await signOut()
        
        // Réinitialiser l'état
        this.user = null
      } catch (error: any) {
        this.error = error.message || 'Une erreur est survenue lors de la déconnexion'
        throw error
      } finally {
        this.loading = false
      }
    },

    async forgotPassword(email: string) {
      try {
        this.loading = true
        this.error = null
        
        const { forgotPassword } = useAuth()
        await forgotPassword({ email })
      } catch (error: any) {
        this.error = error.message || 'Une erreur est survenue lors de la réinitialisation du mot de passe'
        throw error
      } finally {
        this.loading = false
      }
    },

    async resetPassword(token: string, password: string) {
      try {
        this.loading = true
        this.error = null
        
        const { resetPassword } = useAuth()
        await resetPassword({ token, password })
      } catch (error: any) {
        this.error = error.message || 'Une erreur est survenue lors de la réinitialisation du mot de passe'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    }
  },

  persist: {
    storage: process.client ? localStorage : undefined,
    paths: ['user']
  }
}) 