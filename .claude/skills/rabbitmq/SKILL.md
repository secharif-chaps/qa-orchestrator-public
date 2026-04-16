---
name: rabbitmq
description: >
  RabbitMQ message broker integration with Celery for async task processing.
  Use when configuring Celery tasks, managing workflow queues, publishing async jobs,
  handling task retries and timeouts, or monitoring queue health.
  Activates when working on apps/screen/app/workers/, apps/screen/app/core/celery_app.py,
  or any code that publishes tasks to RabbitMQ queues.
  CRITICAL - Always use task_acks_late and handle task failures gracefully with proper status updates.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating or modifying Celery tasks in `apps/screen/app/workers/`
- When configuring queues in `apps/screen/app/core/celery_app.py`
- When publishing async tasks from API endpoints
- When handling task failures, retries, or timeouts
- When debugging queue issues or monitoring with Flower
- When working with the `dify_workflows` or `translations` queues

# RabbitMQ & Celery Integration

**CRITICAL**: Always acknowledge tasks late (`task_acks_late=True`) and update task status in the database on both success and failure.

## Architecture

```
API Endpoint → Celery .delay() → RabbitMQ Queue → Celery Worker → Dify API
                                                        ↓
                                                  DB Status Update
```

## Configuration

### Celery App (`apps/screen/app/core/celery_app.py`)

```python
from celery import Celery

celery_app = Celery("screen")
celery_app.conf.update(
    broker_url=settings.RABBITMQ_URL,  # amqp://guest:guest@target-rabbitmq:5672//
    result_backend="rpc://",
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    task_track_started=True,
    task_reject_on_worker_lost=True,
    task_soft_time_limit=600,   # 10 min soft limit
    task_time_limit=900,        # 15 min hard limit
    result_expires=3600,
)
```

### Queue Definitions

```python
from kombu import Queue

celery_app.conf.task_queues = [
    Queue("dify_workflows", routing_key="workflow.#", queue_arguments={"x-max-priority": 10}),
    Queue("translations", routing_key="translation.#", queue_arguments={"x-max-priority": 5}),
]

celery_app.conf.task_routes = {
    "execute_dify_workflow": {"queue": "dify_workflows", "routing_key": "workflow.execute"},
}
```

## Task Definition Pattern

```python
from app.core.celery_app import celery_app

@celery_app.task(
    bind=True,
    name="execute_dify_workflow",
    queue="dify_workflows",
    max_retries=3,
    default_retry_delay=30,
)
def execute_dify_workflow(
    self,
    task_id: int,
    company_id: int,
    task_type: str,
    api_key: str,
    success_callback: str,
    error_callback: str,
) -> None:
    """Execute a Dify workflow asynchronously."""
    db = SessionLocal()
    try:
        # Update status to RUNNING
        task = db.query(Task).get(task_id)
        task.status = "running"
        db.commit()

        # Execute workflow
        dify_service = DifyService()
        dify_service.run_workflow(
            task_type=task_type,
            company_id=company_id,
            api_key=api_key,
            callbacks={"success": success_callback, "error": error_callback},
        )
    except Exception as exc:
        # Update status to ERROR
        task.status = "error"
        task.error_message = str(exc)
        db.commit()
        raise self.retry(exc=exc)
    finally:
        db.close()
```

## Publishing Tasks

```python
from app.workers.dify_tasks import execute_dify_workflow

# From API endpoint
execute_dify_workflow.delay(
    task_id=task.id,
    company_id=company.id,
    task_type="data_collection",
    api_key=workflow_config.api_key,
    success_callback=f"{settings.BASE_URL}/api/webhooks/dify/success",
    error_callback=f"{settings.BASE_URL}/api/webhooks/dify/error",
)
```

## Periodic Tasks (Celery Beat)

```python
celery_app.conf.beat_schedule = {
    "cleanup-stale-running-tasks": {
        "task": "cleanup_stale_running_tasks",
        "schedule": 120.0,  # Every 2 minutes
    },
}
```

## Concurrency Control

```python
MAX_CONCURRENT_WORKFLOWS = 10

class DifyConcurrencyManager:
    """Limits concurrent Dify workflow executions."""

    async def acquire_slot(self) -> bool:
        """Returns True if a workflow slot is available."""
        ...

    async def release_slot(self) -> None:
        """Release a workflow slot after completion."""
        ...
```

## Monitoring

- **Flower UI**: `http://localhost:5555` (admin/admin)
- **RabbitMQ Management**: `http://localhost:15672` (guest/guest)
- **Logs**: `task logs:service -- screen_celery_worker`

## Key Rules

1. **Always update task status** in DB on success AND failure
2. **Use `bind=True`** to access `self.retry()` for retries
3. **Close DB sessions** in `finally` blocks
4. **Set timeouts** - soft (warning) and hard (kill) limits
5. **One task per message** - Don't batch multiple operations
6. **Idempotent tasks** - Tasks may be retried, design accordingly
7. **Log task IDs** - Always include task_id in log messages for tracing
