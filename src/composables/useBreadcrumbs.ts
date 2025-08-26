import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'

export interface BreadcrumbItem {
  name: string
  to?: string
  current?: boolean
}

/**
 * Composable for generating breadcrumbs based on the current route
 */
export function useBreadcrumbs() {
  const route = useRoute()
  
  // Get company data if we're on a company page
  const companyId = computed(() => {
    if (typeof route.params.companyId === 'string') {
      return route.params.companyId
    }
    return null
  })
  
  const { data: company } = useQuery(
    companyByIdQuery,
    () => ({ id: companyId.value! }),
    {
      enabled: () => !!companyId.value,
    }
  )

  const breadcrumbs = computed((): BreadcrumbItem[] => {
    const items: BreadcrumbItem[] = []
    const pathSegments = route.path.split('/').filter(Boolean)
    
    // Handle different route patterns
    if (pathSegments.length === 0) {
      // Home page - no breadcrumbs needed
      return []
    }
    
    // Helper to build cumulative path
    const buildPath = (segments: string[], endIndex: number): string => {
      return '/' + segments.slice(0, endIndex + 1).join('/')
    }
    
    // Build breadcrumbs based on route structure
    for (let i = 0; i < pathSegments.length; i++) {
      const segment = pathSegments[i]
      const isLast = i === pathSegments.length - 1
      const currentPath = buildPath(pathSegments, i)
      
      switch (segment) {
        case 'companies':
          items.push({
            name: 'Companies',
            to: isLast ? undefined : '/companies',
            current: isLast
          })
          break
          
        case 'admin':
          items.push({
            name: 'Admin',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'settings':
          items.push({
            name: 'Settings',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'team':
          items.push({
            name: 'Team',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'search':
          items.push({
            name: 'Search',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        // Company-specific pages
        case 'index':
          // Skip the index segment for company dashboard
          break
          
        case 'profile':
          items.push({
            name: 'Profile',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'jobs':
          items.push({
            name: 'Jobs',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'timeline':
          items.push({
            name: 'Timeline',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'products':
          items.push({
            name: 'Products',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'press':
          items.push({
            name: 'Press',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        // Admin pages
        case 'workspaces':
          items.push({
            name: 'Workspaces',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'costs':
          items.push({
            name: 'Costs',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        // Settings pages
        case 'appearance':
          items.push({
            name: 'Appearance',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        case 'security':
          items.push({
            name: 'Security',
            to: isLast ? undefined : currentPath,
            current: isLast
          })
          break
          
        default:
          // Handle dynamic segments like company IDs
          if (pathSegments[i - 1] === 'companies' && segment !== 'companies') {
            // This is a company ID - show company name
            const companyName = company.value?.name || 'Company'
            items.push({
              name: companyName,
              to: isLast ? undefined : currentPath,
              current: isLast
            })
          } else if (pathSegments[i - 1] === 'admin' && segment.includes('.')) {
            // This might be a workspace ID
            items.push({
              name: 'Workspace',
              to: isLast ? undefined : currentPath,
              current: isLast
            })
          } else if (!segment.match(/^[0-9a-f-]{36}$/i) && !segment.match(/^\d+$/) && !segment.startsWith('[') && !segment.endsWith(']') && !segment.startsWith('(') && !segment.endsWith(')')) {
            // If it's not a UUID, number, route parameter, or route group, treat as a regular page name
            const pageName = segment.charAt(0).toUpperCase() + segment.slice(1).replace(/[-_]/g, ' ')
            items.push({
              name: pageName,
              to: isLast ? undefined : currentPath,
              current: isLast
            })
          }
          // Skip route groups like (list), (home) and dynamic segments like [companyId]
          break
      }
    }
    
    return items
  })
  
  return {
    breadcrumbs
  }
}

/**
 * Helper function to get page title from route name or path
 */
export function getPageTitle(routeName: string | null | undefined, routePath: string): string {
  if (routeName) {
    // Convert route names like '/companies/[companyId]/profile' to 'Profile'
    const parts = routeName.split('/')
    const lastPart = parts[parts.length - 1]
    if (lastPart && lastPart !== '[companyId]') {
      return lastPart.charAt(0).toUpperCase() + lastPart.slice(1)
    }
  }
  
  // Fallback to path-based title
  const pathParts = routePath.split('/').filter(Boolean)
  const lastPart = pathParts[pathParts.length - 1]
  
  if (lastPart && !lastPart.match(/^[0-9a-f-]{36}$/i) && !lastPart.match(/^\d+$/)) {
    return lastPart.charAt(0).toUpperCase() + lastPart.slice(1)
  }
  
  return 'Page'
}