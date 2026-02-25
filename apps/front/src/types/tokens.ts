/**
 * Token system types for global organization token management.
 *
 * The token system tracks a single global balance per organization,
 * with complete transaction history for audit purposes.
 */

// Transaction type enum - matches backend TransactionType
export type TransactionType = 'add' | 'consume' | 'adjustment'

// Reference type enum - matches backend ReferenceType
export type ReferenceType = 'company' | 'csv_import' | 'manual' | 'system'

// Module names - core product modules an organization can subscribe to
// Note: 'translation' is now a feature flag, not a module (see feature-flags.ts)
export type ModuleName = 'screen' | 'target' | 'explore'

/**
 * Module configuration without token count.
 * Tokens are now managed at the organization level, not per-module.
 */
export interface ModuleConfig {
  name: ModuleName
  enabled: boolean
  created_at: string
  updated_at: string
}

export interface ModulesResponse {
  modules: ModuleConfig[]
}

/**
 * Response for organization token balance queries.
 */
export interface TokenBalanceResponse {
  organization_id: string
  balance: number
}

/**
 * Request to add tokens to an organization.
 */
export interface AddTokensRequest {
  amount: number
}

/**
 * A single token transaction record for audit history.
 */
export interface TokenTransaction {
  id: number
  organization_id: string
  amount: number
  balance_after: number
  transaction_type: TransactionType
  reference_type: ReferenceType
  reference_id: string | null
  created_at: string
  created_by: string
}

/**
 * Filters for querying token transaction history.
 */
export interface TokenHistoryFilters {
  transaction_type?: TransactionType
  reference_type?: ReferenceType
  date_from?: string
  date_to?: string
  page?: number
  size?: number
}

/**
 * Paginated response for token history queries.
 */
export interface TokenHistoryResponse {
  items: TokenTransaction[]
  total: number
  page: number
  size: number
  pages: number
}

/**
 * Error response for insufficient tokens.
 */
export interface InsufficientTokensError {
  error: 'insufficient_tokens'
  message: string
  current_balance: number
  required_tokens: number
}

/**
 * Module toggle request - for enabling/disabling modules.
 */
export interface ModuleToggleRequest {
  enabled: boolean
}

/**
 * Response after toggling a module.
 */
export interface ModuleToggleResponse {
  module: ModuleName
  enabled: boolean
}
