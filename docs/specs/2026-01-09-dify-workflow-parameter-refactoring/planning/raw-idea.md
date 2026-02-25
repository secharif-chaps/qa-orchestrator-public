# Raw Idea: Dify Workflow Parameter Refactoring

## Context
We're switching from a dual LLM setup (Mistral + Claude) to using GPT 5.1 exclusively. This requires cleaning up legacy parameters and renaming knowledge base references.

## User's Requirements
1. **Remove token callback URL** - No longer needed in Dify calls
2. **Remove LLM choice parameter** - No longer needed since we're using GPT 5.1 everywhere
3. **Remove LLM from workflow_config table** - The database column and admin UI for LLM selection
4. **Rename Claude knowledge to GPT** - In company data and Dify workflow inputs
5. **Rename Mistral knowledge to GPT** - Since we're consolidating to one LLM

## Additional Changes Identified (from code analysis)
6. **Simplify admin workflows endpoint** - Remove LLM update capability
7. **Remove frontend LLM selector** - WorkflowCard.vue has dropdown for claude/mistral
8. **Update RawKnowledgeDebug component** - Shows both mistral and claude knowledge
9. **Database migration** - Remove llm column, rename knowledge columns
10. **Consolidate knowledge bases** - Consider if we need 4 separate knowledge fields or can simplify

## Files Affected

### Backend - Token Callback Removal:
- `back/app/services/dify.py` - Lines 134, 242-244, 273-283
- `back/app/workers/dify_tasks.py` - Lines 77, 127-137, 155
- `back/app/services/company.py` - Lines 239, 249, 335, 346, 352-371

### Backend - LLM Removal:
- `back/app/models/workflow_config.py` - Line 13 (llm column)
- `back/app/services/workflow_config.py` - Lines 20, 49, 60
- `back/app/services/dify.py` - Lines 42-66, 136, 164-180, 196
- `back/app/workers/dify_tasks.py` - Lines 78, 157
- `back/app/services/company.py` - Lines 228-250, 323-347
- `back/app/api/endpoints/admin.py` - Lines 37-79

### Backend - Knowledge Renaming:
- `back/app/models/company.py` - Lines 78-82
- `back/app/services/dify.py` - Lines 68-122, 209-214
- `back/app/services/company.py` - Lines 69-72, 95-98
- `back/app/schemas/company.py`

### Frontend:
- `front/src/api/workflows.ts` - Lines 6-32
- `front/src/pages/admin.workflows.vue`
- `front/src/components/admin/WorkflowCard.vue` - Lines 78-94, 230-234
- `front/src/components/company/profile/RawKnowledgeDebug.vue`

### Database Migration:
- New alembic migration needed to:
  - Remove llm column from workflow_configs
  - Rename raw_claude_knowledge to raw_gpt_knowledge
  - Rename raw_mistral_knowledge (decide if keeping or consolidating)

## Open Questions
- Should we consolidate all knowledge into a single `raw_knowledge` field, or maintain separate fields for different knowledge types?
- Are there any Dify workflows that still reference the old parameter names that need updating?
- Should we maintain backward compatibility or do a clean break?
