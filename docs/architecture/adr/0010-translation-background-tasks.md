# ADR-001: Translation Processing with FastAPI BackgroundTasks

## Status

**Accepted** - January 2026

## Context

The MINT application needs to translate company data fields from English to multiple target languages (French, Spanish, German, Portuguese) using the SYSTRAN Translation API.

### Initial Implementation

The initial implementation used Celery with RabbitMQ for asynchronous translation processing:

```text
Frontend → API → DB (create job) → RabbitMQ → Celery Worker → SYSTRAN → DB
```

This architecture required:

- RabbitMQ message broker
- Separate Celery worker process
- Translation-specific queue configuration
- Kubernetes deployment for the worker

### Problem Statement

After observing translation in production, we identified several issues:

1. **Over-engineering for the workload**: Translation of a company's fields (~15 fields) completes in approximately 2-3 seconds
2. **Low volume**: Translation requests are infrequent - users translate a company at most once per language
3. **Infrastructure complexity**: RabbitMQ and Celery worker add deployment complexity
4. **Queue configuration issues**: Different Celery configurations between preprod and local environments caused queue priority conflicts
5. **Kubernetes overhead**: Separate worker deployment adds resource consumption and operational complexity

### Strategic Context

The team is intentionally keeping the system simple while the planned API Gateway multi-module architecture is not yet implemented. Adding complexity now would:

- Increase maintenance burden before proper service boundaries are defined
- Make future architectural changes harder
- Provide no tangible benefit given current usage patterns

## Decision

Replace Celery-based translation processing with FastAPI BackgroundTasks.

### New Architecture

```text
Frontend → API → Response (202 Accepted)
                    ↓
              BackgroundTask → SYSTRAN → DB (update)
```

### Implementation

1. **New module**: `app/services/translation_runner.py`
   - Contains `run_translation_background()` function
   - Manages its own database session (background tasks run after response)
   - Handles errors gracefully and updates job status

2. **Updated endpoint**: `app/api/endpoints/translation.py`
   - Uses FastAPI `BackgroundTasks` dependency
   - Adds task via `background_tasks.add_task()`
   - Returns immediately with job details

3. **Removed**: `app/workers/translation_tasks.py`
   - Celery task file deleted (scout pattern cleanup)
   - This ADR serves as the reference for the old implementation

4. **Updated Celery config**: `app/core/celery_app.py`
   - Removed translation tasks from includes
   - Removed translations queue

## Consequences

### Positive

- **Simpler deployment**: No separate worker process needed for translations
- **Reduced infrastructure**: No RabbitMQ dependency for translation (still used for Dify workflows)
- **Faster iteration**: Changes to translation logic deploy with the main application
- **Easier debugging**: Translation runs in the same process, visible in backend logs
- **Kubernetes simplicity**: One less deployment to manage

### Negative

- **No automatic retry**: BackgroundTasks don't have built-in retry with backoff
- **Single process**: All translations run in the API process
- **No distributed processing**: Cannot scale translation independently

### Mitigations

- **Manual retry**: Users can re-trigger translation if it fails
- **Error handling**: Graceful error handling updates job status to "failed"
- **Monitoring**: Translation errors logged for observability

## When to Revisit

Consider migrating back to Celery when:

1. **High volume**: Translation requests exceed what a single backend can handle
2. **Retry logic needed**: Automatic retry with exponential backoff becomes critical
3. **Distributed processing**: Need to scale translation independently from API
4. **API Gateway ready**: When multi-module architecture is implemented, reassess service boundaries

## References

- [FastAPI BackgroundTasks Documentation](https://fastapi.tiangolo.com/tutorial/background-tasks/)
- [SYSTRAN Translation API](https://docs.systran.net/translateAPI/translation/)
