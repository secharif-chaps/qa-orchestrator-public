# ADR-0009: Global Service Architecture

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** backend, architecture, microservices, api-gateway

---

## Context

ChapsMind is evolving from a single-module application (Screen) to a multi-module platform supporting Screen, Target, Explore, Stream, and future modules. This evolution creates challenges:

- **Shared Functionality**: Multiple modules need common services (token management, folders, organization settings)
- **Code Duplication**: Without shared services, each module would duplicate common logic
- **Independent Scaling**: Different modules have different resource requirements
- **Team Autonomy**: Multiple teams should be able to work on modules independently
- **Deployment Flexibility**: Modules should be deployable independently for faster iteration

Current state (monolith):

- Single FastAPI backend (`mint-server`) serving all functionality
- Single PostgreSQL database (`mint_db`)
- Tightly coupled code between concerns
- Works well for current scale but limits future growth

Target state requirements:

- Support multiple frontend modules (Screen, Target, Explore)
- Share common resources (tokens, folders) across modules
- Enable independent module development and deployment
- Maintain simple architecture where possible

---

## Decision

We will implement a **simplified service architecture** where the **Global Service acts as both API Gateway and shared services layer**. This avoids microservices complexity while enabling module separation.

### Architecture Overview

```text
                    ┌─────────────────────────────────────────┐
                    │              Frontend SPA               │
                    │     (Screen, Target, Explore views)     │
                    └──────────────────┬──────────────────────┘
                                       │ REST (all requests)
                                       ▼
                    ┌─────────────────────────────────────────┐
                    │         Global Service (API Gateway)    │
                    │  ┌───────────────────────────────────┐  │
                    │  │  Gateway Layer                    │  │
                    │  │  - JWT validation (Keycloak)      │  │
                    │  │  - Request routing                │  │
                    │  │  - Rate limiting                  │  │
                    │  └───────────────────────────────────┘  │
                    │  ┌───────────────────────────────────┐  │
                    │  │  Shared Services                  │  │
                    │  │  - Token management               │  │
                    │  │  - Folders & sharing              │  │
                    │  │  - Organization settings          │  │
                    │  │  - User management (Keycloak)     │  │
                    │  └───────────────────────────────────┘  │
                    └──────────┬─────────────────┬────────────┘
                               │                 │
                    Internal   │                 │  Internal
                    REST/gRPC  │                 │  REST/gRPC
                               ▼                 ▼
                    ┌─────────────────┐ ┌─────────────────┐
                    │  Screen Module  │ │  Target Module  │
                    │   (mint-api)    │ │  (target-api)   │
                    └────────┬────────┘ └────────┬────────┘
                             │                   │
                             ▼                   ▼
                       ┌──────────┐        ┌──────────┐
                       │screen_db │        │target_db │
                       └──────────┘        └──────────┘
```

### Why Not Separate API Gateway + Global Service?

| Separate Gateway                        | Global Service as Gateway |
| --------------------------------------- | ------------------------- |
| Additional service to deploy/maintain   | Single entry point        |
| Extra network hop                       | Direct routing            |
| Configuration complexity (Kong/Traefik) | Built-in FastAPI routing  |
| Overkill for current scale              | Right-sized for team      |
| Microservices overhead                  | Pragmatic simplicity      |

### Key Implementation Decisions

1. **Global Service = Single Entry Point**: All frontend requests go through Global Service
   - Handles JWT validation centrally
   - Routes to module services internally
   - Provides shared functionality directly

2. **Shared Services Built-In**: Global Service owns organization-scoped resources:
   - Token management (global balance per organization)
   - Private folders with sharing
   - Organization module enablement
   - User management via Keycloak Admin API
   - Future: Organization settings, user preferences

3. **Internal Communication**: Global Service communicates with modules via:
   - **REST**: Default for simplicity
   - **gRPC**: Optional for performance-critical paths (future)

4. **Module Services are Internal**: Screen and Target services are NOT exposed directly
   - Only Global Service is internet-facing
   - Modules communicate through Global Service
   - Simplifies security (single point of authentication)

5. **Database per Service**: Each service owns its database
   - `global_db`: Token balances, transactions, folders, shares, org settings
   - `screen_db`: Companies, tasks, company data (current `mint_db`)
   - `target_db`: Watchfiles, alerts, monitoring configuration

6. **Incremental Migration**: Extract services gradually
   - Phase 1: Global Service foundation (gateway + org context)
   - Phase 2: Token system migration
   - Phase 3: Folder system migration
   - Phase 4: Screen module extraction (current monolith becomes module)
   - Phase 5: Target module implementation

---

## Options Considered

### Option 1: Global Service as Gateway (Chosen)

**Description:** Global Service acts as both API Gateway and shared services layer. Module services (Screen, Target) are internal and only accessible through Global Service.

**Pros:**

- **Single Entry Point**: One service handles auth, routing, and shared functionality
- **Simplified Operations**: Fewer services to deploy and monitor
- **No Extra Network Hop**: Direct routing without separate gateway
- **Right-Sized**: Appropriate complexity for current team and scale
- **Security Simplified**: Single point of authentication/authorization
- **Incremental Migration**: Can extract modules gradually

**Cons:**

- Global Service becomes critical path (must be highly available)
- Global Service handles more responsibility
- Module services cannot be accessed directly (by design)

### Option 2: Separate API Gateway + Services

**Description:** Deploy dedicated API Gateway (Kong/Traefik) in front of all services including Global Service.

**Pros:**

- Industry-standard gateway features (rate limiting, caching, etc.)
- Services are more independent
- Gateway can be managed separately

**Cons:**

- Additional service to deploy and maintain
- Extra network hop for all requests
- Configuration complexity (gateway rules, service discovery)
- Overkill for current scale
- **Rejected**: Added complexity without proportional benefit

### Option 3: Modular Monolith

**Description:** Keep single backend but organize code into well-defined modules with clear boundaries.

**Pros:**

- Simpler deployment (single artifact)
- No network latency between modules
- Easier debugging and tracing
- Lower operational overhead

**Cons:**

- Cannot scale modules independently
- Single point of failure for entire application
- Teams must coordinate all deployments
- Shared codebase leads to coupling over time
- **Rejected**: Doesn't support multi-module product vision

### Option 4: Full Microservices

**Description:** Extract every domain into its own service with event-driven communication.

**Pros:**

- Maximum independence between services
- True independent scaling
- Clear bounded contexts

**Cons:**

- Massive upfront investment
- Operational complexity explosion
- Distributed transactions nightmare
- Premature optimization for current team size
- **Rejected**: Over-engineering for current scale

---

## Consequences

### Positive

- **Simplified Architecture**: No separate API Gateway to manage
- **Single Entry Point**: All auth and routing in one place
- **Shared Resources**: Token management, folders work across all modules automatically
- **Clear Boundaries**: Global Service owns shared data, modules own domain data
- **Security**: Only Global Service is internet-facing, modules are internal
- **Incremental Path**: Can extract modules as team and product grow
- **Right-Sized Complexity**: Avoids microservices overhead while enabling modularity

### Negative

- **Global Service is Critical**: Must ensure high availability (it's the single entry point)
- **Global Service Responsibility**: Handles gateway + shared services (more code in one service)
- **Internal Network Calls**: Module communication adds some latency
- **Testing**: Integration testing across Global Service and modules required

### Neutral

- This is a pragmatic middle-ground between monolith and microservices
- Current monolith continues working until migration is complete
- Can evolve to separate gateway later if scale demands it
- Infrastructure costs increase moderately (fewer services than full microservices)

---

## Implementation Notes

### Global Service as Gateway

The Global Service handles two responsibilities:

#### 1. Gateway Layer (All Requests)

```text
Frontend Request
      │
      ▼
┌─────────────────────────────────────────────┐
│  Global Service                             │
│  ┌────────────────────────────────────────┐ │
│  │ 1. JWT Validation (Keycloak)           │ │
│  │ 2. Extract org_id from token           │ │
│  │ 3. Route based on path prefix          │ │
│  └────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
      │
      ├── /api/screen/*  → Forward to Screen Module
      ├── /api/target/*  → Forward to Target Module
      └── /api/*         → Handle directly (shared services)
```

#### 2. Shared Services (Direct Handling)

- `/api/tokens/*` - Token management
- `/api/folders/*` - Folder management
- `/api/organizations/*` - Organization settings
- `/api/admin/*` - Admin operations (Keycloak)

### Public Endpoints (Frontend-Facing)

All endpoints go through Global Service:

```http
# Shared Services (handled by Global Service)
GET   /api/organization/context
GET   /api/tokens/balance
POST  /api/tokens/consume
GET   /api/folders
POST  /api/folders

# Screen Module (proxied to Screen Service)
GET   /api/screen/companies
POST  /api/screen/companies
GET   /api/screen/companies/{id}

# Target Module (proxied to Target Service)
GET   /api/target/watchfiles
POST  /api/target/watchfiles
```

### Internal Endpoints (Module-to-Global)

Modules call Global Service for shared operations:

```http
# Internal API (not exposed to frontend)
GET   /api/internal/organizations/{org_id}/tokens/balance
POST  /api/internal/tokens/consume
GET   /api/internal/users/{user_id}/folders
```

### Request Routing Pattern

```python
# Global Service routing (FastAPI)
from fastapi import APIRouter, Request
import httpx

# Direct handlers for shared services
router.include_router(tokens_router, prefix="/api/tokens")
router.include_router(folders_router, prefix="/api/folders")
router.include_router(admin_router, prefix="/api/admin")

# Proxy to module services
@router.api_route("/api/screen/{path:path}", methods=["GET", "POST", "PUT", "DELETE"])
async def proxy_to_screen(request: Request, path: str):
    """Forward request to Screen Module service."""
    async with httpx.AsyncClient() as client:
        response = await client.request(
            method=request.method,
            url=f"{SCREEN_SERVICE_URL}/api/{path}",
            headers={"X-Organization-Id": request.state.organization_id},
            content=await request.body(),
        )
        return Response(content=response.content, status_code=response.status_code)
```

### Module Service Authentication

Module services trust requests from Global Service (internal network):

```python
# Screen Module - trusts X-Organization-Id header from Global Service
@router.get("/api/companies")
async def list_companies(
    organization_id: str = Header(..., alias="X-Organization-Id")
):
    """List companies for organization (called by Global Service)."""
    return await company_service.list_by_organization(organization_id)
```

### Database Schema Pattern

```sql
-- Global Service: global_db
CREATE TABLE organizations (
    organization_id VARCHAR(36) PRIMARY KEY,  -- Keycloak org ID
    token_balance INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE token_transactions (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(36) REFERENCES organizations,
    amount INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    transaction_type VARCHAR(20),  -- add, consume, adjustment
    reference_type VARCHAR(50),    -- company, csv_import, manual
    reference_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW(),
    created_by VARCHAR(36)         -- Keycloak user ID
);

CREATE TABLE folders (
    id SERIAL PRIMARY KEY,
    organization_id VARCHAR(36) NOT NULL,
    owner_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    -- ... folder fields
);

-- Screen Service: screen_db (current mint_db)
-- Keeps companies, tasks, company_data tables
-- References global token system via API calls
```

### Service Extraction Roadmap

| Phase | Service                   | Timeline   | Description                             |
| ----- | ------------------------- | ---------- | --------------------------------------- |
| 1     | Global Service Foundation | Q1 2025    | Organization context, module enablement |
| 2     | Token Migration           | Q1 2025    | Move token tables to Global Service     |
| 3     | Folder Migration          | Q2 2025    | Move folder tables with sharing         |
| 4     | Screen Service            | Q2 2025    | Extract from monolith                   |
| 5     | Target Service            | Q2-Q3 2025 | New service for Target module           |

---

## Ongoing Work

This architecture is being implemented incrementally. See detailed specifications:

- [Global Organization Service Spec](../../../agent-os/specs/global-organization-service/spec.md)
- [Global Token System Spec](../../../agent-os/specs/2025-12-16-global-token-system/spec.md)
- [Private Folders Sharing Spec](../../../agent-os/specs/private-folders-sharing/spec.md)
- [Product Roadmap](../../../agent-os/product/roadmap.md)

---

## References

- [ADR-0002: FastAPI Backend](./0002-fastapi-backend.md)
- [gRPC Documentation](https://grpc.io/docs/)
- [Microservices Patterns (Chris Richardson)](https://microservices.io/patterns/)
- [API Gateway Pattern](https://microservices.io/patterns/apigateway.html)
- [ChapsMind Product Roadmap](../../../agent-os/product/roadmap.md)
