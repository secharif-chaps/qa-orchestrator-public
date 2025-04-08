import { useAuth } from '#imports'

export default defineNuxtRouteMiddleware(async (to) => {
  const { status } = useAuth()
  
  // Liste des routes publiques
  const publicRoutes = [
    '/',
    '/auth/login',
    '/auth/signup',
    '/auth/forgot-password',
    '/auth/reset-password'
  ]

  // Si la route est publique, on laisse passer
  if (publicRoutes.includes(to.path)) {
    return
  }

  // Si l'utilisateur n'est pas authentifié, on le redirige vers la page de login
  if (status.value === 'unauthenticated') {
    return navigateTo({
      path: '/auth/login',
      query: {
        redirect: to.fullPath
      }
    })
  }
}) 