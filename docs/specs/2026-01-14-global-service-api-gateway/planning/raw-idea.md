# Raw Idea: Global Service API Gateway

## Feature Name
global-service-api-gateway

## Description

Transform the global-service into an API Gateway architecture where:

**Current State (v1):**
- Monolithic FastAPI backend (`back/`) with everything combined
- Frontend calls backend directly
- Global features (tokens, folders, organizations, user management) mixed with Screen module features (companies, tasks, Dify workflows)

**Target State (v2):**
- Frontend → Global Service (API Gateway) → Screen Service (internal)
- Global Service acts as single entry point handling:
  - JWT validation and permission checking
  - Request routing (REST and gRPC)
  - Shared services (tokens, folders, organizations, user management, preferences)
- Screen Service becomes internal-only, handling company screening features

**Key Decisions Made:**
1. Communication: Both REST and gRPC from day one
2. Database: Shared PostgreSQL with separate schemas (global_schema, screen_schema)
3. User Preferences: Move to Global Service
4. Folders: Global Service with generic item references {id, type} for future-proofing
5. Activities Feed: Global Service
6. Workflow Configuration: Screen Service (Dify-specific)

**Scope:**
- Detailed feature extraction mapping (which endpoints go where)
- Database schema migration plan
- API contract design for internal communication
- Big-bang migration approach (v2 replaces v1)

## Date Initiated
2026-01-14
