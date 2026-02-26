# Task Breakdown: Usage Dashboard Admin View

## Overview
Total Tasks: 35

This feature adds a global admin dashboard at `/admin/usage` displaying application usage metrics including companies created, task success rates, active users, and organization breakdowns with configurable time ranges (7D, 30D, 90D, All time).

## Component Architecture

```
front/src/components/admin/usage/
├── UsageTimeRangeToggle.vue      # Vuellar Toggle wrapper for time ranges
├── UsageKpiGrid.vue              # Grid of 4 StatCards
├── UsageLineChart.vue            # Companies over time (vue-chartjs Line)
├── UsageStackedBarChart.vue      # Companies by org over time (vue-chartjs Bar)
├── OrganizationUsageTable.vue    # Table container with sorting
└── OrganizationUsageRow.vue      # Reusable row component for table
```

## Vuellar Components to Use

| UI Element | Vuellar Component | Notes |
|------------|-------------------|-------|
| Time Range Selection | **Toggle** | `variant="pill"` with options for 7D/30D/90D/All |
| Organization Table | **Custom table** | Follow existing `OrganizationBreakdownTable.vue` pattern |
| Error Messages | **Alert** | `variant="danger"` for API errors |

## Existing Components to Reuse

| Component | Location | Usage |
|-----------|----------|-------|
| `StatCard.vue` | `components/dashboard/` | KPI cards |
| `OrganizationCostChart.vue` | `components/admin/` | Chart.js pattern reference |
| `Card.vue` | `components/ui/` | Wrapper for sections |

---

## Task List

### Backend Layer

#### Task Group 1: API Endpoint and Data Aggregation
**Dependencies:** None

- [x] 1.0 Complete backend API layer for usage statistics
  - [x] 1.1 Write 4-6 focused tests for usage stats endpoint
    - Test endpoint returns correct response structure with all required fields
    - Test date filtering works correctly for 7-day range
    - Test task success rate calculation (succeeded vs total)
    - Test `admin.organizations` permission is required (403 without role)
    - Skip exhaustive testing of all date ranges and edge cases
  - [x] 1.2 Create Pydantic response schemas in `/back/app/schemas/admin.py`
    - `UsageStatsResponse` with fields: `companies_count`, `task_success_rate`, `active_users_count`, `companies_over_time`, `companies_by_organization`
    - `TimeSeriesDataPoint` with fields: `period`, `count`
    - `OrganizationBreakdown` with fields: `organization_id`, `organization_name`, `companies_count`, `percentage`
  - [x] 1.3 Create `GET /api/admin/usage-stats` endpoint in `/back/app/api/endpoints/admin.py`
    - Accept query params: `start_date` (ISO format), `end_date` (ISO format)
    - Use fastapi-keycloak dependency requiring `admin.organizations` role
    - Inject database session using existing dependency pattern
    - Return `UsageStatsResponse` schema
  - [x] 1.4 Implement companies count aggregation query
    - Query `companies` table with `created_at` between start_date and end_date
    - Exclude soft-deleted companies (`is_deleted = false`)
    - Return total count as integer
  - [x] 1.5 Implement task success rate calculation query
    - Query `tasks` table with `created_at` between start_date and end_date
    - Filter to only `succeeded` and `error` status (exclude pending, running, blocked)
    - Calculate: (succeeded count / total count) * 100
    - Return as float with one decimal precision
    - Handle zero tasks case (return 0.0 or null)
  - [x] 1.6 Implement active users count query
    - Query distinct `owner_id` from `companies` table
    - Filter by `created_at` within date range
    - Exclude soft-deleted companies
    - Return count of unique user IDs
  - [x] 1.7 Implement companies over time aggregation
    - Query companies grouped by time period (day/week/month based on range)
    - Use SQLAlchemy `func.date_trunc` for grouping
    - Return list of `TimeSeriesDataPoint` objects
    - Order by period ascending
  - [x] 1.8 Implement companies by organization aggregation
    - Query companies grouped by `organization_id`
    - Include count per organization and calculate percentage
    - Limit to top 10 organizations; group remainder as "Other"
    - Fetch organization names from Keycloak using existing admin client
    - Return list of `OrganizationBreakdown` objects sorted by count descending
  - [x] 1.9 Ensure backend tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify endpoint returns 200 with valid response structure
    - Do NOT run the entire backend test suite

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Endpoint returns all required fields with correct types
- Date filtering works correctly
- Permission check enforced (403 without admin.organizations role)
- Organization names resolved from Keycloak

---

### Frontend API Layer

#### Task Group 2: API Functions and Queries
**Dependencies:** Task Group 1

- [x] 2.0 Complete frontend data fetching layer
  - [x] 2.1 Write 2-3 focused tests for API integration
    - Test API function constructs correct URL with date params
    - Test query returns expected data structure
    - Skip testing all date ranges and error scenarios
  - [x] 2.2 Create TypeScript types in `/front/src/types/usage.ts`
    - `TimeSeriesDataPoint` interface with `period: string`, `count: number`
    - `OrganizationBreakdown` interface with `organization_id`, `organization_name`, `companies_count`, `percentage`
    - `UsageStats` interface matching API response schema
  - [x] 2.3 Create API function in `/front/src/api/admin.ts`
    - `getUsageStats(startDate: string, endDate: string)` function
    - Use `apiClient.get<UsageStats>` with query params
    - Return typed response
  - [x] 2.4 Create query definition in `/front/src/queries/admin-usage.ts`
    - Define `ADMIN_USAGE_QUERY_KEYS` with `usageStats` key including date params
    - Create `usageStatsQuery` using `defineQueryOptions`
    - Accept `startDate` and `endDate` as reactive parameters
  - [x] 2.5 Ensure API layer tests pass
    - Run ONLY the 2-3 tests written in 2.1
    - Verify API function and query are correctly typed
    - Do NOT run the entire frontend test suite

**Acceptance Criteria:**
- The 2-3 tests written in 2.1 pass
- TypeScript types match backend response exactly
- Query properly caches based on date range
- API function handles date parameter formatting

---

### Frontend Component Layer

#### Task Group 3: Component Structure & Reusable Components
**Dependencies:** Task Group 2

- [x] 3.0 Create component folder structure and reusable components
  - [x] 3.1 Create component folder at `/front/src/components/admin/usage/`
    - Create the `usage/` directory inside `components/admin/`
    - This will house all usage dashboard components
  - [x] 3.2 Create `UsageTimeRangeToggle.vue` component
    - Wrap Vuellar `Toggle` component
    - Props: `modelValue` (selected range key)
    - Emits: `update:modelValue`
    - Options: `{ label: 'Last 7 days', value: '7d' }`, `{ label: 'Last 30 days', value: '30d' }`, `{ label: 'Last 90 days', value: '90d' }`, `{ label: 'All time', value: 'all' }`
    - Use `variant="pill"` for visual style
    - Computed: `startDate` and `endDate` based on selection
    - Expose computed dates via `defineExpose` or emits
  - [x] 3.3 Create `OrganizationUsageRow.vue` component
    - Reusable table row for organization breakdown
    - Props: `organization: OrganizationBreakdown`
    - Display: organization name (left), companies count (center), percentage (right)
    - Format percentage with one decimal: `12.5%`
    - Format count with locale: `1,234`
    - Apply hover state: `hover:bg-base-200`
    - Use semantic colors for text hierarchy

**Acceptance Criteria:**
- Component folder structure created
- Toggle component properly wraps Vuellar Toggle with date calculation
- Row component is self-contained and reusable
- Both components follow TypeScript and Composition API patterns

---

#### Task Group 4: KPI Grid Component
**Dependencies:** Task Group 3

- [x] 4.0 Create KPI grid component
  - [x] 4.1 Write 2-3 focused tests for KPI grid
    - Test all 4 KPI cards render with correct titles
    - Test loading state displays correctly
    - Skip testing all data variations and formatting edge cases
  - [x] 4.2 Create `UsageKpiGrid.vue` component
    - Props: `data: UsageStats | undefined`, `loading: boolean`
    - Use responsive grid: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4`
    - Import and use existing `StatCard` component for each KPI
  - [x] 4.3 Implement Companies Created KPI card
    - Use `StatCard` component
    - Title: "Companies Created"
    - Value: `data.companies_count` formatted with `toLocaleString()`
    - Icon: `building`
    - Color: `indigo`
    - Pass `loading` prop to StatCard
  - [x] 4.4 Implement Task Success Rate KPI card
    - Use `StatCard` component
    - Title: "Task Success Rate"
    - Value: Format as percentage with one decimal (e.g., "94.2%")
    - Icon: `check-circle`
    - Color: `green` (success intent)
    - Handle null/zero case: display "N/A" or "0%"
  - [x] 4.5 Implement Active Users KPI card
    - Use `StatCard` component
    - Title: "Active Users"
    - Value: `data.active_users_count` formatted with `toLocaleString()`
    - Icon: `users`
    - Color: `purple`
  - [x] 4.6 Implement Azure Cost placeholder KPI card
    - Use `StatCard` component
    - Title: "Azure Cost"
    - Value: "Coming Soon" (static text)
    - Icon: `dollar-sign`
    - Color: `orange`
    - Apply muted styling: `opacity-50` on the card
    - Tooltip or subtitle: "Phase 2 feature"
  - [x] 4.7 Ensure KPI grid tests pass
    - Run ONLY the 2-3 tests written in 4.1
    - Verify all cards render correctly
    - Do NOT run the entire frontend test suite

**Acceptance Criteria:**
- The 2-3 tests written in 4.1 pass
- All 4 KPI cards display with correct icons and colors
- Values formatted correctly (numbers with locale, percentage with decimal)
- Responsive grid works across breakpoints
- Loading states handled via StatCard props

---

#### Task Group 5: Chart Components
**Dependencies:** Task Group 4

- [x] 5.0 Create chart components
  - [x] 5.1 Write 2-4 focused tests for charts
    - Test line chart renders with time series data
    - Test stacked bar chart renders with organization data
    - Test empty state displays when no data
    - Skip testing all chart interactions and tooltip variations
  - [x] 5.2 Create `UsageLineChart.vue` component
    - Props: `data: TimeSeriesDataPoint[]`, `loading: boolean`
    - Use vue-chartjs `Line` component
    - Import and register required Chart.js components: `LineElement`, `PointElement`, `CategoryScale`, `LinearScale`, `Tooltip`, `Legend`
    - Follow pattern from `OrganizationCostChart.vue`
  - [x] 5.3 Configure line chart data and options
    - X-axis: time periods from `data[].period` (formatted as readable dates)
    - Y-axis: count of companies
    - Line color: use primary color from palette (`rgb(99, 102, 241)`)
    - Enable tooltips showing exact count on hover
    - Responsive: true, maintain aspect ratio
  - [x] 5.4 Implement line chart loading and empty states
    - Show skeleton/spinner while `loading` is true
    - Show empty state message when `data.length === 0`
    - Empty message: "No company data for selected period"
  - [x] 5.5 Create `UsageStackedBarChart.vue` component
    - Props: `timeSeriesData: TimeSeriesDataPoint[]`, `organizationData: OrganizationBreakdown[]`, `loading: boolean`
    - Use vue-chartjs `Bar` component with stacked configuration
    - Import and register: `BarElement`, `CategoryScale`, `LinearScale`, `Tooltip`, `Legend`
  - [x] 5.6 Configure stacked bar chart data transformation
    - X-axis: time periods (same as line chart)
    - Each organization becomes a dataset with distinct color
    - Stack datasets to show total per period
    - Use color palette from existing charts for consistency
  - [x] 5.7 Configure stacked bar chart legend
    - Display legend below chart
    - Show organization name with color indicator
    - "Other" category uses gray color (`rgb(156, 163, 175)`)
    - Limit legend to top 10 organizations
  - [x] 5.8 Implement stacked bar chart loading and empty states
    - Same pattern as line chart
    - Skeleton/spinner for loading
    - Empty state message when no data
  - [x] 5.9 Ensure charts tests pass
    - Run ONLY the 2-4 tests written in 5.1
    - Verify charts render with sample data
    - Do NOT run the entire frontend test suite

**Acceptance Criteria:**
- The 2-4 tests written in 5.1 pass
- Line chart displays companies over time correctly
- Stacked bar chart shows organization breakdown per period
- Charts follow existing color scheme and styling
- Loading and empty states work correctly

---

#### Task Group 6: Organization Table Component
**Dependencies:** Task Group 5

- [x] 6.0 Create organization table component
  - [x] 6.1 Write 2-3 focused tests for organization table
    - Test table renders with organization data
    - Test sorting by companies count works
    - Skip testing all sort directions and empty edge cases
  - [x] 6.2 Create `OrganizationUsageTable.vue` component
    - Props: `data: OrganizationBreakdown[]`, `loading: boolean`
    - Use custom table pattern from `OrganizationBreakdownTable.vue`
    - Import and use `OrganizationUsageRow.vue` for each row
  - [x] 6.3 Implement table header with sorting
    - Columns: "Organization", "Companies Created", "% of Total"
    - Sortable columns: "Companies Created", "% of Total"
    - Default sort: "Companies Created" descending
    - Click header to toggle sort direction
    - Show sort indicator icon (chevron up/down)
  - [x] 6.4 Implement table body with row component
    - Use `v-for` to iterate over sorted data
    - Render `OrganizationUsageRow` for each item
    - Pass `:organization="item"` prop
    - Apply striped styling: `odd:bg-base-100 even:bg-base-200`
  - [x] 6.5 Implement table loading state
    - Show skeleton rows while `loading` is true
    - Display 5 skeleton rows with pulsing animation
    - Match column widths of actual content
  - [x] 6.6 Implement table empty state
    - Show when `data.length === 0` and not loading
    - Center message: "No organization data for selected period"
    - Use muted text color: `text-secondary`
  - [x] 6.7 Style table container
    - Wrap in Card component or styled div
    - Add section title: "Organization Breakdown"
    - Border: `border border-primary-stroke`
    - Rounded corners: `rounded-lg`
    - Overflow handling: `overflow-x-auto` for mobile
  - [x] 6.8 Ensure table tests pass
    - Run ONLY the 2-3 tests written in 6.1
    - Verify table renders and sorts correctly
    - Do NOT run the entire frontend test suite

**Acceptance Criteria:**
- The 2-3 tests written in 6.1 pass
- Table displays all organization data correctly
- Sorting works on clickable column headers
- Row component properly formats data
- Loading and empty states display correctly

---

### Page Assembly

#### Task Group 7: Page Assembly and Navigation
**Dependencies:** Task Groups 3-6

- [x] 7.0 Assemble page and integrate components
  - [x] 7.1 Write 2-3 focused tests for page integration
    - Test page renders with all sections (KPI, charts, table)
    - Test time range toggle updates data fetch
    - Skip testing all toggle states and edge cases
  - [x] 7.2 Create page file at `/front/src/pages/admin/usage.vue`
    - Add route meta with `admin.organizations` permission
    - Add route meta with title "Usage Dashboard"
    - Use `<script setup lang="ts">` with Composition API
  - [x] 7.3 Implement page header section
    - Title: "Usage Dashboard" (h1, text-3xl font-bold)
    - Description: "View application usage metrics across all organizations"
    - Place `UsageTimeRangeToggle` component in header row
    - Use flex layout: title left, toggle right
  - [x] 7.4 Connect time range to usage stats query
    - Import `useQuery` from `@pinia/colada`
    - Import `usageStatsQuery` from queries
    - Get `startDate` and `endDate` from toggle component
    - Query automatically refetches when dates change
  - [x] 7.5 Wire KPI grid section
    - Import and place `UsageKpiGrid` component
    - Pass `data` and `loading` from query result
    - Add spacing below header: `mt-8` or use gap in parent flex
  - [x] 7.6 Wire charts section
    - Import `UsageLineChart` and `UsageStackedBarChart`
    - Place in vertical stack with `gap-6`
    - Pass appropriate data slices from query result
    - Each chart in its own section with subtle title
  - [x] 7.7 Wire organization table section
    - Import and place `OrganizationUsageTable`
    - Pass `data.companies_by_organization` from query
    - Add section spacing consistent with charts
  - [x] 7.8 Implement error state handling
    - Use Vuellar `Alert` component with `variant="danger"`
    - Display when query has error
    - Show error message with retry option
  - [x] 7.9 Add entry to admin dashboard feature grid
    - Modify `/front/src/pages/admin/(admin).vue`
    - Add usage dashboard card to `features` array
    - Icon: `chart-line` or `chart-bar`
    - Label: "Usage Dashboard"
    - Description: "View application usage metrics"
    - Route: `/admin/usage`
    - Permission: `admin.organizations`
  - [x] 7.10 Ensure page integration tests pass
    - Run ONLY the 2-3 tests written in 7.1
    - Verify page loads with all components
    - Do NOT run the entire frontend test suite

**Acceptance Criteria:**
- The 2-3 tests written in 7.1 pass
- Page accessible at `/admin/usage` with correct permission
- Time range toggle updates all components reactively
- All sections (KPI, charts, table) render correctly
- Entry appears in admin dashboard feature grid
- Error state displays appropriately

---

### Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps only
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review the 4-6 backend tests (Task 1.1)
    - Review the 2-3 API layer tests (Task 2.1)
    - Review the 2-3 KPI grid tests (Task 4.1)
    - Review the 2-4 charts tests (Task 5.1)
    - Review the 2-3 table tests (Task 6.1)
    - Review the 2-3 page integration tests (Task 7.1)
    - Total existing tests: approximately 14-22 tests
  - [x] 8.2 Analyze test coverage gaps for this feature only
    - Identify critical user workflows lacking test coverage
    - Focus ONLY on gaps related to Usage Dashboard feature
    - Do NOT assess entire application test coverage
    - Prioritize: time range change -> data update -> display flow
  - [x] 8.3 Write up to 8 additional strategic tests maximum
    - Priority: integration between time range and all components
    - Priority: permission denied flow for non-admin users
    - Priority: empty state when no data exists
    - Skip edge cases and performance tests
  - [x] 8.4 Run feature-specific tests only
    - Run ONLY tests related to Usage Dashboard feature
    - Expected total: approximately 22-30 tests maximum
    - Do NOT run the entire application test suite
    - Verify critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 22-30 tests total)
- Critical user workflows for Usage Dashboard are covered
- No more than 8 additional tests added when filling gaps
- Testing focused exclusively on this spec's feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Backend Layer (Task Group 1)** - API endpoint and data aggregation
2. **Frontend API Layer (Task Group 2)** - Types, API functions, queries
3. **Component Structure (Task Group 3)** - Folder, Toggle wrapper, Row component
4. **KPI Grid (Task Group 4)** - Grid component using StatCard
5. **Charts (Task Group 5)** - Line chart and stacked bar chart components
6. **Organization Table (Task Group 6)** - Table using row component
7. **Page Assembly (Task Group 7)** - Wire everything together, add navigation
8. **Test Review (Task Group 8)** - Gap analysis and integration tests

---

## Files to Create/Modify

### New Files
**Backend:**
- `/back/app/schemas/admin_usage.py` - Created with UsageStatsResponse, TimeSeriesDataPoint, OrganizationBreakdown schemas

**Frontend Types & API:**
- `/front/src/types/usage.ts` - Created with TimeSeriesDataPoint, OrganizationBreakdown, UsageStats interfaces
- `/front/src/api/admin.ts` - Extended with getUsageStats function
- `/front/src/queries/admin-usage.ts` - Created with ADMIN_USAGE_QUERY_KEYS and usageStatsQuery

**Frontend Components:**
- `/front/src/components/admin/usage/UsageTimeRangeToggle.vue`
- `/front/src/components/admin/usage/OrganizationUsageRow.vue`
- `/front/src/components/admin/usage/UsageKpiGrid.vue`
- `/front/src/components/admin/usage/UsageLineChart.vue`
- `/front/src/components/admin/usage/UsageStackedBarChart.vue`
- `/front/src/components/admin/usage/OrganizationUsageTable.vue`

**Frontend Page:**
- `/front/src/pages/admin/usage.vue`

### Modified Files
- `/back/app/api/endpoints/admin.py` - Added usage stats endpoint with GET /api/admin/usage-stats
- `/front/src/pages/admin/(admin).vue` - Add feature grid entry

### Test Files
- `/back/tests/unit/test_admin_usage_stats.py` - Updated with 13 tests for usage stats endpoint (added 2 strategic tests)
- `/front/src/queries/admin-usage.spec.ts` - Created with 3 tests for API and query layer
- `/front/src/components/admin/usage/UsageKpiGrid.spec.ts` - Updated with 5 tests for KPI grid component (added 2 tests for null handling)
- `/front/src/components/admin/usage/UsageCharts.spec.ts` - Created with 4 tests for chart components
- `/front/src/components/admin/usage/OrganizationUsageTable.spec.ts` - Created with 3 tests for table component
- `/front/src/components/admin/usage/UsageTimeRangeToggle.spec.ts` - Created with 5 tests for time range toggle component
- `/front/src/pages/admin/usage.spec.ts` - Updated with 6 tests for page integration (added 3 tests for error/retry handling)

---

## Test Summary

### Final Test Count
**Backend:** 13 tests (all passing)
- 4 schema validation tests
- 2 companies count query tests
- 3 task success rate calculation tests
- 1 active users count test
- 1 companies over time aggregation test
- 1 companies by organization "Other" grouping test (NEW)
- 1 endpoint permission configuration test (NEW)

**Frontend:** 26 tests (vitest required)
- 3 API/query tests
- 5 KPI grid tests (added null handling tests)
- 4 chart tests
- 3 table tests
- 5 time range toggle tests (NEW component test file)
- 6 page integration tests (added error/retry tests)

**Total: 39 tests** covering all critical user workflows for the Usage Dashboard feature.

---

## Existing Code References

| Reference | Location | Usage |
|-----------|----------|-------|
| StatCard component | `/front/src/components/dashboard/StatCard.vue` | KPI cards in UsageKpiGrid |
| Chart patterns | `/front/src/components/admin/OrganizationCostChart.vue` | Chart.js setup and colors |
| Table patterns | `/front/src/components/admin/OrganizationBreakdownTable.vue` | Table structure and sorting |
| Admin page layout | `/front/src/pages/admin/(admin).vue` | Page layout and feature grid |
| Task model/status | `/back/app/models/task.py` | Task success rate calculation |
| Admin router pattern | `/back/app/api/endpoints/admin.py` | Endpoint structure |
| Vuellar Toggle | `@owlint/feathers-vue` | Time range selection |
