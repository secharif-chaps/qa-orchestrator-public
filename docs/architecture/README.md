# MINT Backend Architecture

This document provides a living overview of the MINT backend architecture, design decisions, and key patterns.

## Architecture Philosophy

**Keep It Simple Until We Need Complexity**

The MINT backend intentionally favors simplicity over scalability optimizations. We avoid adding distributed systems complexity until:
1. Actual usage patterns demonstrate the need
2. The planned API Gateway multi-module architecture is implemented
3. Clear service boundaries are established

This approach reduces maintenance burden and makes future architectural changes easier.

## System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              MINT Backend                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                     │
│   │   FastAPI   │───▶│   Services  │───▶│  PostgreSQL │                     │
│   │    (API)    │    │   (Logic)   │    │    (DB)     │                     │
│   └─────────────┘    └─────────────┘    └─────────────┘                     │
│          │                  │                                                │
│          │                  ▼                                                │
│          │           ┌─────────────┐                                        │
│          │           │  SYSTRAN    │  (Translation API)                     │
│          │           └─────────────┘                                        │
│          │                                                                   │
│          ▼                                                                   │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                     │
│   │  RabbitMQ   │───▶│   Celery    │───▶│    Dify     │                     │
│   │  (Broker)   │    │  (Worker)   │    │ (Workflows) │                     │
│   └─────────────┘    └─────────────┘    └─────────────┘                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### FastAPI Application
- REST API endpoints
- Authentication via Keycloak (JWT validation)
- Request validation (Pydantic)
- Background tasks for lightweight async work

### Service Layer
- Business logic
- Database operations
- External API integrations

### Celery Workers
- Long-running Dify workflow execution
- Task retry and failure handling
- NOT used for translation (see ADR-001)

### External Services
- **Keycloak**: Authentication and authorization
- **SYSTRAN**: Translation API
- **Dify**: AI workflow orchestration

## Background Processing Strategy

We use different approaches for background work based on characteristics:

| Task Type | Duration | Volume | Approach | Rationale |
|-----------|----------|--------|----------|-----------|
| Dify Workflows | 30s-5min | High | Celery | Long-running, needs retry, distributed |
| Translation | 2-3s | Low | BackgroundTasks | Quick, low volume, simple |
| Email/Notifications | <1s | Medium | BackgroundTasks | Fire-and-forget |

### Why Not Celery for Everything?

Celery adds operational complexity:
- RabbitMQ broker dependency
- Separate worker deployment
- Queue configuration management
- Kubernetes resource overhead

For tasks that complete quickly with low volume, FastAPI BackgroundTasks provide sufficient capability without the infrastructure overhead.

## Key Design Decisions

### ADR-001: Translation with BackgroundTasks
Translation processing was moved from Celery to FastAPI BackgroundTasks because:
- Translations complete in 2-3 seconds
- Low concurrent volume
- Simpler deployment

[Full ADR](./adr-001-translation-background-tasks.md)

## Future Architecture: API Gateway

A planned API Gateway architecture will introduce:
- Service mesh for inter-service communication
- Proper module boundaries
- Independent scaling per module

Until this architecture is implemented, we maintain a monolithic backend to:
- Avoid premature optimization
- Keep deployment simple
- Enable faster iteration

## Database Schema

The backend uses PostgreSQL with SQLAlchemy ORM:

- **companies**: Core company records
- **company_***: Section tables (profile, digital, timeline, etc.)
- **translations**: Normalized translation storage
- **translation_jobs**: Translation job tracking
- **folders**: Organizational folders for companies

See `alembic/versions/` for migration history.

## Authentication Flow

```
Client → API → Keycloak (JWT validation) → Extract claims → Authorize
```

- JWT tokens from Keycloak contain user identity and roles
- Organization membership from Keycloak Organizations
- Permission checks via `fastapi-keycloak` dependency injection

## Adding New Features

When adding new async processing:

1. **Evaluate the task characteristics**:
   - Duration: <5s or >5s?
   - Volume: How many concurrent requests?
   - Retry needs: Critical or fire-and-forget?

2. **Choose the approach**:
   - Quick + Low volume → `BackgroundTasks`
   - Long + High volume + Retry needed → Celery

3. **Document the decision** if non-obvious (add to this doc or create ADR)

## Monitoring and Observability

- **Logs**: Structured JSON logging via `app.core.logging_config`
- **Celery Flower**: Task monitoring at `/flower` (port 5555)
- **Health checks**: `/health` endpoint for Kubernetes probes

## References

- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [Celery Documentation](https://docs.celeryq.dev/)
- [SQLAlchemy Documentation](https://docs.sqlalchemy.org/)
- [Keycloak Documentation](https://www.keycloak.org/documentation)
