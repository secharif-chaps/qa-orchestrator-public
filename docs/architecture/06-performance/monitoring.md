# Monitoring

This document describes ChapsMind's current monitoring capabilities and planned observability improvements.

## Current Monitoring Stack

ChapsMind has **limited observability** in the current architecture. The primary monitoring tool is **Celery Flower** for task queue monitoring.

| Component | Monitoring | Tool |
|-----------|------------|------|
| **Celery Tasks** | Task status, timing, failures | Flower |
| **Backend Logs** | Application logs | Docker/Kubernetes logs |
| **Database** | Connection status | Manual checks |
| **API Health** | Basic health endpoint | `/health` endpoint |

## Celery Flower

### Overview

**Flower** is a real-time web-based monitor for Celery. It provides:

- Task progress and history
- Worker status and statistics
- Task rate limiting and priority visualization

### Access

| Environment | URL | Notes |
|-------------|-----|-------|
| **Development** | http://localhost:5555 | Via Docker Compose |
| **Production** | Internal only | Access via kubectl port-forward |

### Dashboard Features

```
+------------------------------------------------------------------+
|  Celery Flower Dashboard                                          |
+------------------------------------------------------------------+
|                                                                   |
|  Workers (2)                    Tasks                             |
|  +-------------------+          +---------------------------+     |
|  | celery@worker-1   |          | Succeeded: 1,234         |     |
|  | Status: Online    |          | Failed: 12               |     |
|  | Tasks: 156        |          | Pending: 5               |     |
|  +-------------------+          | Retried: 3               |     |
|  | celery@worker-2   |          +---------------------------+     |
|  | Status: Online    |                                           |
|  | Tasks: 142        |          Rate: 2.5 tasks/sec              |
|  +-------------------+                                           |
|                                                                   |
+------------------------------------------------------------------+
```

### Key Metrics in Flower

| Metric | Description | Threshold |
|--------|-------------|-----------|
| **Task Success Rate** | Percentage of successful tasks | > 95% |
| **Average Task Time** | Mean execution time per task type | Varies by type |
| **Queue Depth** | Number of pending tasks | < 100 typical |
| **Worker Heartbeat** | Worker health status | All green |

### Task Monitoring Queries

View tasks by type:
- Data collection tasks: Filter by `tasks.data_collection`
- AI analysis tasks: Filter by task name prefix

## Application Logging

### Current Logging Setup

```python
# Backend logging configuration
import logging

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
```

### Log Access

```bash
# Development (Docker Compose)
docker compose logs -f backend
docker compose logs -f backend_celery_worker

# Production (Kubernetes)
kubectl logs -f deployment/mint-backend -n chapsmind
kubectl logs -f deployment/celery-worker -n chapsmind
```

### Structured Log Events

Key events to monitor:

| Event | Log Level | Description |
|-------|-----------|-------------|
| `task.started` | INFO | Task execution began |
| `task.completed` | INFO | Task finished successfully |
| `task.failed` | ERROR | Task execution failed |
| `api.error` | ERROR | API endpoint error |
| `auth.failed` | WARNING | Authentication failure |

## Health Checks

### Backend Health Endpoint

```http
GET /api/health

Response:
{
  "status": "healthy",
  "database": "connected",
  "celery": "connected"
}
```

### Kubernetes Probes

```yaml
# Current health probe configuration
livenessProbe:
  httpGet:
    path: /api/health
    port: 8000
  initialDelaySeconds: 30
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /api/health
    port: 8000
  initialDelaySeconds: 5
  periodSeconds: 5
```

## Future: Full Observability Stack

<!-- TODO: Expand when observability is implemented -->

### Planned Components (Q4 2025)

The following observability stack is planned but **not yet implemented**:

```
+------------------------------------------------------------------+
|                    Observability Stack (Planned)                  |
+------------------------------------------------------------------+
|                                                                   |
|  +-------------+    +-------------+    +-------------+            |
|  | Prometheus  |    |   Grafana   |    |   Jaeger    |           |
|  | (Metrics)   |--->| (Dashboards)|--->| (Tracing)   |           |
|  +-------------+    +-------------+    +-------------+            |
|        ^                                     ^                    |
|        |                                     |                    |
|  +-----+-------+                      +------+------+             |
|  |  Backend    |                      |  Backend    |             |
|  |  /metrics   |                      |  Trace IDs  |             |
|  +-------------+                      +-------------+             |
|                                                                   |
+------------------------------------------------------------------+
```

### Prometheus Metrics (Planned)

Metrics to be exposed:

| Metric | Type | Description |
|--------|------|-------------|
| `http_requests_total` | Counter | Total HTTP requests |
| `http_request_duration_seconds` | Histogram | Request latency |
| `celery_tasks_total` | Counter | Total Celery tasks |
| `celery_task_duration_seconds` | Histogram | Task execution time |
| `db_connections_active` | Gauge | Active database connections |

### Grafana Dashboards (Planned)

Planned dashboards:

1. **API Performance**: Request rates, latencies, error rates
2. **Celery Tasks**: Task throughput, queue depths, failure rates
3. **Database Health**: Connection pool, query times
4. **Business Metrics**: Companies created, tasks completed

### Distributed Tracing (Planned)

Using OpenTelemetry and Jaeger for:

- Request tracing across services
- Performance bottleneck identification
- Error correlation across components

## Current Monitoring Recommendations

### What to Monitor Today

1. **Celery Flower**
   - Check daily for failed tasks
   - Monitor queue depth during peak hours
   - Review task execution times

2. **Application Logs**
   - Set up log aggregation (even basic)
   - Alert on ERROR level logs
   - Track authentication failures

3. **Kubernetes Health**
   - Pod restart counts
   - Resource utilization (CPU, memory)
   - Liveness/readiness probe failures

### Alert Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Task failure rate | > 5% | > 10% |
| API error rate | > 1% | > 5% |
| Pod restarts (1h) | > 2 | > 5 |
| Celery queue depth | > 50 | > 100 |

## Troubleshooting Guide

### High Task Failure Rate

1. Check Flower for specific failing tasks
2. Review Celery worker logs for errors
3. Verify Dify API connectivity
4. Check database connection pool

### Slow API Responses

1. Check database query times in logs
2. Review Celery queue depth (backpressure)
3. Verify network latency to external services
4. Check for N+1 query patterns

### Memory Issues

1. Check for memory leaks in worker processes
2. Review large result set queries
3. Monitor Celery worker memory usage
4. Consider worker process recycling

## Related Documentation

- [Scalability](scalability.md): Scaling strategies
- [Caching](caching.md): Query optimization
- [Infrastructure View](../04-infrastructure/README.md): Deployment configuration
