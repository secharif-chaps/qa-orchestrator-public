/**
 * TypeScript types for usage dashboard statistics.
 *
 * These types match the backend Pydantic schemas in /back/app/schemas/admin_usage.py
 * and are used for the admin usage dashboard at /admin/usage.
 */

/**
 * A single data point in a time series chart.
 *
 * Used for the "Companies Over Time" line chart where each point
 * represents a time period (day/week/month) with a count.
 */
export interface TimeSeriesDataPoint {
  /** ISO format date string for the time period (e.g., "2025-01-01T00:00:00") */
  period: string
  /** Count of items (companies) in this period */
  count: number
}

/**
 * Company count breakdown for a single organization.
 *
 * Used in the organization breakdown table and stacked bar chart
 * to show how companies are distributed across organizations.
 */
export interface OrganizationBreakdown {
  /** Keycloak organization UUID (or "other" for grouped remainder) */
  organization_id: string
  /** Human-readable organization name from Keycloak */
  organization_name: string
  /** Number of companies created by this organization */
  companies_count: number
  /** Percentage of total companies (0-100, one decimal precision) */
  percentage: number
}

/**
 * Complete usage statistics response for the admin dashboard.
 *
 * This interface matches the backend UsageStatsResponse schema
 * and contains all data needed for the usage dashboard:
 * - KPI card values (companies_count, task_success_rate, active_users_count)
 * - Time series data for the line chart (companies_over_time)
 * - Organization breakdown for table and stacked chart (companies_by_organization)
 */
export interface UsageStats {
  /** Total number of companies created in the selected period */
  companies_count: number
  /** Percentage of successful tasks (0-100), or null if no completed tasks */
  task_success_rate: number | null
  /** Number of unique users who created companies in the period */
  active_users_count: number
  /** Time series data for the "Companies Over Time" line chart */
  companies_over_time: TimeSeriesDataPoint[]
  /** Organization breakdown for table and stacked bar chart */
  companies_by_organization: OrganizationBreakdown[]
}
