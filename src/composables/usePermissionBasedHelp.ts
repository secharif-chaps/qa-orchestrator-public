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
 */
export const usePermissionBasedHelp = () => {
  const authStore = useAuthStore()

  // Define help sections with their required permissions
  const helpSections: HelpSection[] = [
    {
      permission: 'admin.costs',
      title: 'Cost Analysis Dashboard',
      description: 'Track and analyze Dify workflow costs, LLM usage, and external API expenses.',
      content: '', // Will be loaded dynamically
    },
    {
      permission: 'admin.organizations',
      title: 'organization Management',
      description:
        'Manage organizations, users, tokens, and module configurations across the platform.',
      content: '',
    },
    {
      permission: 'admin.workflows',
      title: 'Workflow Management',
      description: 'Configure Dify workflows, API keys, and LLM model assignments.',
      content: '',
    },
    {
      permission: 'company.create',
      title: 'Creating Company Screenings',
      description: 'Learn how to create new company screening tasks for risk assessment.',
      content: '',
    },
    {
      permission: 'company.view',
      title: 'Viewing Company Screenings',
      description: 'Access and navigate company screening lists and detailed results.',
      content: '',
    },
    {
      permission: 'company.delete',
      title: 'Deleting Company Screenings',
      description: 'Safely remove company screening records while maintaining compliance.',
      content: '',
    },
    {
      permission: 'organization.read',
      title: 'organization Team (Read Access)',
      description: 'View team information, member roles, and organization settings.',
      content: '',
    },
    {
      permission: 'organization.write',
      title: 'organization Team Management',
      description: 'Manage team members, roles, and permissions with full administrative control.',
      content: '',
    },
  ]

  // Get available help sections based on user permissions
  const availableHelpSections = computed(() => {
    return helpSections.filter((section) => authStore.hasPermission(section.permission))
  })

  // Load markdown content for a specific permission
  const loadHelpContent = async (permission: string): Promise<string> => {
    try {
      const module = await import(`@/assets/help/${permission}.md?raw`)
      return module.default
    } catch (error) {
      console.warn(`Failed to load help content for ${permission}:`, error)
      return '# Help content not found\n\nThis help section is not available at the moment.'
    }
  }

  // Get help sections with loaded content
  const getHelpSectionsWithContent = async (): Promise<HelpSection[]> => {
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
  const getHelpSection = (permission: string) => {
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
