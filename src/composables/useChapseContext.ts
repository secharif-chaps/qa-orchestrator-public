/**
 * Chapse Context Composable
 *
 * Handles automatic context detection and management for the Chapse chatbot.
 * Detects company context from route params and provides methods to add/remove context.
 */
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@pinia/colada'
import { companyByIdQuery } from '@/queries/companies'
import { useChapseStore, type CompanyContext } from '@/stores/chapse'

// Maximum companies allowed in context
const MAX_COMPANY_CONTEXT = 3

export function useChapseContext() {
  const route = useRoute()
  const store = useChapseStore()

  // =========================================================================
  // Route-based Context Detection
  // =========================================================================

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
      enabled: computed(() => !!companyId.value),
    },
  )

  // =========================================================================
  // Available Context from Current Page
  // =========================================================================

  // Context available from the current page (company page provides company context)
  const availablePageContext = computed<CompanyContext | null>(() => {
    if (company.value && companyId.value) {
      return {
        id: Number(companyId.value),
        name: company.value.name || 'Company',
        siren: company.value.siren || null,
      }
    }
    return null
  })

  // =========================================================================
  // Store-based Context (Active Contexts)
  // =========================================================================

  const activeContexts = computed(() => store.companyContext)
  const hasActiveContexts = computed(() => store.companyContext.length > 0)
  const canAddMoreCompanies = computed(() => store.canAddMoreCompanies)
  const contextCount = computed(() => store.companyContextCount)

  // =========================================================================
  // Context Actions
  // =========================================================================

  /**
   * Add a company to the active context.
   * Returns false if limit reached or company already in context.
   */
  function addContext(company: CompanyContext): boolean {
    return store.addCompanyToContext(company)
  }

  /**
   * Add the company from the current page to context.
   * Returns false if no page context or limit reached.
   */
  function addPageContextToChat(): boolean {
    if (!availablePageContext.value) return false
    return store.addCompanyToContext(availablePageContext.value)
  }

  /**
   * Remove a company from context by ID.
   */
  function removeContext(companyId: number): void {
    store.removeCompanyFromContext(companyId)
  }

  /**
   * Clear all active contexts.
   */
  function clearActiveContexts(): void {
    store.clearCompanyContext()
  }

  /**
   * Check if a company is currently in context.
   */
  function isContextActive(companyId: number): boolean {
    return store.isCompanyInContext(companyId)
  }

  /**
   * Check if the current page's company is in context.
   */
  const isPageContextActive = computed(() => {
    if (!availablePageContext.value) return false
    return store.isCompanyInContext(availablePageContext.value.id)
  })

  // =========================================================================
  // Context Display Helpers
  // =========================================================================

  /**
   * Get a badge label for context display.
   */
  function getContextBadgeLabel(context: CompanyContext): string {
    return context.name
  }

  /**
   * Get context icon class.
   */
  function getContextIcon(): string {
    return 'fa fa-building'
  }

  /**
   * Get context limit display string.
   */
  const contextLimitDisplay = computed(() => {
    return `${contextCount.value}/${MAX_COMPANY_CONTEXT}`
  })

  // =========================================================================
  // Auto-attach Behavior (optional)
  // =========================================================================

  /**
   * Watch route changes and optionally auto-attach company context.
   * This is disabled by default - enable if you want automatic context attachment.
   */
  function enableAutoAttach(enabled: boolean = true): void {
    if (!enabled) return

    watch(
      [() => route.path, company],
      () => {
        if (availablePageContext.value && !isPageContextActive.value) {
          // Only auto-attach if we have room
          if (canAddMoreCompanies.value) {
            addPageContextToChat()
          }
        }
      },
      { immediate: true },
    )
  }

  // =========================================================================
  // Return
  // =========================================================================

  return {
    // Route-based context
    availablePageContext,
    isLoadingCompany,
    isPageContextActive,

    // Active contexts from store
    activeContexts,
    hasActiveContexts,
    canAddMoreCompanies,
    contextCount,
    contextLimitDisplay,

    // Actions
    addContext,
    addPageContextToChat,
    removeContext,
    clearActiveContexts,
    isContextActive,

    // Helpers
    getContextBadgeLabel,
    getContextIcon,
    enableAutoAttach,
  }
}
