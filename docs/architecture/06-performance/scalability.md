# Scalability

This document describes ChapsMind's current scalability characteristics and planned improvements.

## Current Architecture

### Single-Instance Setup

ChapsMind currently operates as a **single-instance deployment**:

```
                     +------------------+
                     |   Load Balancer  |
                     |   (Kubernetes)   |
                     +--------+---------+
                              |
              +---------------+---------------+
              |                               |
    +---------v---------+         +-----------v-----------+
    |   Backend API     |         |   Frontend SPA        |
    |   (Single Pod)    |         |   (Static Assets)     |
    +--------+----------+         +-----------------------+
             |
    +--------v----------+
    |    PostgreSQL     |
    |  (Single Instance)|
    +-------------------+
```

### Component Scaling Characteristics

| Component          | Current                            | Scalability                               |
| ------------------ | ---------------------------------- | ----------------------------------------- |
| **Frontend SPA**   | Static assets served via CDN/nginx | Horizontally scalable (stateless)         |
| **Backend API**    | Single FastAPI instance            | Can scale horizontally (stateless design) |
| **Celery Workers** | 1-2 worker instances               | Can scale horizontally                    |
| **PostgreSQL**     | Single instance                    | Vertical scaling only (currently)         |
| **RabbitMQ**       | Single instance                    | Can be clustered                          |

## Async Processing with Celery

The primary scalability mechanism is **Celery workers** for background processing:

```
+-------------+     +-------------+     +------------------+
|  Backend    | --> |  RabbitMQ   | --> |  Celery Workers  |
|  API        |     |  (Broker)   |     |  (1-N instances) |
+-------------+     +-------------+     +--------+---------+
                                                 |
                                        +--------v---------+
                                        |  Dify Workflows  |
                                        |  (AI Processing) |
                                        +------------------+
```

### Task Types

| Task Category                     | Processing Time | Worker Requirements       |
| --------------------------------- | --------------- | ------------------------- |
| **Data Collection**               | 1-5 minutes     | I/O bound, network heavy  |
| **AI specific section workflows** | 30s - 2 minutes | CPU/memory for Dify calls |
| **Report Generation**             | 10-30 seconds   | I/O bound                 |

### Worker Configuration

Current Celery worker configuration:

```python
# Celery settings
CELERY_WORKER_CONCURRENCY = 4  # Concurrent tasks per worker
CELERY_TASK_ACKS_LATE = True   # Acknowledge after completion
CELERY_WORKER_PREFETCH_MULTIPLIER = 1  # One task at a time per worker
```

## Dify Scalability (Critical Bottleneck)

### The Problem

**Dify is the primary scalability bottleneck** in ChapsMind. Every company card creation triggers multiple Dify workflow calls:

```
Company Created
      │
      ▼
┌─────────────────┐
│ data_collection │  ← Must complete first
└────────┬────────┘
         │
         ▼
┌────────────────────────────────────────────────────┐
│  Parallel Dify Calls (up to MAX_CONCURRENT)        │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ profile │ │ digital │ │timeline │ │products │  │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘  │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │  jobs   │ │   csr   │ │  press  │ │  team   │  │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘  │
└────────────────────────────────────────────────────┘
```

**Each company = 9 Dify workflow calls** (1 data_collection + 8 parallel section workflows)

### Scalability Concerns

| Scenario    | Companies/Day | Dify Calls/Day | Impact                      |
| ----------- | ------------- | -------------- | --------------------------- |
| **Current** | ~10           | ~90            | Manageable                  |
| **Growth**  | 50            | ~450           | Queue buildup possible      |
| **Scale**   | 200+          | ~1800+         | **Dify becomes bottleneck** |

### Current Mitigation: DifyConcurrencyManager

ChapsMind limits parallel Dify calls to prevent overwhelming the Dify instance:

```python
# Configuration (app/core/config.py)
MAX_CONCURRENT_WORKFLOWS = 10  # Max parallel Dify calls across all workers
TASK_TIMEOUT_MINUTES = 5       # Timeout for individual workflows
```

The `DifyConcurrencyManager` uses a semaphore pattern to:

- Queue Dify calls when limit is reached
- Prevent Dify instance overload
- Provide fair scheduling across companies

### Scaling Options (Future Considerations)

| Option                                 | Complexity | Impact                                       |
| -------------------------------------- | ---------- | -------------------------------------------- |
| **Increase MAX_CONCURRENT_WORKFLOWS**  | Low        | Limited by Dify instance capacity            |
| **Deploy multiple Dify instances**     | Medium     | Linear scaling, requires load balancing      |
| **Dify Cloud (managed)**               | Low        | Offload scaling to Anthropic/Dify            |
| **Replace Dify with direct LLM calls** | High       | Full control, removes middleware bottleneck  |
| **Async batch processing**             | Medium     | Process companies in batches during off-peak |

### Monitoring Dify Performance

Key metrics to watch:

- Dify workflow execution time (via task logs)
- Queue depth in RabbitMQ (pending company tasks)
- `DifyConcurrencyManager` semaphore wait times
- Task timeout frequency

### Known Limitations

1. **Single Dify instance**: All workflows go through one Dify deployment
2. **No Dify horizontal scaling**: Current setup doesn't support multiple Dify instances
3. **Network dependency**: Dify calls are network-bound, latency affects throughput
4. **No priority queuing**: All companies processed FIFO, no prioritization

## Scaling Strategies

### Horizontal Scaling (Current Capabilities)

Components that can be scaled horizontally today:

1. **Celery Workers**: Add more worker pods in Kubernetes

   ```yaml
   # Example: Scale workers
   kubectl scale deployment celery-worker --replicas=3 -n chapsmind
   ```

2. **Backend API**: Design is stateless, can add replicas
   ```yaml
   # Example: Scale API (requires load balancer configuration)
   kubectl scale deployment mint-backend --replicas=2 -n chapsmind
   ```

### Vertical Scaling (Current Approach)

For database and single-instance components:

- Increase PostgreSQL memory/CPU allocation
- Increase RabbitMQ resources for high queue volumes

## Future: Global Service as Gateway

Planned architecture where Global Service acts as both API Gateway and shared services:

```
                    ┌─────────────────────────────────────────┐
                    │              Frontend SPA               │
                    └──────────────────┬──────────────────────┘
                                       │ REST (all requests)
                                       ▼
                    ┌─────────────────────────────────────────┐
                    │      Global Service (API Gateway)       │
                    │  - JWT validation (Keycloak)            │
                    │  - Request routing                      │
                    │  - Token management                     │
                    │  - Folders & sharing                    │
                    │  - Organization settings                │
                    └──────────┬─────────────────┬────────────┘
                               │                 │
                    Internal   │                 │  Internal
                               ▼                 ▼
                    ┌─────────────────┐ ┌─────────────────┐
                    │  Screen Module  │ │  Target Module  │
                    │   (Companies)   │ │  (Watchfiles)   │
                    └─────────────────┘ └─────────────────┘
```

### Why Global Service as Gateway?

| Aspect                          | Benefit                                                     |
| ------------------------------- | ----------------------------------------------------------- |
| **Single entry point**          | One service handles auth, routing, and shared functionality |
| **No microservices complexity** | Avoids separate Kong/Traefik gateway                        |
| **Simplified security**         | Only Global Service is internet-facing                      |
| **Right-sized**                 | Appropriate complexity for current team and scale           |

### Services

| Service            | Purpose                                           | Access          |
| ------------------ | ------------------------------------------------- | --------------- |
| **Global Service** | Gateway + shared services (tokens, folders, orgs) | Internet-facing |
| **Screen Module**  | Company monitoring functionality                  | Internal only   |
| **Target Module**  | Sales targeting functionality                     | Internal only   |

See [ADR-0009](../adr/0009-global-service-architecture.md) for detailed architecture decision.

## Database Scaling Considerations

### Current Limitations

- Single PostgreSQL instance
- No read replicas
- No connection pooling (relies on SQLAlchemy pool)

### Future Improvements (When Needed)

1. **Read replicas**: Separate read traffic from write traffic
2. **Connection pooling**: PgBouncer for connection management
3. **Table partitioning**: For large tables (e.g., tasks, company data)

## Performance Recommendations

### For Current Scale

1. **Optimize queries**: Use indexes, avoid N+1 queries
2. **Scale Celery workers**: Add workers for heavy AI processing periods
3. **Monitor bottlenecks**: Use Flower to identify slow tasks

### For Growth (100+ Organizations)

1. **Implement Redis caching**: Reduce database load
2. **Deploy read replicas**: Separate read-heavy analytics
3. **Consider service extraction**: Start with Global Service architecture

## Related Documentation

- [Caching](caching.md): Query optimization strategies
- [Monitoring](monitoring.md): Performance monitoring tools
- [Backend Architecture](../03-development/backend-architecture.md): Service layer patterns
