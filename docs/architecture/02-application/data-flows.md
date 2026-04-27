# Data Flows

This document describes the key data flows through the ChapsMind platform, with emphasis on how flows evolve from the current monolithic architecture to the planned modular architecture.

## Architecture Context

Data flows differ based on the architecture version:

| Version          | Description                                 |
| ---------------- | ------------------------------------------- |
| **Current (v1)** | Single FastAPI backend handles all requests |
| **Planned (v2)** | API Gateway routes to module services       |

## Company Creation Flow (Screen Module)

When a user creates a new company card, the system triggers data collection workflows via Dify.

### Current Flow (Monolithic)

```mermaid
sequenceDiagram
    participant User
    participant Frontend as Vue.js SPA
    participant API as FastAPI Backend<br/>(Global + Screen)
    participant DB as PostgreSQL
    participant Queue as RabbitMQ
    participant Worker as Celery Worker
    participant Dify as Dify Platform

    User->>Frontend: Submit company form
    Frontend->>API: POST /api/companies
    API->>API: Validate JWT (Keycloak)
    API->>API: Validate request (Pydantic)
    API->>DB: Insert company record
    API->>Queue: Enqueue datacollector task
    API->>Frontend: Return company (201 Created)
    Frontend->>User: Show company card

    Note over Worker,Dify: Background Processing (Screen Module)

    Worker->>Queue: Consume task
    Worker->>Dify: Execute datacollector workflow
    Dify->>Dify: Gather raw data from sources
    Dify->>Worker: Return raw data
    Worker->>DB: Update company with data
    Worker->>Queue: Enqueue section tasks (jobs, products, etc.)

    loop For Each Section
        Worker->>Queue: Consume section task
        Worker->>Dify: Execute section workflow
        Dify->>Worker: Return processed data
        Worker->>DB: Update company section
    end
```

### Planned Flow (API Gateway + Screen Service)

```mermaid
sequenceDiagram
    participant User
    participant Frontend as Vue.js SPA
    participant Gateway as API Gateway
    participant Screen as Screen Service
    participant DB as PostgreSQL
    participant Queue as RabbitMQ
    participant Worker as Screen Workers
    participant Dify as Dify Platform

    User->>Frontend: Submit company form
    Frontend->>Gateway: POST /api/companies
    Gateway->>Gateway: Validate JWT (Keycloak)
    Gateway->>Screen: Route request (gRPC)
    Screen->>Screen: Validate request (Pydantic)
    Screen->>DB: Insert company record
    Screen->>Queue: Enqueue datacollector task
    Screen->>Gateway: Return company
    Gateway->>Frontend: Return company (201 Created)
    Frontend->>User: Show company card

    Note over Worker,Dify: Background Processing (Screen Module)

    Worker->>Queue: Consume task
    Worker->>Dify: Execute datacollector workflow
    Dify->>Worker: Return raw data
    Worker->>DB: Update company with data

    loop For Each Section
        Worker->>Queue: Consume section task
        Worker->>Dify: Execute section workflow
        Worker->>DB: Update company section
    end
```

## Task Execution Flow (Screen Module)

Tasks are processed asynchronously through Celery with Dify workflows.

### Current Flow

```mermaid
sequenceDiagram
    participant API as FastAPI Backend
    participant Queue as RabbitMQ
    participant Worker as Celery Worker
    participant Dify as Dify Platform
    participant DB as PostgreSQL

    API->>Queue: Publish task message
    Note over API: Non-blocking, returns immediately

    Worker->>Queue: Poll for tasks
    Queue->>Worker: Deliver task

    Worker->>DB: Update task status = "running"
    Worker->>Dify: Call workflow API

    alt Workflow Success
        Dify->>Worker: Return result
        Worker->>DB: Store result
        Worker->>DB: Update task status = "completed"
    else Workflow Failure
        Dify->>Worker: Return error
        Worker->>DB: Update task status = "failed"
        Worker->>Worker: Schedule retry (if retries remaining)
    end
```

## Global Services Flows

### Folder Operations (Global Service)

Folder operations are handled by Global Services and apply to all modules:

```mermaid
sequenceDiagram
    participant User
    participant Frontend as Vue.js SPA
    participant API as FastAPI Backend<br/>(Global Services)
    participant Keycloak
    participant DB as PostgreSQL

    User->>Frontend: Create folder
    Frontend->>API: POST /api/folders
    API->>Keycloak: Validate JWT
    API->>API: Extract organization_id from token
    API->>DB: Insert folder (organization-scoped)
    API->>Frontend: Return folder
    Frontend->>User: Show new folder
```

### Organization Token Usage (Global Service)

Token tracking is a global service used by all modules:

```mermaid
sequenceDiagram
    participant Module as Module Service<br/>(Screen, Target, etc.)
    participant Global as Global Services
    participant DB as PostgreSQL

    Module->>Global: Report token usage
    Global->>DB: Update organization token balance
    Global->>DB: Log usage record
    Global->>Module: Confirm recorded

    alt Token Limit Exceeded
        Global->>Module: Return limit error
        Module->>Module: Block operation
    end
```

## Authentication Flow

Authentication is a global service. See [Authentication](../05-security/authentication.md) for the detailed OIDC flow.

### Summary Flow

```mermaid
sequenceDiagram
    participant User
    participant Frontend as Vue.js SPA
    participant Keycloak
    participant Gateway as API Gateway / Backend

    User->>Frontend: Click Login
    Frontend->>Keycloak: OIDC Authorization Request
    Keycloak->>User: Login Form
    User->>Keycloak: Credentials
    Keycloak->>Frontend: JWT Tokens
    Frontend->>Gateway: API Request + Bearer Token
    Gateway->>Keycloak: Validate Token
    Keycloak->>Gateway: Token Valid + Claims
    Gateway->>Gateway: Check Permissions
    Gateway->>Frontend: Response
```

## Related Documentation

- [Authentication Flow](../05-security/authentication.md) - Detailed OIDC sequence diagrams
- [Screen AI Orchestration](./modules/screen/ai-orchestration.md) - Dify workflow details
- [Global Services](./modules/global-services/) - Folder, token, sharing services
- [Containers](./containers.md) - Architecture evolution diagrams
