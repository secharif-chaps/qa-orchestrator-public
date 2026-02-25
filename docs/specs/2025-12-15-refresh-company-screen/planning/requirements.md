# Spec Requirements: Refresh Company Screen

## Initial Description

**Feature Name**: Refresh Company Screen Feature

**Description**:
- When the owner of a company screen accesses their company screen, they should find a refresh button next to the export and delete action buttons in the company header
- Clicking this button opens a modal explaining that refreshing will consume 35 tokens (same as creating a new company)
- The refresh will override current company data with new data
- This feature is for when users want fresh data without duplicating the company
- Display current token count like on the create new company page
- Same logic to disable refresh if not enough tokens
- Refreshing should restart all tasks (data collection tasks first, then others)
- Task statuses should be set accordingly

**Roadmap Phase**: Phase 1: Screen Production & First Client [CURRENT]
**Related Roadmap Item**: #15 - Company Data Refresh - Ability to re-run data collection tasks for updated information

## Requirements Discussion

### First Round Questions

**Q1:** I assume the refresh button should be placed in the company header between the debug button (if visible) and the delete button, matching the existing button styling (tertiary variant, icon-only or with label). Should it have a label like "Refresh" or be icon-only with a tooltip, similar to the debug button?
**Answer:** Should have a label (with i18n internationalization)

**Q2:** I assume the confirmation modal should follow the existing modal pattern used in CompanyArchiveModal.vue (using the @owlint/feathers-vue Modal component with title, description, content area showing token info, and action buttons). Should we display current token count prominently, cost of refresh (35 tokens), remaining tokens after refresh, and a warning that current data will be overwritten?
**Answer:** Yes - display all token info (current count, cost of 35, remaining after refresh, warning about data overwrite)

**Q3:** I assume if the user has insufficient tokens (less than 35), the refresh button should be disabled with a tooltip explaining why, similar to how the create company form disables the submit button. Is that correct?
**Answer:** Yes - disabled button with tooltip

**Q4:** The raw idea mentions "owner of a company screen" - I assume this means users with company.create or company.update permission within the organization. Should any organization member with company permissions be able to refresh, or only the original creator (owner_id field)?
**Answer:** Only the owner (owner_id) can refresh, not all organization members

**Q5:** I assume a new permission is NOT needed since this is essentially re-running the same workflows as company creation. Should we reuse company.create permission, or does this warrant a new company.refresh permission?
**Answer:** company.create permission

**Q6:** When refreshing, should existing data be cleared immediately on refresh start, or preserved until new data overwrites it?
**Answer:** Preserved until overwritten by new data

**Q7:** I assume we need a new backend endpoint like POST /companies/{company_id}/refresh. Is that correct?
**Answer:** Yes - new POST /companies/{company_id}/refresh endpoint

**Q8:** What should happen if a refresh is triggered while tasks are still running from a previous refresh or initial creation?
**Answer:** Disable refresh if all tasks are not succeeded (must wait for completion)

**Q9:** I assume the feature should be available immediately after company creation (even if initial tasks are still running). Is that correct?
**Answer:** Requires all tasks to succeed before refresh is available

### Existing Code to Reference

**Similar Features Identified:**
- Feature: CompanyArchiveModal - Path: `/Users/nicolasmercier/dev/mint-new/mint-front/src/components/companies/CompanyArchiveModal.vue`
- Feature: Token system on create company page - Path: `/Users/nicolasmercier/dev/mint-new/mint-front/src/pages/folders/[folderId]/create/company.vue`
- Feature: Company header with action buttons - Path: `/Users/nicolasmercier/dev/mint-new/mint-front/src/pages/folders/[folderId]/companies/[companyId].vue`
- Feature: Token counter component - Path: `/Users/nicolasmercier/dev/mint-new/mint-front/src/components/tokens/TokenCounter.vue`
- Feature: Insufficient tokens alert - Path: `/Users/nicolasmercier/dev/mint-new/mint-front/src/components/tokens/InsufficientTokensAlert.vue`
- Backend: Company creation with token consumption - Path: `/Users/nicolasmercier/dev/mint-new/mint-server/app/api/endpoints/company.py`
- Backend: Company service with task creation - Path: `/Users/nicolasmercier/dev/mint-new/mint-server/app/services/company.py`
- Backend: Task restart endpoint - Path: `/Users/nicolasmercier/dev/mint-new/mint-server/app/api/endpoints/tasks.py`

### Follow-up Questions

**Follow-up 1:** You mentioned only the owner (owner_id) can refresh. Should we display the refresh button to non-owners at all?
**Answer:** Hide completely for non-owners (they won't know the feature exists)

**Follow-up 2:** You mentioned refresh should only be available when all tasks have succeeded. What about tasks that ended in ERROR status?
**Answer:** Require all tasks to be SUCCEEDED (errors block refresh - user must manually restart failed tasks first)

**Follow-up 3:** When tasks are still in progress (RUNNING, PENDING, or BLOCKED), should the refresh button be hidden or visible but disabled?
**Answer:** Visible but disabled with tooltip "Wait for all tasks to complete"

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements
- Add a refresh button with i18n label in the company header (next to export and delete buttons)
- Button only visible to the company owner (owner_id matches current user)
- Button disabled with tooltip when:
  - Insufficient tokens (less than 35)
  - Tasks are not all in SUCCEEDED status
- Clicking refresh opens a confirmation modal displaying:
  - Current token count
  - Cost of refresh (35 tokens)
  - Remaining tokens after refresh
  - Warning that current data will be overwritten
- On confirmation, consume 35 tokens and restart all data collection tasks
- Existing company data preserved until new data overwrites it
- Task workflow: data_collection runs first (PENDING), dependent tasks start as BLOCKED

### Reusability Opportunities
- Reuse Modal component from @owlint/feathers-vue (same pattern as CompanyArchiveModal)
- Reuse TokenCounter component for displaying token information
- Reuse token consumption logic from company creation endpoint
- Reuse task creation/restart patterns from CompanyService.create_company()
- Reuse permission checking patterns (company.create permission)

### Scope Boundaries

**In Scope:**
- Refresh button in company header (owner-only, with i18n label)
- Confirmation modal with token information
- Token validation and consumption (35 tokens)
- New backend endpoint POST /companies/{company_id}/refresh
- Reset all tasks to initial states and trigger workflows
- Ownership validation (only owner_id can refresh)
- Task status validation (all tasks must be SUCCEEDED)
- Disabled state with tooltip when conditions not met

**Out of Scope:**
- New permission type (uses existing company.create)
- Clearing existing data before new data arrives
- Refresh while tasks are running
- Refresh when any task is in ERROR status
- Visibility of refresh button for non-owners

### Technical Considerations
- Frontend: Vue 3 with Composition API, @owlint/feathers-vue components
- Backend: FastAPI with new POST /companies/{company_id}/refresh endpoint
- Token consumption: 35 tokens per refresh (same as company creation)
- Task dependency: data_collection is prerequisite, others are dependent
- Permission: Requires company.create permission AND owner_id match
- State management: Check task statuses before enabling refresh
- i18n: All user-facing text must use vue-i18n translation keys
