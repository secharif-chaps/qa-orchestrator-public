---
name: llmgateway
description: >
  Dify AI workflow orchestration for LLM-powered data collection and analysis.
  Use when integrating with Dify workflows, executing AI tasks, handling webhook callbacks,
  injecting knowledge data into workflows, or implementing chat interactions.
  Activates when working on apps/screen/app/infrastructure/dify/,
  apps/screen/app/services/dify.py, or API endpoints handling AI features.
  CRITICAL - Always use async mode with callbacks for long-running workflows; blocking mode only for chat.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When calling Dify workflow APIs from the screen backend
- When creating or modifying webhook callback handlers
- When injecting knowledge data into analysis workflows
- When implementing chat features (company chat, Chaps-e)
- When configuring workflow API keys per task type
- When handling Dify error responses and timeouts

# Dify AI Workflow Integration

**CRITICAL**: Use async mode with callbacks for data collection and analysis workflows. Blocking mode is only for chat interactions.

## Architecture

```
API Endpoint
    ↓
TaskService.create_task()     → DB: Task(status=PENDING)
    ↓
Celery: execute_dify_workflow → RabbitMQ
    ↓
DifyService.run_workflow()    → Dify API (async)
    ↓
Dify completes workflow       → Webhook callback
    ↓
Webhook handler               → DB: Task(status=SUCCEEDED) + save results
```

## DifyService

```python
# apps/screen/app/services/dify.py
class DifyService:
    def __init__(self):
        self.base_url = settings.DIFY_URL       # http://10.6.1.10/v1
        self.fallback_api_key = settings.DIFY_API_KEY
        self.chat_api_key = settings.DIFY_CHAT_API_KEY

    async def run_workflow(
        self,
        task_type: str,
        company_id: int,
        api_key: str,
        callbacks: dict[str, str],
    ) -> dict:
        inputs = self._build_inputs(company_id, task_type)

        # Knowledge injection for analysis workflows
        if task_type != "data_collection":
            knowledge = self._get_knowledge_data(company_id)
            inputs.update({
                "mistral": knowledge["mistral"],
                "gpt": knowledge["gpt"],
                "wikipedia": knowledge["wikipedia"],
                "scraped": knowledge["scraped"],
                "pappers": knowledge["pappers"],
            })

        async with httpx.AsyncClient(timeout=10.0) as client:
            response = await client.post(
                f"{self.base_url}/workflows/run",
                headers={"Authorization": f"Bearer {api_key}"},
                json={
                    "inputs": inputs,
                    "response_mode": "blocking",
                    "user": f"company_{company_id}",
                },
            )
        return response.json()
```

## Workflow Types

| Type              | Mode     | Purpose                               | Callback |
| ----------------- | -------- | ------------------------------------- | -------- |
| `data_collection` | Async    | Gather raw data from multiple sources | Yes      |
| `profile`         | Async    | Analyze company profile               | Yes      |
| `products`        | Async    | Analyze products and services         | Yes      |
| `timeline`        | Async    | Build company timeline                | Yes      |
| `csr`             | Async    | CSR initiatives analysis              | Yes      |
| `press`           | Async    | Press coverage analysis               | Yes      |
| `jobs`            | Async    | Job market analysis                   | Yes      |
| `digital`         | Async    | Digital presence analysis             | Yes      |
| `chat`            | Blocking | Company-specific chat                 | No       |
| `quick_actions`   | Blocking | Action recommendations                | No       |

## Knowledge Injection Pattern

Analysis workflows receive pre-collected data from a prior `data_collection` run:

```python
def _get_knowledge_data(self, company_id: int) -> dict:
    company = self.db.query(Company).get(company_id)
    return {
        "mistral": company.raw_mistral_knowledge or "",
        "gpt": company.raw_gpt_knowledge or "",
        "wikipedia": company.raw_wikipedia_knowledge or "",
        "scraped": company.raw_scraped_knowledge or "",
        "pappers": company.raw_pappers_knowledge or "",
    }
```

## Webhook Callbacks

```python
# apps/screen/app/api/endpoints/webhooks.py
@router.post("/dify/success")
async def dify_success_callback(
    payload: DifyCallbackPayload,
    db: Session = Depends(get_db),
) -> dict:
    task = db.query(Task).get(payload.task_id)
    task.status = "succeeded"
    task.result_data = payload.data
    db.commit()
    return {"status": "ok"}

@router.post("/dify/error")
async def dify_error_callback(
    payload: DifyErrorPayload,
    db: Session = Depends(get_db),
) -> dict:
    task = db.query(Task).get(payload.task_id)
    task.status = "error"
    task.error_message = payload.error
    db.commit()
    return {"status": "ok"}
```

## Chat Integration

```python
from dify_client import ChatClient

async def send_chat_message(
    self,
    message: str,
    company_id: int,
    conversation_id: str | None = None,
) -> dict:
    client = ChatClient(api_key=self.chat_api_key)
    response = client.create_chat_message(
        inputs={"company_context": self._get_company_context(company_id)},
        query=message,
        user=f"company_{company_id}",
        conversation_id=conversation_id,
        response_mode="blocking",
    )
    return response.json()
```

## Workflow Config (API Key Management)

```python
# Each task type maps to a specific Dify workflow via API key
config = workflow_config_service.get_config_by_task_type(task_type)
api_key = config.api_key  # Unique per workflow app in Dify
```

## Error Handling

```python
# apps/screen/app/core/dify_error_config.py
DIFY_ERROR_MESSAGES = {
    "rate_limit_exceeded": "Le service IA est temporairement surchargé",
    "workflow_not_found": "Le workflow demandé n'existe pas",
    "timeout": "Le traitement a dépassé le temps imparti",
}
```

## Key Rules

1. **Async for workflows** - Use callbacks, not blocking mode
2. **Blocking for chat only** - Chat needs immediate response
3. **Inject knowledge** - Analysis workflows need prior data_collection results
4. **Track tokens** - Log token costs per task for billing
5. **Handle timeouts** - Dify workflows can take minutes; don't block API threads
6. **Idempotent callbacks** - Webhook may be called multiple times
7. **Per-workflow API keys** - Each task type has its own Dify app and key
