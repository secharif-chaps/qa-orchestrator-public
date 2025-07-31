export default defineNuxtRouteMiddleware(async (to) => {
  // Skip middleware on server-side rendering
  if (process.server) return

  const authStore = useAuthStore()
  const { fetchCurrentWorkspace } = useWorkspace()
  
  // Define public routes that don't require authentication
  const publicRoutes = [
    '/login',
    '/auth/callback',
    '/auth/silent-callback'
  ]
  
  // Define workspace-related routes that don't require workspace access
  const workspaceRoutes = [
    '/workspace/join'
  ]
  
  // Check if current route is public or workspace-related
  const isPublicRoute = publicRoutes.some(route => to.path.startsWith(route))
  const isWorkspaceRoute = workspaceRoutes.some(route => to.path.startsWith(route))
  
  // If it's a public route, allow access
  if (isPublicRoute) {
    return
  }
  
  try {
    // Check if user is authenticated
    await authStore.getUser()
    
    if (!authStore.isAuthenticated) {
      // Redirect to login if not authenticated
      return navigateTo('/login')
    }
    
    // Skip workspace check for workspace-related routes
    if (isWorkspaceRoute) {
      return
    }
    
    // Check if user has workspace access
    try {
      const result = await fetchCurrentWorkspace()
      if (result.error.value) {
        // User doesn't have workspace access, redirect to join page
        return navigateTo('/workspace/join')
      }
    } catch (workspaceError) {
      // On workspace error, redirect to join page
      return navigateTo('/workspace/join')
    }
  } catch (error) {
    console.error('Auth middleware error:', error)
    // Redirect to login on any auth error
    return navigateTo('/login')
  }
})