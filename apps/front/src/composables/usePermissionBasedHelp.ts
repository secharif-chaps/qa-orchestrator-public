import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

interface HelpSection {
  permission: string
  title: string
  description: string
  content: string
}

/**
 * Permission-based help system composable
 * Provides help content based on user's current permissions
 *
 * Updated to use new permission model:
 * - company.* permissions replaced with screen.create / organization.read
 * - Legacy permissions kept for backward compatibility
 */
export function usePermissionBasedHelp() {
  const authStore = useAuthStore()

  // Define help sections with their required permissions
  // Updated to use new permission model with legacy fallbacks
  const helpSections: HelpSection[] = [
    {
      permission: 'admin.costs',
      title: 'Cost Analysis Dashboard',
      description: 'Track and analyze Dify workflow costs, LLM usage, and external API expenses.',
      content: '', // Will be loaded dynamically
    },
    {
      permission: 'admin.organizations',
      title: 'Organization Management',
      description:
        'Manage organizations, users, tokens, and module configurations across the platform.',
      content: '',
    },
    {
      // Updated: screen.create is the new permission for creating companies
      permission: 'screen.create',
      title: 'Creating Company Screenings',
      description: 'Learn how to create new company screening tasks for risk assessment.',
      content: '',
    },
    {
      // Updated: organization.read is the base access for viewing
      permission: 'organization.read',
      title: 'Viewing Company Screenings',
      description: 'Access and navigate company screening lists and detailed results.',
      content: '',
    },
    {
      permission: 'organization.write',
      title: 'Folder Management',
      description: 'Create and manage folders to organize your company screenings.',
      content: '',
    },
    {
      permission: 'target.create',
      title: 'Creating Target Watch Files',
      description: 'Set up and manage target monitoring watch files.',
      content: '',
    },
  ]

  // Get available help sections based on user permissions
  const availableHelpSections = computed(() => {
    return helpSections.filter((section) => {
      // Check for the new permission
      if (authStore.hasPermission(section.permission)) {
        return true
      }

      // Legacy permission fallbacks
      if (section.permission === 'screen.create') {
        return authStore.hasPermission('company.create')
      }
      if (section.permission === 'organization.read') {
        return authStore.hasPermission('company.view')
      }

      return false
    })
  })

  // Load markdown content for a specific permission
  async function loadHelpContent(permission: string): Promise<string> {
    try {
      const module = await import(`@/assets/help/${permission}.md?raw`)
      return module.default
    } catch (error) {
      console.warn(`Failed to load help content for ${permission}:`, error)
      return '# Help content not found\n\nThis help section is not available at the moment.'
    }
  }

  // Get help sections with loaded content
  async function getHelpSectionsWithContent(): Promise<HelpSection[]> {
    const sectionsWithContent = await Promise.all(
      availableHelpSections.value.map(async (section) => {
        const content = await loadHelpContent(section.permission)
        return {
          ...section,
          content,
        }
      }),
    )
    return sectionsWithContent
  }

  // Check if user has access to any help content
  const hasAnyHelpAccess = computed(() => {
    return availableHelpSections.value.length > 0
  })

  // Get help section by permission
  function getHelpSection(permission: string) {
    return availableHelpSections.value.find((section) => section.permission === permission)
  }

  // Get all unique permission categories for grouping
  const helpCategories = computed(() => {
    const categories = new Set<string>()
    availableHelpSections.value.forEach((section) => {
      const category = section.permission.split('.')[0]
      categories.add(category)
    })
    return Array.from(categories)
  })

  // Group help sections by category
  const helpSectionsByCategory = computed(() => {
    const grouped: Record<string, HelpSection[]> = {}

    availableHelpSections.value.forEach((section) => {
      const category = section.permission.split('.')[0]
      if (!grouped[category]) {
        grouped[category] = []
      }
      grouped[category].push(section)
    })

    return grouped
  })

  return {
    availableHelpSections,
    hasAnyHelpAccess,
    helpCategories,
    helpSectionsByCategory,
    loadHelpContent,
    getHelpSectionsWithContent,
    getHelpSection,
  }
}

// Export for backward compatibility
export const usePermissionBasedHelpComposable = usePermissionBasedHelp
