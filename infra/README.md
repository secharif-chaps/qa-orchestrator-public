# ChapsMind Infrastructure

Docker Compose configurations, Kubernetes manifests, and deployment scripts.

## Commands

```bash
task up           # Start all services
task down         # Stop all services
task restart      # Restart all services
task logs         # Tail all logs
```

## Services

| Service | Port | Description |
|---|---|---|
| frontend | 3000 | Vue.js application |
| screen | 8000 | Screen backend (FastAPI) |
| global-service | - | Global service |
| keycloak | 8080 | Authentication (integration server) |
| db | 5432 | PostgreSQL |
| rabbitmq | 5672, 15672 | Message broker |
| celery worker | - | Background task processor |
| flower | 5555 | Celery monitoring |