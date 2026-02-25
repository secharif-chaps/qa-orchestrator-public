# Specification: Refresh Company Screen

## Goal

Enable company owners to refresh their company data by re-running all data collection tasks, consuming 35 tokens (same cost as initial company creation), to obtain updated information without creating duplicate company entries.

## User Stories

- As a company owner, I want to refresh my company's data so that I can get the latest information without creating a duplicate entry
- As a company owner, I want to see how many tokens a refresh will cost so that I can make an informed decision before proceeding

## Specific Requirements

**Refresh button in company header**
- Add a labeled button "Refresh" with i18n translation key next to export and delete buttons
- Use tertiary variant with an appropriate refresh icon (fa fa-refresh)
- Only render the button for the company owner (owner_id matches current user's Keycloak sub claim)
- Non-owners should not see the button at all (completely hidden)

**Button disabled states**
- Disable button when user has fewer than 35 tokens with tooltip "Insufficient tokens"
- Disable button when any task is not in SUCCEEDED status with tooltip "Wait for all tasks to complete"
- Disable button when a refresh is already in progress with loading spinner

**Confirmation modal**
- Use Modal component from @owlint/feathers-vue following CompanyArchiveModal pattern
- Display modal title with i18n: "Refresh Company Data"
- Include icon fa fa-refresh in modal header
- Show warning message that current data will be overwritten when new data arrives

**Token information in modal**
- Display current token count using similar styling to TokenCounter component
- Display cost of refresh: 35 tokens
- Display remaining tokens after refresh (current - 35)
- Use semantic colors: warning for low tokens, success for sufficient tokens

**Backend refresh endpoint**
- Create new POST /companies/{company_id}/refresh endpoint
- Verify company.create permission (same as company creation)
- Verify owner_id matches the current user's Keycloak sub claim
- Verify all tasks are in SUCCEEDED status before allowing refresh
- Consume 35 tokens from screen module
- Reset all tasks and restart data collection workflow

**Task reset workflow**
- Reset data_collection task to PENDING status (ready to run)
- Reset all dependent tasks (profile, digital, csr, press, timeline, products, team, jobs) to BLOCKED status
- Clear task error fields
- Preserve existing company data until new data arrives from workflows
- Queue the data_collection task to Celery for execution

**Token consumption and rollback**
- Consume 35 tokens immediately on refresh initiation
- Rollback tokens if task reset fails
- Follow same token handling pattern as create_company endpoint

## Visual Design

No visual assets were provided. Follow existing patterns:

**Button placement reference: `/Users/nicolasmercier/dev/mint-new/mint-front/src/pages/folders/[folderId]/companies/[companyId].vue`**
- Place refresh button between debug button (if visible) and delete button
- Use same button styling as other header action buttons (tertiary variant)

**Modal design reference: `/Users/nicolasmercier/dev/mint-new/mint-front/src/components/companies/CompanyArchiveModal.vue`**
- Follow same structure: title, description slot, content area, footer with action buttons
- Use consistent spacing and layout patterns

**Token display reference: `/Users/nicolasmercier/dev/mint-new/mint-front/src/components/tokens/TokenCounter.vue`**
- Use similar visual styling for token count display
- Apply semantic colors based on token availability

## Existing Code to Leverage

**CompanyArchiveModal.vue**
- Reuse Modal component pattern with v-model:display-modal binding
- Follow same structure for title, description, content, and footer slots
- Reuse loading state handling and emit patterns

**TokenCounter.vue**
- Reuse visual styling for displaying current token count
- Reuse computed classes for semantic colors (tokenIconClasses, tokenCountClasses)
- Reuse TOKENS_PER_COMPANY constant (35)

**Create company endpoint (company.py)**
- Reuse token consumption pattern with TokenManager.consume_tokens()
- Reuse token rollback pattern for error handling
- Reuse permission verification with verify_company_modify_permission

**CompanyService.create_company()**
- Reuse task_configs array for task type definitions
- Reuse task creation pattern with TaskStatus.PENDING for prerequisite and BLOCKED for dependents
- Reuse workflow execution queueing pattern with execute_dify_workflow.delay()

**Task restart endpoint (tasks.py)**
- Reference restart_task for task status reset pattern
- Reference organization access verification pattern

## Out of Scope

- Creating a new permission type (uses existing company.create)
- Clearing existing company data before new data arrives
- Allowing refresh while tasks are still running
- Allowing refresh when any task is in ERROR status (must manually restart failed tasks first)
- Showing refresh button to non-owners
- Partial refresh of specific task types only
- Customizing token cost per refresh
- Scheduling automatic refreshes
- Batch refresh for multiple companies
- Undo/revert functionality after refresh completes
