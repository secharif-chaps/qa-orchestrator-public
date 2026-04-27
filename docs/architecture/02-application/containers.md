# Container Architecture

This document describes the container-level architecture of ChapsMind, showing the major deployment units and their interactions.

## Architecture Evolution

ChapsMind is evolving from a monolithic architecture to a modular service-based architecture.

### Current Architecture (v1 - Monolithic)

Everything runs in a single FastAPI backend with Global Services and Screen module combined:

```mermaid
C4Container
    title ChapsMind Container Architecture (Current)

    Person(user, "User", "Intelligence professional")

    Container_Boundary(frontend_tier, "Frontend Tier") {
        Container(spa, "Vue.js SPA", "Vue 3, TypeScript", "Unified frontend for all modules")
    }

    Container_Boundary(backend_tier, "Backend Tier") {
        Container(api, "FastAPI Backend", "Python, FastAPI", "Global Services + Screen Module combined")
        Container(celery, "Celery Workers", "Python, Celery", "Background task processing")
        Container(flower, "Celery Flower", "Python", "Task monitoring dashboard")
    }

    Container_Boundary(data_tier, "Data Tier") {
        ContainerDb(postgres, "PostgreSQL", "PostgreSQL 16", "Primary relational database")
        ContainerQueue(rabbitmq, "RabbitMQ", "RabbitMQ", "Message broker for async tasks")
    }

    Container_Boundary(external, "External Services") {
        System_Ext(keycloak, "Keycloak", "Identity Provider")
        System_Ext(dify, "Dify", "AI Platform (Screen workflows)")
    }

    Rel(user, spa, "Uses", "HTTPS")
    Rel(spa, api, "API calls", "REST/JSON")
    Rel(spa, keycloak, "Authentication", "OIDC")

    Rel(api, postgres, "Reads/Writes", "SQL")
    Rel(api, rabbitmq, "Publishes tasks", "AMQP")
    Rel(api, keycloak, "Validates JWT", "OIDC")

    Rel(celery, rabbitmq, "Consumes tasks", "AMQP")
    Rel(celery, postgres, "Updates results", "SQL")
    Rel(celery, dify, "Screen AI workflows", "REST")

    Rel(flower, rabbitmq, "Monitors", "AMQP")
```

### Planned Architecture (v2 - Global Service as Gateway)

Global Service acts as both API Gateway and shared services. Module services are internal only.

**Key Decision**: No separate API Gateway (Kong/Traefik) - Global Service IS the gateway. See [ADR-0009](../adr/0009-global-service-architecture.md).

```mermaid
C4Container
    title ChapsMind Container Architecture (Planned)

    Person(user, "User", "Intelligence professional")

    Container_Boundary(frontend_tier, "Frontend Tier") {
        Container(spa, "Vue.js SPA", "Vue 3, TypeScript", "Unified frontend for all modules")
    }

    Container_Boundary(global_tier, "Global Service (Internet-Facing)") {
        Container(global, "Global Service", "Python, FastAPI", "Gateway + Auth + Folders + Tokens + Orgs")
    }

    Container_Boundary(module_tier, "Internal Module Services") {
        Container(screen, "Screen Service", "Python, FastAPI", "Company screening (internal)")
        Container(screen_worker, "Screen Workers", "Python, Celery", "Screen background tasks")
    }

    Container_Boundary(data_tier, "Data Tier") {
        ContainerDb(postgres, "PostgreSQL", "PostgreSQL 16", "Shared database")
        ContainerQueue(rabbitmq, "RabbitMQ", "RabbitMQ", "Message broker")
    }

    Container_Boundary(external, "External Services") {
        System_Ext(keycloak, "Keycloak", "Identity Provider")
        System_Ext(dify, "Dify", "AI Platform (Screen)")
    }

    Rel(user, spa, "Uses", "HTTPS")
    Rel(spa, global, "All API calls", "REST/JSON")
    Rel(spa, keycloak, "Authentication", "OIDC")

    Rel(global, keycloak, "Validates JWT", "OIDC")
    Rel(global, screen, "Internal routing", "REST/gRPC")
    Rel(global, postgres, "Global data", "SQL")

    Rel(screen, postgres, "Screen data", "SQL")
    Rel(screen, rabbitmq, "Publishes tasks", "AMQP")

    Rel(screen_worker, rabbitmq, "Consumes tasks", "AMQP")
    Rel(screen_worker, dify, "AI workflows", "REST")
```

### Future Architecture (v3 - Multiple Modules)

Multiple internal module services, all accessed through Global Service:

```mermaid
C4Container
    title ChapsMind Container Architecture (Future)

    Person(user, "User", "Intelligence professional")

    Container_Boundary(frontend_tier, "Frontend Tier") {
        Container(spa, "Vue.js SPA", "Vue 3, TypeScript", "Unified frontend for all modules")
    }

    Container_Boundary(global_tier, "Global Service (Internet-Facing)") {
        Container(global, "Global Service", "Python, FastAPI", "Gateway + Shared Services")
    }

    Container_Boundary(module_tier, "Internal Module Services") {
        Container(screen, "Screen Service", "Python, FastAPI", "Company screening (internal)")
        Container(target, "Target Service", "Python, FastAPI", "Watchfiles monitoring (internal)")
    }

    Container_Boundary(data_tier, "Data Tier") {
        ContainerDb(postgres, "PostgreSQL", "PostgreSQL 16", "Databases")
        ContainerQueue(rabbitmq, "RabbitMQ", "RabbitMQ", "Message broker")
    }

    Container_Boundary(external, "External Services") {
        System_Ext(keycloak, "Keycloak", "Identity Provider")
        System_Ext(dify, "Dify", "Screen AI Platform")
        System_Ext(n8n, "n8n", "Target Workflow Automation")
    }

    Rel(user, spa, "Uses", "HTTPS")
    Rel(spa, global, "All API calls", "REST/JSON")
    Rel(spa, keycloak, "Authentication", "OIDC")

    Rel(global, keycloak, "Validates JWT", "OIDC")
    Rel(global, screen, "Internal", "REST/gRPC")
    Rel(global, target, "Internal", "REST/gRPC")

    Rel(screen, dify, "AI workflows", "REST")
    Rel(target, n8n, "Workflow automation", "REST")
```

## Containers Overview

### Current Containers

| Container           | Technology                      | Purpose                             |
| ------------------- | ------------------------------- | ----------------------------------- |
| **Vue.js SPA**      | Vue 3, TypeScript, Pinia Colada | Unified user interface              |
| **FastAPI Backend** | Python, FastAPI, SQLAlchemy     | REST API (Global + Screen combined) |
| **Celery Workers**  | Python, Celery                  | Background processing               |
| **Celery Flower**   | Python                          | Task monitoring                     |
| **PostgreSQL**      | PostgreSQL 16                   | Primary database                    |
| **RabbitMQ**        | RabbitMQ                        | Message broker                      |

### Planned Containers

| Container          | Technology        | Purpose                                     |
| ------------------ | ----------------- | ------------------------------------------- |
| **Vue.js SPA**     | Vue 3, TypeScript | Unified frontend                            |
| **Global Service** | Python, FastAPI   | Gateway + Shared Services (internet-facing) |
| **Screen Service** | Python, FastAPI   | Company screening module (internal)         |
| **Screen Workers** | Python, Celery    | Screen background tasks                     |
| **PostgreSQL**     | PostgreSQL 16     | Shared database                             |
| **RabbitMQ**       | RabbitMQ          | Message broker                              |

## Deployment Ports

| Service        | Port        | Environment           |
| -------------- | ----------- | --------------------- |
| Frontend SPA   | 3000        | Development           |
| Global Service | 8000        | All (internet-facing) |
| Screen Service | 8001        | All (internal only)   |
| PostgreSQL     | 5432        | All                   |
| RabbitMQ       | 5672, 15672 | All                   |
| Celery Flower  | 5555        | All                   |

## Related Documentation

- [Modules Overview](./modules/) - Module architecture philosophy
- [Global Services](./modules/global-services/) - Global Service (gateway + shared services)
- [Screen Module](./modules/screen/) - Company screening module
- [Frontend Module](./modules/frontend.md) - Vue.js architecture details
- [Data Flows](./data-flows.md) - Request/response flows
