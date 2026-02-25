# Performance View

This section documents ChapsMind's performance architecture, including current capabilities, limitations, and future scalability plans.

## Overview

ChapsMind is currently deployed as a **single-instance architecture** optimized for small to medium-scale deployments. The system prioritizes functional correctness and maintainability while providing adequate performance for typical market intelligence workloads.

## Current Architecture Characteristics

### Deployment Model

- **Single-instance deployment**: One backend API server, one database instance
- **Kubernetes orchestration**: Production runs on Kubernetes with namespace `chapsmind`
- **Horizontal scaling planned**: Multi-service architecture on the roadmap (see [ADR-0009](../adr/0009-global-service-architecture.md))

### Processing Model

- **Synchronous API requests**: Standard HTTP request/response for user interactions
- **Asynchronous task processing**: Celery workers handle background jobs (AI workflows, data collection)
- **Message broker**: RabbitMQ for task queue communication

## Performance Characteristics

| Aspect | Current State | Notes |
|--------|---------------|-------|
| **API Response Time** | < 200ms typical | For standard CRUD operations |
| **AI Task Processing** | 30s - 5min | Depends on Dify workflow complexity |
| **Concurrent Users** | ~50-100 | Per organization, limited by single instance |
| **Database Size** | < 10GB typical | Per organization dataset |

## Known Limitations

1. **No Redis caching layer**: Query caching relies on Pinia Colada on the frontend only
2. **Single database instance**: No read replicas or sharding
3. **Single API server**: No load balancing across multiple API instances
4. **Limited observability**: Basic monitoring via Celery Flower, no full APM stack

## Future Performance Roadmap

| Phase | Enhancement | Timeline |
|-------|-------------|----------|
| **Phase 1** | Global Service extraction (API gateway pattern) | Q2 2025 |
| **Phase 2** | Multi-instance API deployment with load balancing | Q3 2025 |
| **Phase 3** | Redis caching layer introduction | Q3 2025 |
| **Phase 4** | Full observability stack (Prometheus, Grafana, Jaeger) | Q4 2025 |

## Section Contents

- **[Scalability](scalability.md)**: Current and planned scaling strategies
- **[Caching](caching.md)**: Query caching and optimization strategies
- **[Monitoring](monitoring.md)**: Observability and monitoring tools

## Related Documentation

- [Infrastructure View](../04-infrastructure/README.md): Deployment configuration
- [Backend Architecture](../03-development/backend-architecture.md): Service layer patterns
- [ADR-0009: Global Service Architecture](../adr/0009-global-service-architecture.md): Multi-service roadmap
