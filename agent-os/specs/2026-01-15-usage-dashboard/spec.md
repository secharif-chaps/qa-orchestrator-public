# Specification: Usage Dashboard Admin View

## Goal
Provide global administrators with visibility into application usage patterns across all organizations, including company creation trends, task success rates, and active user counts over configurable time periods.

## User Stories
- As a global admin, I want to see how many companies have been created across all organizations so that I can track platform adoption and growth.
- As a global admin, I want to understand task success rates so that I can identify potential workflow issues and maintain service quality.

## Specific Requirements

**Time Range Selection**
- Display toggle buttons for predefined time ranges: 7D, 30D, 90D, All time
- Default selection is 7D when the page loads
- Selected range persists during the session but not across page reloads
- All KPI cards and charts update reactively when the time range changes
- Use Vuellar Toggle component with segment style for range selection

**KPI Card: Companies Created**
- Display total count of companies created within the selected time period
- Query counts companies from `companies` table filtered by `created_at` within range
- Exclude soft-deleted companies (`is_deleted = false`)
- Use existing StatCard component pattern with `building` icon and `indigo` color

**KPI Card: Task Success Rate**
- Calculate as: (succeeded tasks / total tasks) * 100 for selected period
- Query from `tasks` table filtering by `created_at` within range
- Only count tasks with status `succeeded` or `error` (exclude `pending`, `running`, `blocked`)
- Display as percentage with one decimal place (e.g., "94.2%")
- Use StatCard component with `check-circle` icon and `green` color

**KPI Card: Active Users**
- Count unique `owner_id` values from companies created in the selected period
- Represents users who performed the primary action (creating companies)
- Use StatCard component with `users` icon and `purple` color

**KPI Card: Azure Cost (Placeholder)**
- Display as "Coming Soon" or disabled state
- Do not implement actual cost fetching in this phase
- Use StatCard component with `dollar-sign` icon and `orange` color with muted styling

**Line Chart: Companies Over Time**
- Show companies created over time as a line chart
- X-axis: time periods (days for 7D/30D, weeks for 90D, months for All time)
- Y-axis: count of companies created per period
- Use Chart.js Line component with existing color scheme from OrganizationCostChart
- Include loading and empty states

**Stacked Bar Chart: Companies by Organization**
- Show companies created over time grouped by organization
- Each bar is stacked with different colors per organization
- X-axis: time periods matching the line chart granularity
- Y-axis: total count per period
- Legend displays organization names with color indicators
- Limit to top 10 organizations by company count; group remainder as "Other"
- Use Chart.js Bar component with stacked configuration

**Organization Breakdown Table**
- Display sortable table with columns: Organization Name, Companies Created, Percentage
- Percentage calculated as: (org companies / total companies) * 100
- Sort by Companies Created (descending) by default
- Use Vuellar Table component with existing field/items pattern
- Include empty state for when no data exists

**Page Layout and Structure**
- Page route: `/admin/usage` requiring `admin.organizations` permission
- Add entry to admin dashboard feature grid linking to this page
- Use consistent admin page layout with header, description, and grid structure
- KPI cards in 4-column responsive grid (1 col mobile, 2 cols tablet, 4 cols desktop)
- Charts stacked vertically below KPIs with gap-6 spacing
- Organization table at bottom of page

**Backend API Endpoint**
- Create `GET /api/admin/usage-stats` endpoint with query params: `start_date`, `end_date`
- Return aggregated response with: `companies_count`, `task_success_rate`, `active_users_count`, `companies_over_time`, `companies_by_organization`
- Requires `admin.organizations` role via fastapi-keycloak dependency
- Use SQLAlchemy aggregation queries with GROUP BY for time series data
- Include organization names from Keycloak lookup for display

## Visual Design
No visual mockups provided. Follow existing admin page patterns from `/admin/(admin).vue` and chart styling from `OrganizationCostChart.vue`.

## Existing Code to Leverage

**`/front/src/components/dashboard/StatCard.vue`**
- Reusable KPI card component with icon, title, value, subtitle, and color props
- Already supports loading state and number formatting
- Extend for percentage display if needed, or format value as string

**`/front/src/components/admin/OrganizationCostChart.vue`**
- Chart.js doughnut chart with loading, error, and empty states
- Color palette and tooltip configuration pattern to follow
- Legend rendering pattern with color indicators

**`/front/src/pages/admin/(admin).vue`**
- Admin dashboard layout and feature grid pattern
- Permission-based feature visibility with `visibleFeatures` computed
- Route meta configuration with permissions array

**`/back/app/models/task.py`**
- TaskStatus enum with `SUCCEEDED` and `ERROR` for success rate calculation
- Task model with `created_at`, `status`, `organization_id` fields
- Company relationship for joining queries

**`/back/app/api/endpoints/admin.py`**
- Admin router pattern with `/admin` prefix and `admin` tag
- fastapi-keycloak role-based dependency pattern
- Database session dependency injection pattern

## Out of Scope
- Task breakdown by individual task type (all 9 tasks treated equally)
- Organization growth rate metrics or trends
- Per-organization filtering on the dashboard (table provides breakdown)
- Comparison with previous period (e.g., "+15% vs last week")
- User-level activity tracking beyond active user count
- Custom date range picker with calendar UI
- Azure cost integration via Azure Cost Management API
- Export functionality (CSV, PDF)
- Real-time updates via SSE or WebSocket
- Caching of aggregated statistics
