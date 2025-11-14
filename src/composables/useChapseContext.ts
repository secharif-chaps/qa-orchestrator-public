import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { currentOrganizationQuery } from '@/queries/organization'
import type { ChapseContext } from './useChapseChat'

export function useChapseContext() {
  const route = useRoute()

  // Active contexts (contexts that will be sent with next message)
  const activeContexts = ref<ChapseContext[]>([])

  // Available contexts (contexts that can be added)
  const availableContexts = ref<ChapseContext[]>([])

  // Detect company context from route
  const companyId = computed(() => {
    // Check various route params that might contain company ID
    return route.params.companyId || route.params.id
  })

  // Fetch company data if on company page
  const { data: company, isLoading: isLoadingCompany } = useQuery(
    companyByIdQuery,
    () => ({ id: companyId.value as string }),
    {
      enabled: computed(() => !!companyId.value)
    }
  )

  // Fetch current organization
  const { data: organization, isLoading: isLoadingOrganization } = useQuery(
    currentOrganizationQuery,
    () => ({})
  )

  // Detect folder context from route
  const folderId = computed(() => route.params.folderId)

  // Auto-attach context (for company pages) - MUST be defined before updateAvailableContexts
  const autoAttachContext = (context: ChapseContext) => {
    // Only auto-attach if not already active
    const exists = activeContexts.value.find(
      ctx => ctx.type === context.type && ctx.id === context.id
    )
    if (!exists) {
      activeContexts.value.push(context)
    }
  }

  // Update available contexts based on current page
  const updateAvailableContexts = () => {
    const contexts: ChapseContext[] = []

    // Company context
    if (company.value && companyId.value) {
      const companyContext: ChapseContext = {
        type: 'company',
        id: companyId.value as string,
        name: company.value.name || 'Company',
        data: company.value
      }
      contexts.push(companyContext)

      // Auto-attach company context if on company page
      if (!activeContexts.value.find(ctx => ctx.type === 'company' && ctx.id === companyId.value)) {
        autoAttachContext(companyContext)
      }
    }

    // Folder context
    if (folderId.value) {
      // TODO: Fetch folder data when folder queries are available
      const folderContext: ChapseContext = {
        type: 'folder',
        id: folderId.value as string,
        name: 'Current Folder', // Replace with actual folder name
        data: { id: folderId.value }
      }
      contexts.push(folderContext)
    }

    // Organization context (always available)
    if (organization.value) {
      const organizationContext: ChapseContext = {
        type: 'organization', // Keep type as 'organization' for compatibility with ChapseContext interface
        id: organization.value.id,
        name: organization.value.name || 'Organization',
        data: organization.value
      }
      contexts.push(organizationContext)
    }

    availableContexts.value = contexts
  }

  // Watch route changes and update available contexts
  watch(
    [() => route.path, company, organization],
    () => {
      updateAvailableContexts()
    },
    { immediate: true }
  )

  // Add context manually
  const addContext = (context: ChapseContext) => {
    const exists = activeContexts.value.find(
      ctx => ctx.type === context.type && ctx.id === context.id
    )
    if (!exists) {
      activeContexts.value.push(context)
    }
  }

  // Remove context
  const removeContext = (contextId: string) => {
    activeContexts.value = activeContexts.value.filter(ctx => {
      const id = typeof ctx.id === 'number' ? ctx.id.toString() : ctx.id
      return id !== contextId
    })
  }

  // Clear all active contexts
  const clearActiveContexts = () => {
    activeContexts.value = []
  }

  // Check if context is active
  const isContextActive = (context: ChapseContext): boolean => {
    return activeContexts.value.some(
      ctx => ctx.type === context.type && ctx.id === context.id
    )
  }

  // Check if context is available
  const isContextAvailable = (contextType: 'company' | 'folder' | 'organization'): boolean => {
    return availableContexts.value.some(ctx => ctx.type === contextType)
  }

  // Get context badge label
  const getContextBadgeLabel = (context: ChapseContext): string => {
    return `@${context.name}`
  }

  // Get context icon
  const getContextIcon = (context: ChapseContext): string => {
    switch (context.type) {
      case 'company':
        return 'fa fa-building'
      case 'folder':
        return 'fa fa-folder'
      case 'organization':
        return 'fa fa-users'
      default:
        return 'fa fa-tag'
    }
  }

  // Handle context switch warning
  const shouldWarnContextSwitch = (newContext: ChapseContext): boolean => {
    // Warn if switching company context while another company context is active
    if (newContext.type === 'company') {
      const activeCompanyContext = activeContexts.value.find(ctx => ctx.type === 'company')
      return !!activeCompanyContext && activeCompanyContext.id !== newContext.id
    }
    return false
  }

  // Computed properties
  const hasActiveContexts = computed(() => activeContexts.value.length > 0)
  const hasAvailableContexts = computed(() => availableContexts.value.length > 0)

  return {
    // State
    activeContexts,
    availableContexts,
    isLoadingCompany,
    isLoadingOrganization,

    // Computed
    hasActiveContexts,
    hasAvailableContexts,

    // Methods
    addContext,
    removeContext,
    clearActiveContexts,
    isContextActive,
    isContextAvailable,
    getContextBadgeLabel,
    getContextIcon,
    shouldWarnContextSwitch
  }
}
