# ChapsMind Screen Backend

FastAPI backend service for the Screen module (company monitoring).

## Stack

- **Framework**: FastAPI (async)
- **Language**: Python 3.9+
- **ORM**: SQLAlchemy 2.0 (async with asyncpg)
- **Migrations**: Alembic
- **Task Queue**: Celery + RabbitMQ
- **Auth**: Keycloak (fastapi-keycloak)
- **AI**: Dify workflows

## Commands

```bash
task screen:lint      # Lint with ruff
task screen:format    # Format with ruff
task screen:test      # Run pytest
task screen:shell     # Open bash in container
task migrate          # Run Alembic migrations
task migrate:status   # Show current migration
task seed             # Seed sample data
task db:shell         # Open psql
```

## Structure

```
app/
├── api/              # FastAPI endpoints
├── models/           # SQLAlchemy models
├── schemas/          # Pydantic schemas
├── services/         # Business logic
└── core/             # Configuration, auth, database
```