---
name: n8n-optimize
description: >
  Optimizes n8n workflows for ChapsMind covering architecture, performance, LLM prompt
  refinement, and error handling patterns. Use when user mentions "workflow", "n8n",
  "automation", "optimize workflow", or asks about workflow architecture. Activates when
  analyzing n8n JSON exports, reviewing Dify integrations, or improving LLM prompt chains.
  CRITICAL - Always validate error handling on all external calls (API, DB, LLM).
license: MIT
metadata:
  author: owlint
  version: "1.0"
  stack: global
allowed-tools: Read, Grep, Bash
---

## When to use this skill

- When the user asks to optimize an n8n workflow
- When reviewing workflow architecture or performance
- When refining LLM prompts in workflow nodes
- When the user says "workflow", "n8n", "automation", "optimize"
- When analyzing Dify integration patterns (callbacks, error aggregation)
- When debugging rate limiting or timeout issues in workflows

# N8N Workflow Optimizer - ChapsMind

**CRITICAL**: Always validate error handling on all external calls (API, DB, LLM). Every external node must have a fallback path.

## Analysis Framework

### 1. Architecture Review

- Workflow structure (nodes, connections, branching)
- Error handling patterns (try/catch, fallback paths)
- Data flow efficiency (unnecessary transformations?)
- Credential management (no hardcoded secrets)

### 2. Performance Optimization

- Batch processing vs individual items
- Parallel execution opportunities
- Rate limiting and retry strategies
- Caching opportunities

### 3. LLM Prompt Refinement

- Prompt clarity and specificity
- Token optimization (remove redundancy)
- Output format constraints (JSON schema, structured output)
- Temperature and model selection

### 4. Error Handling Patterns

```
Critical Path: Trigger -> Process -> Validate -> Output
Error Path:   Any Node -> Error Trigger -> Log -> Notify -> Retry/Fallback
```

## Output Format

For each finding:
```
[SEVERITY: Critical/Important/Suggestion]
Node: [node name]
Issue: [what's wrong]
Impact: [why it matters]
Fix: [concrete solution with config/code]
```

## Examples

### Example 1 - Dify error aggregation (ChapsMind Screen)

```
[Critical] Node: HTTP Request - Dify Callback
Issue: No error handling when Dify workflow returns LLM rate limit error
Impact: Entire screen generation fails silently, user sees empty card
Fix: Add IF node after HTTP Request checking response.error field.
     Route to error aggregation node that stores { workflow: "screen_jobs",
     error: response.error, timestamp: $now } and continues to next workflow
     instead of stopping. This is the pattern used in TAR-1098 through TAR-1193.
```

### Example 2 - Rate limiting on SerpAPI

```
[Important] Node: HTTP Request - SerpAPI Search
Issue: No rate limiting between consecutive SerpAPI calls
Impact: Hits SerpAPI rate limit on companies with many search queries,
        causes 429 errors and incomplete data
Fix: Add Wait node (1s delay) between batched SerpAPI calls.
     Use $itemIndex to calculate dynamic delay: Math.min($itemIndex * 200, 2000)ms.
     Add retry on 429 with exponential backoff (1s, 2s, 4s, max 3 retries).
```
