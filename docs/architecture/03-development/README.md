# Development View

This section documents the software architecture patterns, development practices, and coding standards for the ChapsMind platform. It provides guidance for developers building and maintaining the system.

## Purpose

The Development View addresses:

- **Software Architecture**: How the frontend and backend are structured internally
- **Code Organization**: Directory layouts, naming conventions, and module boundaries
- **API Contracts**: How frontend and backend communicate
- **Quality Standards**: Coding standards, testing strategies, and development workflows

## Architecture Philosophy

### Layer Separation

ChapsMind follows a **clean architecture** approach with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────┐
│                     Presentation Layer                           │
│  - Vue.js components (pages, features, UI)                      │
│  - FastAPI endpoints (routes, middleware)                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Application Layer                           │
│  - Business logic (services)                                    │
│  - Use cases and workflows                                      │
│  - Data transformation (queries, mutations)                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Data Layer                                │
│  - Database models (SQLAlchemy)                                 │
│  - External service clients (Dify, Keycloak)                    │
│  - API client (frontend HTTP layer)                             │
└─────────────────────────────────────────────────────────────────┘
```

### Key Principles

1. **Thin Controllers/Endpoints**: API endpoints delegate to services; pages delegate to components
2. **Rich Domain Layer**: Business logic lives in services, not in routes or UI
3. **Dependency Injection**: Both FastAPI and Vue.js use DI patterns for testability
4. **Type Safety**: TypeScript on frontend, Python type hints on backend
5. **Separation of Server and Client State**: Server data managed separately from UI state

## Documentation Index

| Document | Description |
|----------|-------------|
| [Frontend Architecture](./frontend-architecture.md) | Vue.js application structure and patterns |
| [Backend Architecture](./backend-architecture.md) | FastAPI layer organization and service pattern |
| [API Contracts](./api-contracts.md) | API layer patterns and data fetching |
| [Coding Standards](./coding-standards.md) | Links to development conventions |
| [Testing Strategy](./testing-strategy.md) | Testing approach and tools |

## Quick Reference

### Frontend Stack

- **Framework**: Vue 3 with Composition API (`<script setup lang="ts">`)
- **State**: Pinia + Pinia Colada 
- **Routing**: File-based with `unplugin-vue-router`
- **Styling**: Tailwind CSS v4 with semantic color tokens

### Backend Stack

- **Framework**: FastAPI with async support
- **ORM**: SQLAlchemy 2.x
- **Validation**: Pydantic v2
- **Background Tasks**: Celery with RabbitMQ
- **Migrations**: Alembic

### Development Environment

- **Backend**: Runs in Docker (port 8000)
- **Frontend**: Vite dev server (port 3000)
- **Database**: PostgreSQL 16 in Docker
- **Auth**: Keycloak integration server

## Related Documentation

- [Application View Modules](../02-application/modules/) - Detailed module documentation
- [ADR Index](../adr/README.md) - Architecture decision records
- [Workspace CLAUDE.md](../../../CLAUDE.md) - Development guide and patterns
