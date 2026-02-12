/**
 * API functions for global token management.
 *
 * These functions interact with the organization-level token endpoints.
 * Token balance is now global per organization, not per-module.
 */

import { apiClient } from './client'
import type {
  ModulesResponse,
  TokenBalanceResponse,
  AddTokensRequest,
  TokenHistoryFilters,
  TokenHistoryResponse,
  ModuleName,
  ModuleToggleResponse,
} from '@/types/tokens'

// ============================================================================
// Global Token Endpoints
// ============================================================================

/**
 * Get the current token balance for an organization.
 *
 * @param organizationId - Keycloak organization UUID
 * @returns Token balance response with organization_id and balance
 */
export async function getOrganizationBalance(
  organizationId: string,
): Promise<TokenBalanceResponse> {
  return apiClient.get<TokenBalanceResponse>(`/organizations/${organizationId}/tokens`)
}

/**
 * Add tokens to an organization's balance.
 * Requires admin.organizations role.
 *
 * @param organizationId - Keycloak organization UUID
 * @param amount - Number of tokens to add (must be positive)
 * @returns Updated token balance
 */
export async function addOrganizationTokens(
  organizationId: string,
  amount: number,
): Promise<TokenBalanceResponse> {
  const data: AddTokensRequest = { amount }
  return apiClient.post<TokenBalanceResponse>(`/organizations/${organizationId}/tokens`, data)
}

/**
 * Get token transaction history for an organization.
 * Supports filtering by transaction type, reference type, and date range.
 *
 * @param organizationId - Keycloak organization UUID
 * @param filters - Optional filters for the history query
 * @returns Paginated list of token transactions
 */
export async function getTokenHistory(
  organizationId: string,
  filters: TokenHistoryFilters = {},
): Promise<TokenHistoryResponse> {
  const params = new URLSearchParams()

  if (filters.transaction_type) {
    params.append('transaction_type', filters.transaction_type)
  }
  if (filters.reference_type) {
    params.append('reference_type', filters.reference_type)
  }
  if (filters.date_from) {
    params.append('date_from', filters.date_from)
  }
  if (filters.date_to) {
    params.append('date_to', filters.date_to)
  }
  if (filters.page !== undefined) {
    params.append('page', filters.page.toString())
  }
  if (filters.size !== undefined) {
    params.append('size', filters.size.toString())
  }

  const queryString = params.toString()
  const endpoint = queryString
    ? `/organizations/${organizationId}/tokens/history?${queryString}`
    : `/organizations/${organizationId}/tokens/history`

  return apiClient.get<TokenHistoryResponse>(endpoint)
}

// ============================================================================
// Module Management Endpoints (tokens removed, enablement only)
// ============================================================================

/**
 * Get all modules for an organization.
 * Organization members can view their own modules.
 *
 * @param organizationId - Keycloak organization UUID
 * @returns List of module configurations (without token counts)
 */
export async function getOrganizationModules(organizationId: string): Promise<ModulesResponse> {
  return apiClient.get<ModulesResponse>(`/organizations/${organizationId}/modules`)
}

/**
 * Toggle a module's enabled status.
 *
 * @param organizationId - Keycloak organization UUID
 * @param module - Module name to toggle
 * @param enabled - Whether the module should be enabled
 * @returns Updated module status
 */
export async function toggleModule(
  organizationId: string,
  module: ModuleName,
  enabled: boolean,
): Promise<ModuleToggleResponse> {
  return apiClient.put<ModuleToggleResponse>(
    `/organizations/${organizationId}/modules/${module}/toggle`,
    { enabled },
  )
}
