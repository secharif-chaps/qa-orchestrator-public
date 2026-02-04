# Chapsmind Global Organization Service

A dedicated microservice to centralize organization-scoped resources (module configuration, unified token management, private folders with sharing) and enable a multi-module architecture where Screen, Target, and future services share common functionality. The service exposes both REST and gRPC interfaces, allowing module services to start with REST for rapid iteration and migrate to gRPC for performance optimization.

## Features

- Organization-scoped data management (CRUD operations)
- Unified resource management shared across multiple modules (Screen, Target, future services)
- REST API (FastAPI) for rapid development and frontend access
- gRPC API for high-performance service-to-service communication
- PostgreSQL database for data storage
- Alembic for database migrations
- SQLAlchemy ORM with connection pooling

## Interfaces

- **REST**: Exposed via FastAPI (OpenAPI / Swagger)
- **gRPC**: Exposed for internal service communication and future performance optimization

## Architecture

The application follows a Domain-Driven Design approach with the following components:

- **Domain Layer**: Core business logic and entities
- **Application Layer**: Orchestration of business operations
- **Infrastructure Layer**: Technical implementations (database, Dify)
- **API Layer**: External interface

## Installation

### Prerequisites

- Python 3.9+
- PostgreSQL database

### Setup

1. Clone the repository:
```bash
git clone https://github.com/yourusername/chapsmind-global-service.git
cd chapsmind-global-service
```

2. Create a virtual environment:
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

3. Install dependencies:
```bash
pip install -r requirements.txt
```

4. Configure environment variables in `.env` file:
```
DATABASE_URL=postgresql://postgres:postgres@localhost:5432/global_db
```

5. Run database migrations:
```bash
alembic upgrade head
```

6. Start the application:
```bash
uvicorn app.main:app --reload
```

## API Endpoints

### Organization Context Endpoint
- REST `GET /api/organization/context` returns enabled modules, organization settings for frontend startup
- Service-to-service endpoints (both REST and gRPC):
  - REST `GET /api/internal/organizations/{org_id}/context` / gRPC `GetOrganizationContext(organization_id)`
  - REST `GET /api/internal/organizations/{org_id}/modules/{module}/enabled` / gRPC `IsModuleEnabled(organization_id, module_name)`
- Organization data (name, ID) comes from JWT token, not stored in global DB
- Module enablement stored in `organization_modules` table (organization_id, module_name, enabled)

### Token Endpoints
- Service-to-service endpoints (both REST and gRPC):
  - REST `GET /api/internal/organizations/{org_id}/tokens/balance` / gRPC `GetTokenBalance(organization_id)` → returns balance
  - REST `POST /api/internal/tokens/consume` / gRPC `ConsumeTokens(organization_id, amount, reference_type, reference_id)` → deducts tokens
  - REST `POST /api/internal/organizations/{org_id}/tokens/add` / gRPC `AddTokens(organization_id, amount)` → admin adds tokens
- Database-level row locking (`SELECT FOR UPDATE`) prevents race conditions
- No reservation system - immediate consume with transaction logging
- REST `GET /api/organization/tokens/history` for admin viewing

### Private Folders
- Service-to-service endpoints (both REST and gRPC):
  - REST `POST /api/internal/folders` / gRPC `CreateFolder`
  - REST `GET /api/internal/users/{user_id}/folders` / gRPC `GetUserFolders` (owned + shared)
  - REST `GET /api/internal/folders/{id}` / gRPC `GetFolder` (with access check)
  - REST `GET /api/internal/folders/{id}/items` / gRPC `GetFolderItems`
  - REST `POST /api/internal/folders/{id}/items` / gRPC `AddItemToFolder` (owner/writer only)
  - REST `DELETE /api/internal/folders/{id}/items/{item_id}` / gRPC `RemoveItemFromFolder` (owner only)
  - REST `POST /api/internal/folders/{id}/shares` / gRPC `ShareFolder` (owner only)
  - REST `GET /api/internal/folders/{id}/access/{user_id}` / gRPC `CheckFolderAccess`

## Development

### Creating Migrations

To create a new migration after modifying models:

```bash
alembic revision --autogenerate -m "Description of changes"
```

### Running Tests

```bash
pytest
```
### Runing gRPC tests:
```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml  exec -e PYTHONPATH=/app global-service poetry run pytest tests/grpc/test_grpc_server.py -v
```
 
## License

This project is licensed under the MIT License. 