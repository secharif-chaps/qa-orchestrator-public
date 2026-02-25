# Specification: Dify Workflow Parameter Refactoring

## Goal

Remove legacy dual-LLM (Mistral + Claude) parameters from the codebase as the system transitions to exclusively using GPT 5.1, eliminating unused token callbacks and LLM selection while renaming knowledge fields from "claude" to "gpt".

## User Stories

- As a developer, I want to remove unused parameters so that the codebase is cleaner and easier to maintain
- As an admin, I want a simplified workflow configuration without LLM selection since all workflows now use GPT 5.1

## Specific Requirements

**Remove Token Callback URL Parameter**
- Remove `token_callback_url` parameter from `DifyService.run_workflow()` method signature
- Remove `token_callback` parameter from `execute_dify_workflow` Celery task
- Remove `_prepare_task_callbacks()` token callback URL generation (lines 356, 369-371)
- Remove token callback from all `execute_dify_workflow.delay()` calls in `company.py`
- Keep success and error callback parameters (still used)

**Remove LLM Column from Database**
- Create Alembic migration to remove `llm` column from `workflow_configs` table
- Include both upgrade (drop column) and downgrade (add column with default "mistral")
- Migration should be reversible per project standards

**Remove LLM from WorkflowConfig Model**
- Remove `llm = Column(String(20), nullable=False, default="mistral")` from `WorkflowConfig` model
- Remove `llm` field from `WorkflowConfigResponse` Pydantic schema
- Remove `llm` field and validator from `WorkflowConfigUpdate` schema
- Remove LLM from `_get_workflow_config()` return tuple in `dify.py`

**Remove LLM from Dify Service**
- Remove `llm` parameter from `run_workflow()` method signature
- Remove LLM lookup from database config in `_get_workflow_config()`
- Remove `inputs["llm"] = llm` line that passes LLM to Dify workflows
- Remove LLM default fallback logic (`if not llm: llm = "mistral"`)

**Remove LLM from Celery Tasks and Company Service**
- Remove `llm` parameter from `execute_dify_workflow` task signature
- Remove `llm=workflow_config.llm` from all `execute_dify_workflow.delay()` calls
- Remove LLM passing from `create_company()` and `restart_task()` methods

**Remove LLM from Admin API Endpoint**
- Remove `llm` field from response in `update_workflow_config()` endpoint
- Keep endpoint for API key updates only

**Remove LLM Selector from Frontend WorkflowCard**
- Remove LLM dropdown select element from edit mode template
- Remove `llm` from `editData` reactive object
- Remove `llm` from update emit payload
- Remove LLM display in read-only view mode

**Rename Knowledge Database Column**
- Create Alembic migration to rename `raw_claude_knowledge` to `raw_gpt_knowledge` in `companies` table
- Keep `raw_mistral_knowledge` unchanged (backward compatibility for existing data)
- Include reversible downgrade function

**Update Knowledge Field References**
- Rename `raw_claude_knowledge` to `raw_gpt_knowledge` in `Company` model
- Update `CompanyResponse` schema field name
- Update `_build_company_response()` in company service
- Update `_update_company_data()` to use new field name
- Update `_get_knowledge_data()` in dify.py to use key "gpt" instead of "claude"
- Update Dify workflow inputs: `inputs["claude"]` becomes `inputs["gpt"]`

**Update Frontend RawKnowledgeDebug Component**
- Rename "Raw Claude Knowledge" label to "Raw GPT Knowledge"
- Update `company?.raw_claude_knowledge` to `company?.raw_gpt_knowledge`
- Update `hasAnyRawData` computed property

## Visual Design

No visual mockups required. Changes are parameter removals and field renames with minimal UI impact (only WorkflowCard LLM dropdown removal and RawKnowledgeDebug label rename).

## Existing Code to Leverage

**DifyService (`back/app/services/dify.py`)**
- Contains `run_workflow()` method with token_callback_url and llm parameters to remove
- `_get_workflow_config()` returns tuple (api_key, llm) - simplify to return api_key only
- `_get_knowledge_data()` builds knowledge dict with "claude" key to rename

**Celery Task (`back/app/workers/dify_tasks.py`)**
- `execute_dify_workflow` task has token_callback and llm parameters to remove
- Calls `dify_service.run_workflow()` with these parameters

**CompanyService (`back/app/services/company.py`)**
- `_prepare_task_callbacks()` generates token_callback URL to remove
- `create_company()` and `restart_task()` pass llm to Celery task

**WorkflowConfigService (`back/app/services/workflow_config.py`)**
- `WorkflowConfigResponse` and `WorkflowConfigUpdate` schemas have llm field
- `get_all_configs()` and `update_config()` include llm handling

**Frontend WorkflowCard (`front/src/components/admin/WorkflowCard.vue`)**
- Lines 78-94 have LLM selector in edit mode
- Lines 185-193 have LLM display in read-only mode
- `editData.llm` and update emit include llm

## Out of Scope

- Dify workflow updates in Dify platform (done separately)
- Modifying `raw_mistral_knowledge` field (kept for backward compatibility)
- Modifying `raw_wikipedia_knowledge` or `raw_scraped_website_knowledge` fields
- Consolidating all knowledge fields into a single field
- Adding new LLM options or configurations
- Chat functionality in DifyService (`send_chat_message`, `send_global_chat_message`, `generate_quick_actions`)
- Token webhook endpoint removal (separate consideration)
- Cost calculation logic changes
- Any UI changes beyond WorkflowCard and RawKnowledgeDebug
