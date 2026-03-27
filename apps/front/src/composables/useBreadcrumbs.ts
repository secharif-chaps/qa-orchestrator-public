import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { useI18n } from 'vue-i18n'
import { companyByIdQuery } from '@/queries/companies'
import { folderByIdQuery } from '@/queries/folders'

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
  const { t } = useI18n()

  // Get company data if we're on a company page
  // Use type assertion since route.params may have companyId on some routes
  const companyId = computed(() => {
    const params = route.params as Record<string, string | string[] | undefined>
    if (typeof params.companyId === 'string') {
      return params.companyId
    }
    return null
  })

  const { data: company } = useQuery(() => companyByIdQuery({ id: companyId.value ?? '' }))

  // Get folder data if we're on a folder page
  // Use type assertion since route.params may have folderId on some routes
  const folderId = computed(() => {
    const params = route.params as Record<string, string | string[] | undefined>
    if (typeof params.folderId === 'string') {
      return params.folderId
    }
    return null
  })

  const { data: folder } = useQuery(() => folderByIdQuery({ id: folderId.value ?? '' }))

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
          // Only add 'Companies' breadcrumb if it's not under folders
          if (!pathSegments.includes('folders')) {
            items.push({
              name: t('common.breadcrumb.companies', 'Companies'),
              to: isLast ? undefined : '/companies',
              current: isLast,
            })
          } else if (i > 0 && pathSegments[i - 1] !== 'folders') {
            // This is companies under a folder - don't add a separate breadcrumb
            // The company name will be added in the default case
          }
          break

        case 'folders':
          items.push({
            name: t('common.breadcrumb.folders', 'Folders'),
            to: '/folders',
            current: isLast,
          })
          break

        case 'team':
          items.push({
            name: t('common.breadcrumb.team', 'Team'),
            to: undefined,
            current: isLast,
          })
          break

        case 'admin':
          items.push({
            name: t('common.breadcrumb.admin', 'Admin'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'settings':
          items.push({
            name: t('common.breadcrumb.settings', 'Settings'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'search':
          items.push({
            name: t('common.breadcrumb.search', 'Search'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        // Company-specific pages
        case 'index':
          // Skip the index segment for company dashboard
          break

        case 'profile':
          items.push({
            name: t('common.breadcrumb.profile', 'Profile'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'jobs':
          items.push({
            name: t('common.breadcrumb.jobs', 'Jobs'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'timeline':
          items.push({
            name: t('common.breadcrumb.timeline', 'Timeline'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'products':
          items.push({
            name: t('common.breadcrumb.products', 'Products'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'press':
          items.push({
            name: t('common.breadcrumb.press', 'Press'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'edit':
          // For folder edit pages
          if (pathSegments.includes('folders')) {
            items.push({
              name: t('common.breadcrumb.edit', 'Edit'),
              to: isLast ? undefined : currentPath,
              current: isLast,
            })
          }
          break

        case 'create':
          // For folder create pages - not clickable as there's no /create route
          if (pathSegments.includes('folders')) {
            items.push({
              name: t('common.breadcrumb.create', 'Create'),
              to: undefined, // Always undefined - no standalone /create page exists
              current: isLast,
            })
          }
          break

        case 'company':
          // For specific create pages like /folders/[id]/create/company
          if (pathSegments.includes('folders') && pathSegments.includes('create')) {
            items.push({
              name: t('common.breadcrumb.company', 'Company'),
              to: isLast ? undefined : currentPath,
              current: isLast,
            })
          }
          break

        // Admin pages
        case 'users':
          items.push({
            name: t('common.breadcrumb.users', 'Users'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'organizations':
          items.push({
            name: t('common.breadcrumb.organizations', 'Organizations'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'tokens':
          items.push({
            name: t('common.breadcrumb.tokens', 'Tokens'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'members':
          items.push({
            name: t('common.breadcrumb.members', 'Members'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'sources':
          items.push({
            name: t('common.breadcrumb.sources', 'Sources'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'tasks':
          items.push({
            name: t('common.breadcrumb.tasks', 'Tasks'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'usage':
          items.push({
            name: t('common.breadcrumb.usage', 'Usage'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'workflows':
          items.push({
            name: t('common.breadcrumb.workflows', 'Workflows'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'history':
          items.push({
            name: t('common.breadcrumb.history', 'History'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'costs':
          items.push({
            name: t('common.breadcrumb.costs', 'Costs'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'csr':
          items.push({
            name: t('common.breadcrumb.csr', 'CSR'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'company-csv':
          items.push({
            name: t('common.breadcrumb.companyCsv', 'CSV Import'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'help':
          items.push({
            name: t('common.breadcrumb.help', 'Help'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        // Settings pages
        case 'credits':
          items.push({
            name: t('common.breadcrumb.credits', 'Credits'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'ai-preferences':
          items.push({
            name: t('common.breadcrumb.aiPreferences', 'AI Preferences'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'ai-preferences-setup':
          items.push({
            name: t('common.breadcrumb.aiPreferencesSetup', 'AI Preferences Setup'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'appearance':
          items.push({
            name: t('common.breadcrumb.appearance', 'Appearance'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'security':
          items.push({
            name: t('common.breadcrumb.security', 'Security'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        case 'team-management':
          items.push({
            name: t('common.breadcrumb.teamManagement'),
            to: isLast ? undefined : currentPath,
            current: isLast,
          })
          break

        default:
          // Handle dynamic segments like company IDs and folder IDs
          if (pathSegments[i - 1] === 'companies' && segment !== 'companies') {
            // This is a company ID - show company name with link if not last
            const companyName = company.value?.name || 'Company'
            // For company pages under folders, build the correct path
            const companyPath =
              pathSegments.includes('folders') && folderId.value
                ? `/folders/${folderId.value}/companies/${segment}`
                : currentPath
            items.push({
              name: companyName,
              to: isLast ? undefined : companyPath,
              current: isLast,
            })
          } else if (pathSegments[i - 1] === 'folders' && segment !== 'folders') {
            // This is a folder ID - show folder name with link to folder page
            const folderName = folder.value?.name || 'Folder'
            items.push({
              name: folderName,
              to: `/folders/${segment}`,
              current: isLast,
            })
          } else if (pathSegments[i - 1] === 'admin' && segment.includes('.')) {
            // This might be a organization ID
            items.push({
              name: 'organization',
              to: isLast ? undefined : currentPath,
              current: isLast,
            })
          } else if (
            !segment.match(/^[0-9a-f-]{36}$/i) &&
            !segment.match(/^\d+$/) &&
            !segment.startsWith('[') &&
            !segment.endsWith(']') &&
            !segment.startsWith('(') &&
            !segment.endsWith(')')
          ) {
            // If it's not a UUID, number, route parameter, or route group, treat as a regular page name
            const pageName =
              segment.charAt(0).toUpperCase() + segment.slice(1).replace(/[-_]/g, ' ')
            items.push({
              name: pageName,
              to: isLast ? undefined : currentPath,
              current: isLast,
            })
          }
          // Skip route groups like (list), (home) and dynamic segments like [companyId]
          break
      }
    }

    return items
  })

  return {
    breadcrumbs,
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
