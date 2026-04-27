# ADR-0004: Dify for AI Orchestration

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** ai, orchestration, llm

---

## Context

ChapsMind is an AI-first market intelligence platform. The core value proposition depends on intelligent data collection, analysis, and summarization. We need to:

- Orchestrate multiple LLM calls for complex workflows
- Chain AI operations (data collection, analysis, summarization)
- Allow non-developers to modify AI workflows
- Support multiple LLM providers (OpenAI, Anthropic, etc.)
- Monitor AI performance and costs
- Iterate on prompts without code deployments

Traditional approaches would embed LLM calls directly in application code, but this creates:

- Tight coupling between business logic and AI implementation
- Difficulty in prompt iteration without deployments
- No visibility into AI workflow performance
- Challenges in managing multiple LLM providers

---

## Decision

We will use **Dify** as the AI orchestration platform for all workflows. Dify provides a visual workflow builder for AI pipelines and data collection.

Key implementation decisions:

- **Dify for all workflows**: All AI and data collection goes through Dify
- **API integration**: Backend calls Dify via REST API with workflow-specific API keys
- **Celery coordination**: Celery tasks orchestrate Dify workflow execution
- **Workflow types**: Datacollector (raw data), Section analyzers (jobs, products, etc.)

---

## Options Considered

### Option 1: Dify for AI Orchestration (Chosen)

**Description:** Use Dify as a dedicated AI orchestration platform with visual workflow builder.

**Pros:**

- Visual workflow editor for non-developers
- Built-in prompt management and versioning
- Support for multiple LLM providers
- Cost tracking and monitoring
- No code deployment needed for AI changes
- Separates AI logic from application code
- Active open-source community

**Cons:**

- Additional infrastructure component
- Network latency for API calls
- Learning curve for workflow builder
- Dependency on external platform

### Option 2: Direct LLM Integration

**Description:** Call LLM APIs directly from application code using libraries like LangChain.

**Pros:**

- No additional infrastructure
- Tighter control over implementation
- Simpler architecture
- Lower latency

**Cons:**

- Prompt changes require deployments
- No visual editing for business users
- Must build own monitoring and cost tracking
- Tight coupling between app and AI logic
- Harder to iterate on AI workflows

### Option 3: Custom AI Pipeline Service

**Description:** Build a custom microservice for AI orchestration.

**Pros:**

- Complete control over implementation
- Tailored to exact requirements
- No external dependencies

**Cons:**

- Significant development effort
- Must build workflow management from scratch
- No visual interface
- Ongoing maintenance burden
- Delays time to market

---

## Consequences

### Positive

- **Rapid AI Iteration**: Prompt engineers can modify workflows without code changes
- **Visual Debugging**: Workflow execution visible in Dify UI
- **Provider Flexibility**: Easy to switch between LLM providers
- **Cost Visibility**: Built-in tracking of AI costs per workflow
- **Separation of Concerns**: AI logic separated from application logic
- **Team Enablement**: Non-developers can contribute to AI improvements

### Negative

- **Infrastructure Complexity**: Additional service to deploy and maintain
- **Network Overhead**: API calls to Dify add latency
- **Platform Lock-in**: Workflows built in Dify-specific format
- **Learning Curve**: Team must learn Dify workflow builder

### Neutral

- Dify is actively developed; features may change
- Some workflows may still need code for complex logic

---

## Implementation Notes

### Workflow Architecture

```
Company Creation
       |
       v
+------------------+
| Dify Datacollector|  <-- Runs FIRST to gather raw data
+------------------+
       |
       v
+------------------+
| Dify Workflows    |  <-- Section-specific processing
+------------------+
  |  |  |  |  |
  v  v  v  v  v
Jobs Products Team CSR News
```

### Integration Pattern

```python
# Backend calls Dify via REST API
from dify_client import DifyClient

async def run_datacollector(company: Company) -> dict:
    client = DifyClient(api_key=settings.DIFY_API_KEY)
    result = await client.run_workflow(
        workflow_id="datacollector",
        inputs={"company_name": company.name, "website": company.website}
    )
    return result
```

---

## References

- [Dify Documentation](https://docs.dify.ai/)
- [Dify GitHub](https://github.com/langgenius/dify)
- [AI Orchestration Module](../02-application/modules/ai-orchestration.md)
- [ChapsMind Tech Stack](../../../agent-os/product/tech-stack.md)
