export default defineNuxtRouteMiddleware(async (to) => {
  // Skip middleware on server-side rendering
  if (process.server) return

  const { getUser, isAuthenticated } = useAuth()
  
  // Define public routes that don't require authentication
  const publicRoutes = [
    '/login',
    '/auth/callback',
    '/auth/silent-callback'
  ]
  
  // Check if current route is public
  const isPublicRoute = publicRoutes.some(route => to.path.startsWith(route))
  
  // If it's a public route, allow access
  if (isPublicRoute) {
    return
  }
  
  try {
    // Check if user is authenticated
    await getUser()
    
    if (!isAuthenticated.value) {
      // Redirect to login if not authenticated
      return navigateTo('/login')
    }
  } catch (error) {
    console.error('Auth middleware error:', error)
    // Redirect to login on any auth error
    return navigateTo('/login')
  }
})