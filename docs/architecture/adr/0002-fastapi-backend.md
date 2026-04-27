# ADR-0002: FastAPI Backend Framework

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** backend, framework, python, api

---

## Context

ChapsMind requires a backend framework to:

- Serve a RESTful API for the frontend SPA
- Handle asynchronous operations for external service calls (Dify, Keycloak)
- Integrate with PostgreSQL database using an ORM
- Support background task processing with Celery
- Provide automatic API documentation
- Validate request/response payloads
- Enable rapid development with type safety

The platform involves significant integration work with AI services (Dify) and identity management (Keycloak), requiring robust HTTP client support and async capabilities.

---

## Decision

We will use **FastAPI** as the backend framework with the following technology stack:

- **FastAPI**: Web framework for building APIs
- **Python 3.9+**: Runtime with type hints
- **SQLAlchemy 2.0+**: ORM with async support
- **Pydantic 2.0+**: Request/response validation
- **Uvicorn**: ASGI server
- **Alembic**: Database migrations
- **Celery + RabbitMQ**: Background task processing

Key implementation patterns:

- Service layer pattern for business logic
- Dependency injection for database sessions and authentication
- Pydantic models for all API contracts
- Async endpoints for I/O-bound operations

---

## Options Considered

### Option 1: FastAPI

**Description:** Modern Python web framework designed for building APIs with automatic OpenAPI documentation.

**Pros:**

- Automatic OpenAPI/Swagger documentation at `/docs`
- Native async/await support for I/O-bound operations
- Pydantic integration for automatic validation and serialization
- Dependency injection system for clean code organization
- Excellent TypeScript-like developer experience with type hints
- High performance (one of the fastest Python frameworks)
- Active development and growing community
- Easy integration with SQLAlchemy async

**Cons:**

- Relatively newer framework (less mature than Django)
- Smaller ecosystem of plugins compared to Django
- Less opinionated (requires more architectural decisions)
- Limited built-in features (no admin panel, ORM, etc.)

### Option 2: Django with Django REST Framework

**Description:** Full-featured Python web framework with Django REST Framework for API development.

**Pros:**

- Batteries included (admin panel, ORM, auth)
- Large ecosystem of packages
- Mature and battle-tested
- Excellent documentation
- Built-in security features

**Cons:**

- Synchronous by default (async support is improving but not native)
- Heavier framework with features we don't need
- Django ORM is less flexible than SQLAlchemy
- Slower development velocity for API-only applications
- OpenAPI documentation requires additional setup

### Option 3: Flask with Flask-RESTful

**Description:** Lightweight Python microframework with extensions for REST API development.

**Pros:**

- Lightweight and flexible
- Simple to understand and get started
- Large ecosystem of extensions
- Freedom to choose components

**Cons:**

- No built-in validation (requires extensions)
- No automatic API documentation
- Limited async support
- More boilerplate for type hints and validation
- Need to assemble many extensions for full functionality

---

## Consequences

### Positive

- **Automatic Documentation**: OpenAPI spec auto-generated at `/docs` and `/redoc` endpoints, eliminating manual documentation maintenance
- **Type Safety**: Pydantic validation catches errors at the API boundary, providing clear error messages
- **Async Performance**: Native async support enables efficient handling of external service calls (Dify, Keycloak)
- **Developer Experience**: Type hints provide IDE autocomplete and catch errors during development
- **Integration Ready**: Easy to integrate with Celery for background tasks via dependency injection
- **API-First Design**: Framework encourages clean API design with explicit request/response models

### Negative

- **Less Opinionated**: Team must make more architectural decisions (service patterns, folder structure)
- **No Admin Panel**: No built-in admin interface for data management (using PostgreSQL clients instead)
- **Plugin Ecosystem**: Fewer pre-built plugins than Django (some features require custom implementation)
- **Learning Curve**: Dependency injection and async patterns require understanding

### Neutral

- FastAPI's opinionated approach to validation may require adaptation from developers used to other frameworks
- The lack of a built-in ORM means using SQLAlchemy, which is powerful but has its own learning curve

---

## References

- [FastAPI Documentation](https://fastapi.tiangolo.com/)
- [Pydantic Documentation](https://docs.pydantic.dev/)
- [SQLAlchemy 2.0 Documentation](https://docs.sqlalchemy.org/)
- [FastAPI + SQLAlchemy Async Guide](https://fastapi.tiangolo.com/tutorial/sql-databases/)
- [ChapsMind Backend Standards](../../../agent-os/standards/backend/)
