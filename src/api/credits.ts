/**
 * API functions for organization credit statistics.
 *
 * These functions fetch credit usage data for the manager dashboard:
 * - Balance and usage breakdown by module
 * - Top credit-consuming users
 * - Daily credit usage time series
 */

import { apiClient } from './client'
import type {
  CreditStats,
  ModuleUsage,
  ModuleForecast,
  TopCreditUsersResponse,
  TopCreditUser,
  TopCreditUsersFilters,
  DailyCreditUsageResponse,
  DailyUsage,
  DailyCreditUsageFilters,
} from '@/types/credits'

// ============================================================================
// Response Transformers (snake_case to camelCase)
// ============================================================================

interface RawModuleUsage {
  module: string
  label: string
  credits_consumed: number
  percentage: number
}

interface RawModuleForecast {
  module: string
  label: string
  icon: string
  cost: number | null
  remaining_count: number | null
  enabled: boolean
  item_label: string
  item_label_plural: string
}

interface RawCreditStats {
  balance: number
  usage_by_module: RawModuleUsage[]
  remaining_capacity: RawModuleForecast[]
}

interface RawTopCreditUser {
  rank: number
  user_id: string
  username: string
  full_name: string
  email: string
  initials: string
  credits_consumed: number
}

interface RawTopCreditUsersResponse {
  items: RawTopCreditUser[]
  total: number
  page: number
  size: number
}

interface RawDailyUsage {
  date: string
  credits_consumed: number
}

interface RawDailyCreditUsageResponse {
  daily_usage: RawDailyUsage[]
}

const transformModuleUsage = (raw: RawModuleUsage): ModuleUsage => ({
  module: raw.module as ModuleUsage['module'],
  label: raw.label,
  creditsConsumed: raw.credits_consumed,
  percentage: raw.percentage,
})

const transformModuleForecast = (raw: RawModuleForecast): ModuleForecast => ({
  module: raw.module as ModuleForecast['module'],
  label: raw.label,
  icon: raw.icon,
  cost: raw.cost,
  remainingCount: raw.remaining_count,
  enabled: raw.enabled,
  itemLabel: raw.item_label,
  itemLabelPlural: raw.item_label_plural,
})

const transformCreditStats = (raw: RawCreditStats): CreditStats => ({
  balance: raw.balance,
  usageByModule: raw.usage_by_module.map(transformModuleUsage),
  remainingCapacity: raw.remaining_capacity.map(transformModuleForecast),
})

const transformTopCreditUser = (raw: RawTopCreditUser): TopCreditUser => ({
  rank: raw.rank,
  userId: raw.user_id,
  username: raw.username,
  fullName: raw.full_name,
  email: raw.email,
  initials: raw.initials,
  creditsConsumed: raw.credits_consumed,
})

const transformTopCreditUsersResponse = (raw: RawTopCreditUsersResponse): TopCreditUsersResponse => ({
  items: raw.items.map(transformTopCreditUser),
  total: raw.total,
  page: raw.page,
  size: raw.size,
})

const transformDailyUsage = (raw: RawDailyUsage): DailyUsage => ({
  date: raw.date,
  creditsConsumed: raw.credits_consumed,
})

const transformDailyCreditUsageResponse = (raw: RawDailyCreditUsageResponse): DailyCreditUsageResponse => ({
  dailyUsage: raw.daily_usage.map(transformDailyUsage),
})

// ============================================================================
// API Functions
// ============================================================================

/**
 * Get credit statistics for an organization.
 * Returns balance, usage breakdown by module, and remaining capacity.
 *
 * Requires organization.manage role for own organization or admin.organizations for any.
 *
 * @param organizationId - Keycloak organization UUID
 * @returns Credit statistics with balance, usage breakdown, and capacity forecast
 */
export const getCreditStats = async (organizationId: string): Promise<CreditStats> => {
  const raw = await apiClient.get<RawCreditStats>(`/organizations/${organizationId}/credits/stats`)
  return transformCreditStats(raw)
}

/**
 * Get top credit-consuming users in the organization.
 *
 * Requires organization.manage role for own organization or admin.organizations for any.
 *
 * @param organizationId - Keycloak organization UUID
 * @param filters - Query filters (module, period, dates, search, pagination)
 * @returns Paginated list of top credit users
 */
export const getTopCreditUsers = async (
  organizationId: string,
  filters: TopCreditUsersFilters = {}
): Promise<TopCreditUsersResponse> => {
  const params = new URLSearchParams()

  if (filters.module && filters.module !== 'all') {
    params.set('module', filters.module)
  }
  if (filters.period) {
    params.set('period', filters.period)
  }
  if (filters.startDate) {
    params.set('start_date', filters.startDate)
  }
  if (filters.endDate) {
    params.set('end_date', filters.endDate)
  }
  if (filters.search) {
    params.set('search', filters.search)
  }
  if (filters.page !== undefined) {
    params.set('page', filters.page.toString())
  }
  if (filters.size !== undefined) {
    params.set('size', filters.size.toString())
  }

  const queryString = params.toString()
  const endpoint = queryString
    ? `/organizations/${organizationId}/credits/top-users?${queryString}`
    : `/organizations/${organizationId}/credits/top-users`

  const raw = await apiClient.get<RawTopCreditUsersResponse>(endpoint)
  return transformTopCreditUsersResponse(raw)
}

/**
 * Get daily credit usage for an organization.
 *
 * Requires organization.manage role for own organization or admin.organizations for any.
 *
 * @param organizationId - Keycloak organization UUID
 * @param filters - Query filters (module, period, dates)
 * @returns Daily credit usage time series
 */
export const getDailyCreditUsage = async (
  organizationId: string,
  filters: DailyCreditUsageFilters = {}
): Promise<DailyCreditUsageResponse> => {
  const params = new URLSearchParams()

  if (filters.module && filters.module !== 'all') {
    params.set('module', filters.module)
  }
  if (filters.period) {
    params.set('period', filters.period)
  }
  if (filters.startDate) {
    params.set('start_date', filters.startDate)
  }
  if (filters.endDate) {
    params.set('end_date', filters.endDate)
  }

  const queryString = params.toString()
  const endpoint = queryString
    ? `/organizations/${organizationId}/credits/daily-usage?${queryString}`
    : `/organizations/${organizationId}/credits/daily-usage`

  const raw = await apiClient.get<RawDailyCreditUsageResponse>(endpoint)
  return transformDailyCreditUsageResponse(raw)
}
