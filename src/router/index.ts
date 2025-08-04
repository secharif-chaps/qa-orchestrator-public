import { createRouter, createWebHistory } from 'vue-router'
import { routes } from 'vue-router/auto-routes'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Define public routes that don't require authentication
const publicRoutes = ['/login', '/auth/callback', '/auth/silent-callback', '/403']

// Check if a route is public
const isPublicRoute = (path: string): boolean => {
  return publicRoutes.some((route) => path.startsWith(route))
}

// Global navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Wait for auth initialization if not done yet
  if (!authStore.initialized) {
    try {
      await authStore.initialize()
    } catch (error) {
      console.error('Auth initialization failed:', error)
    }
  }

  const isAuthenticated = authStore.isAuthenticated
  const isPublic = isPublicRoute(to.path)

  // If route is public, allow access
  if (isPublic) {
    // Redirect authenticated users away from login page
    if (to.path === '/login' && isAuthenticated) {
      return next('/')
    }
    return next()
  }

  // For protected routes, check authentication
  if (!isAuthenticated) {
    // Store the intended route for redirect after login
    const redirectTo = to.fullPath !== '/' ? to.fullPath : undefined
    const loginQuery = redirectTo ? { redirect: redirectTo } : {}

    return next({
      path: '/login',
      query: loginQuery,
    })
  }

  // Check for required permissions if specified in route meta
  const requiredPermissions = to.meta.permissions as string[] | undefined
  if (requiredPermissions && requiredPermissions.length > 0) {
    const hasPermission = requiredPermissions.some(permission => 
      authStore.hasPermission(permission)
    )
    
    if (!hasPermission) {
      console.warn(`Access denied: User lacks required permissions for ${to.path}`, {
        required: requiredPermissions,
        userPermissions: authStore.userPermissions
      })
      
      // Redirect to 403 forbidden page
      return next({
        path: '/403',
        replace: true
      })
    }
  }

  // User is authenticated and has required permissions, allow access
  next()
})

export default router
