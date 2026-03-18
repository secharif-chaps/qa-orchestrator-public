---
name: n8n-test
description: >
  Generates comprehensive test cases and test data for n8n workflows including unit,
  integration and end-to-end levels. Use when user mentions "test n8n", "test workflow",
  "test cases workflow", or needs QA strategy for n8n automations. Activates when
  creating test plans for Dify integrations, validating workflow error handling, or
  generating mock data for ChapsMind workflows.
  CRITICAL - Always include edge cases (empty data, rate limits, timeouts) and error fixtures.
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Read, Write, Bash
---

## When to use this skill

- When the user asks to create test cases for n8n workflows
- When writing a QA strategy for workflow automations
- When generating mock data or fixtures for workflow testing
- When the user says "test n8n", "test workflow", "test cases"
- When validating Dify callback error handling
- When testing LLM prompt output compliance

# N8N Test Case Generator - ChapsMind

**CRITICAL**: Always include edge cases (empty data, rate limits, timeouts) and error fixtures. Never test only the happy path.

## Test Levels

| Level | Scope | What to test |
|-------|-------|-------------|
| Unit | Single node | Config, expressions, input/output shapes |
| Integration | Node chain | Data transformation, error propagation, branching |
| E2E | Full workflow | Happy path, error scenarios, edge cases, performance |

## Test Case Template

```markdown
### TC-[ID]: [Name]
**Level**: Unit / Integration / E2E
**Node(s)**: [target nodes]
**Priority**: Critical / High / Medium / Low

**Preconditions**: [setup required]

**Input Data**:
```json
{ "example": "data" }
```

**Steps**:
1. [Action]
2. [Action]

**Expected Result**: [what should happen]
**Status**: Pass / Fail / Blocked
```

## Coverage Matrix

| Workflow Path | Happy | Empty | Error | Load | Edge |
|---------------|-------|-------|-------|------|------|
| Main flow     | [ ]   | [ ]   | [ ]   | [ ]  | [ ]  |
| Branch A      | [ ]   | [ ]   | [ ]   | [ ]  | [ ]  |
| Error handler | [ ]   | N/A   | [ ]   | [ ]  | [ ]  |

## Examples

### Example 1 - Dify screen_jobs error aggregation (TAR-1098)

```markdown
### TC-01: LLM rate limit during screen_jobs workflow
**Level**: Integration | **Priority**: Critical
**Node(s)**: HTTP Request Dify -> IF Error Check -> Error Aggregator

**Input**: Company with 50+ job postings (triggers multiple LLM calls)

**Steps**:
1. Trigger screen_jobs workflow with company_id = "test-large-corp"
2. Simulate Dify returning { "error": "rate_limit_exceeded" } on 3rd call
3. Observe error aggregation node

**Expected**: Error stored in aggregation, workflow continues to next section
              (screen_press), final report includes partial screen_jobs data
              with error flag { "screen_jobs": { "status": "partial", "error_count": 1 } }
```

### Example 2 - WorldCheck HMAC auth failure (TAR-1237)

```markdown
### TC-02: WorldCheck API returns 401 on expired HMAC signature
**Level**: Unit | **Priority**: High
**Node(s)**: HTTP Request - WorldCheck API

**Input**: Valid company SIREN, but HMAC timestamp > 5min old (clock drift)

**Steps**:
1. Set system clock offset +6min in test env
2. Trigger WorldCheck screening for SIREN "443061841"

**Expected**: 401 detected, retry with fresh HMAC signature (max 2 retries).
              If still failing, store error { "source": "worldcheck",
              "error": "auth_failed", "retries": 2 } and skip WorldCheck data.
```

## LLM-Specific Tests

- **Prompt injection resistance**: Input with "ignore previous instructions" in company name
- **Output format compliance**: Verify JSON schema matches expected structure
- **Hallucination detection**: Cross-reference generated facts with source data
- **Token limit handling**: Company with extremely long description (>8K tokens)
- **Rate limit recovery**: Burst of 20 concurrent Dify calls
