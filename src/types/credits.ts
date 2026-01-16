/**
 * Credit statistics types for organization credit management view.
 *
 * These types support the credit usage dashboard showing:
 * - Current balance and usage breakdown
 * - Top credit-consuming users
 * - Daily credit usage time series
 */

// Module types (same as tokens.ts for consistency)
export type ModuleName = 'screen' | 'target' | 'explore'

/**
 * Credit usage breakdown for a single module.
 */
export interface ModuleUsage {
  module: ModuleName
  label: string
  creditsConsumed: number
  percentage: number
}

/**
 * Remaining capacity forecast for a single module.
 */
export interface ModuleForecast {
  module: ModuleName
  label: string
  icon: string
  cost: number | null
  remainingCount: number | null
  enabled: boolean
  itemLabel: string
  itemLabelPlural: string
}

/**
 * Response for organization credit statistics.
 */
export interface CreditStats {
  balance: number
  usageByModule: ModuleUsage[]
  remainingCapacity: ModuleForecast[]
}

/**
 * A single user in the top credit users leaderboard.
 */
export interface TopCreditUser {
  rank: number
  userId: string
  username: string
  fullName: string
  email: string
  initials: string
  creditsConsumed: number
}

/**
 * Paginated response for top credit users.
 */
export interface TopCreditUsersResponse {
  items: TopCreditUser[]
  total: number
  page: number
  size: number
}

/**
 * Filters for top credit users query.
 */
export interface TopCreditUsersFilters {
  module?: string
  period?: string
  startDate?: string
  endDate?: string
  search?: string
  page?: number
  size?: number
}

/**
 * Credit usage for a single day.
 */
export interface DailyUsage {
  date: string
  creditsConsumed: number
}

/**
 * Response for daily credit usage time series.
 */
export interface DailyCreditUsageResponse {
  dailyUsage: DailyUsage[]
}

/**
 * Filters for daily credit usage query.
 */
export interface DailyCreditUsageFilters {
  module?: string
  period?: string
  startDate?: string
  endDate?: string
}

/**
 * Color configuration for chart modules.
 * Colors match the module badge colors in the appbar.
 */
export const MODULE_CHART_COLORS: Record<ModuleName, string> = {
  screen: '#6366f1',   // Indigo (matching module badge)
  target: '#f43f5e',   // Rose/Cherry (matching module badge)
  explore: '#8BAF9C',  // Sage green (matching module badge)
}
