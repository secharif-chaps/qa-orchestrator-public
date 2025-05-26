# Mint Backend

A FastAPI backend for company data management and n8n workflow integration. This application follows Domain-Driven Design principles to provide a clean, maintainable architecture.

## Features

- Company data management (CRUD operations)
- Integration with n8n workflows for data collection
- Asynchronous processing of company data
- PostgreSQL database for data storage
- Alembic for database migrations

## Architecture

The application follows a Domain-Driven Design approach with the following components:

- **Domain Layer**: Core business logic and entities
- **Application Layer**: Orchestration of business operations
- **Infrastructure Layer**: Technical implementations (database, n8n)
- **API Layer**: External interface

## Installation

### Prerequisites

- Python 3.9+
- PostgreSQL database
- n8n instance running

### Setup

1. Clone the repository:
```bash
git clone https://github.com/yourusername/mint-back.git
cd mint-back
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
DATABASE_URL=postgresql://postgres:postgres@localhost:5432/mint_db
N8N_BASE_URL=http://your-n8n-instance:5678
N8N_WEBHOOK_ID=your-webhook-id
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

### Companies

- `GET /companies` - Get all companies
- `GET /companies/{company_id}` - Get company by ID
- `GET /companies/by-name/{name}` - Get company by name
- `POST /companies` - Create a new company
- `PUT /companies/{company_id}` - Update a company
- `DELETE /companies/{company_id}` - Delete a company
- `POST /companies/search` - Start a comprehensive search for company data
- `POST /companies/{company_id}/query/{query_type}` - Start a specific query for company data

### Webhooks

- `POST /webhooks/n8n/callback` - Endpoint for n8n to send workflow results

## n8n Integration

This application integrates with n8n workflows to collect data about companies. Each workflow is responsible for finding specific data (profile, team, products, etc.). The application provides two ways to interact with n8n:

1. **Search Endpoint**: Triggers all workflows for a company
2. **Query Endpoint**: Triggers a specific workflow for a company

When workflows complete, they should call the webhook endpoint to update the company data.

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

## License

This project is licensed under the MIT License. 