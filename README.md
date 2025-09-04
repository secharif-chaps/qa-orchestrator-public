# Mint Server - Company Data Intelligence Platform

A FastAPI backend service that finds and aggregates comprehensive data about companies online using web scraping, AI workflows, and automated data collection systems.

## 🎯 What It Does

Mint Server is a company research platform that automatically discovers and collects:

- **Company profiles**: Basic info, descriptions, and business details
- **Digital presence**: Websites, social media, online footprint
- **Team information**: Key employees, leadership, organizational structure
- **Product portfolios**: Services, solutions, and offerings
- **Corporate timeline**: Company history, milestones, news
- **CSR initiatives**: Sustainability efforts, social responsibility
- **Press coverage**: News articles, media mentions
- **Job listings**: Current openings, hiring trends

All data is collected through automated workflows, AI analysis, and intelligent web scraping.

## 🏗️ Architecture

- **FastAPI Backend**: RESTful API with async processing
- **PostgreSQL Database**: Company data storage and relationships
- **Keycloak Authentication**: User management and permissions
- **Dify AI Workflows**: Intelligent data processing and chat
- **RabbitMQ Message Broker**: Task queue for async workflow execution
- **Celery Workers**: Distributed task processing with concurrency control
- **Flower Dashboard**: Real-time monitoring of task queues and workers
- **Docker**: Containerized deployment

## 🚀 Quick Start

### Development Environment

1. **Clone and setup**:
   ```bash
   git clone <repository>
   cd mint-server
   ```

2. **Start services**:
   ```bash
   docker compose -f docker-compose.dev.yml up -d
   ```

3. **Create initial user**:
   ```bash
   ./create_initial_user.sh
   ```

4. **Set up local Dify callbacks** (for AI workflow testing):
   ```bash
   # Install ngrok
   brew install ngrok
   
   # Sign up and get authtoken from https://dashboard.ngrok.com/signup
   ngrok config add-authtoken YOUR_TOKEN
   
   # Start ngrok tunnel
   ngrok http 8000
   
   # Update .env.dev with your ngrok URL (note: use .env.dev, not .env)
   echo "BACKEND_BASE_URL=https://your-ngrok-url.ngrok-free.app" >> .env.dev
   echo "ENVIRONMENT=development" >> .env.dev
   
   # Restart backend to load new configuration
   docker compose -f docker-compose.dev.yml restart backend
   ```

5. **Access the application**:
   - API Documentation: http://localhost:8000/docs
   - Keycloak Admin: http://localhost:8080 (admin/admin)
   - Database: localhost:5432 (postgres/postgres)
   - RabbitMQ Management: http://localhost:15672 (guest/guest)
   - Flower Dashboard: http://localhost:5555 (admin/admin)

### Local Dify Callback Setup

For testing AI workflows locally, you need to expose your local backend to the internet so Dify can send callbacks:

#### Prerequisites
1. **Install ngrok**: `brew install ngrok`
2. **Sign up**: https://dashboard.ngrok.com/signup
3. **Get authtoken**: https://dashboard.ngrok.com/get-started/your-authtoken

#### Setup Process
```bash
# Configure ngrok with your authtoken
ngrok config add-authtoken YOUR_AUTHTOKEN

# Start ngrok tunnel (keep this running)
ngrok http 8000

# Copy the HTTPS URL (e.g., https://abc123.ngrok-free.app)
# Update your environment configuration in .env.dev (not .env)
echo "BACKEND_BASE_URL=https://your-ngrok-url.ngrok-free.app" >> .env.dev
echo "ENVIRONMENT=development" >> .env.dev

# Restart backend to load new configuration
docker compose -f docker-compose.dev.yml restart backend
```

#### Testing Callbacks
```bash
# Get authentication token
python3 get_token.py

# Create a test task (will use ngrok callback URL)
curl -X POST http://localhost:8000/api/tasks/ \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"company_id": 1, "type": "profile"}'

# Check logs for callback activity
docker compose -f docker-compose.dev.yml logs backend | grep callback
```

#### Important Notes
- Keep ngrok running while testing workflows
- Use `.env.dev` file (Docker Compose uses this, not `.env`)
- Callback URLs will be: `https://your-ngrok-url.ngrok-free.app/api/webhooks/dify/tasks/{task_id}/callback`
- Without ngrok, tasks will stay in "running" state as callbacks can't reach localhost
- The default `BACKEND_BASE_URL` in `config.py` is set for local development and gets overridden by environment variables

### Test Users

Create test users with different permission levels:
```bash
./create_test_users.sh
```

Available test accounts:
- `admin` / `admin123` - Full system access
- `company_manager` / `manager123` - Full company management
- `company_creator` / `creator123` - Create and view companies
- `company_viewer` / `viewer123` - Read-only access

## 🔧 API Features

### Company Management
- **GET** `/api/companies` - List companies with pagination
- **POST** `/api/companies` - Create/search new companies
- **GET** `/api/companies/{id}` - Get company details
- **PUT** `/api/companies/{id}` - Update company data
- **DELETE** `/api/companies/{id}` - Remove companies

### AI Chat
- **POST** `/api/companies/{id}/chatbot` - Chat about company data

### Task Management
- **GET** `/api/tasks` - View data collection tasks
- **POST** `/api/tasks/{id}/execute` - Trigger data collection
- **GET** `/api/concurrency/status` - View task queue and worker status
- **POST** `/api/concurrency/cleanup` - Clean up stuck tasks

### User & Workspace Management
- **GET** `/api/workspaces/{id}/users` - Manage team members
- **PATCH** `/api/workspaces/{id}/users/{user_id}` - Update permissions

## 🌍 Environments

### Development
```bash
docker compose -f docker-compose.dev.yml up -d
```
- Local development with hot reload
- Debug logging enabled
- Exposed database port

### Preprod (VPN Required)
```bash
./deploy-preprod.sh
```
- **Server**: 10.0.1.2 (behind VPN)
- **Dify Workflows**: 10.0.1.1
- Production-like environment for testing

## 📝 Scripts Reference

### Deployment
- `./deploy-preprod.sh` - Deploy to preprod server (10.0.1.2)

### User Management
- `./create_initial_user.sh` - Create admin user (nmr)
- `./create_test_users.sh` - Create multiple test users with different permissions

### Monitoring
- `./logs.sh` - View production logs
- `./logs.sh backend` - Backend logs only
- `./logs.sh status` - Service status
- `./logs.sh backend -f` - Real-time backend logs

## 🔐 Authentication & Permissions

### Permission System
- **workspace.read/write** - Workspace access and team management
- **company.view** - View company data
- **company.create** - Search and create companies
- **company.update** - Edit existing companies
- **company.delete** - Remove companies
- **admin.workspaces** - Global workspace administration

### Keycloak Configuration
- **Development Realm**: mint-dev
- **Default Admin**: admin/admin
- **JWT Authentication**: RS256 with realm roles

## 🤖 AI Integration

### Dify Workflows
- **Data Collection**: Automated scraping and analysis
- **Company Chat**: AI-powered conversations about company data
- **Workflow Server**: 10.0.1.1 (production)

### Supported Tasks
- Profile generation
- Team analysis
- Product discovery
- Timeline construction
- Press monitoring
- CSR assessment

## ⚙️ Configuration

### Environment Variables

**Database**:
- `DATABASE_URL`: PostgreSQL connection
- `DB_PASSWORD`: Database password (preprod)

**Dify Integration**:
- `DIFY_URL`: Workflow server URL
- `DIFY_API_KEY`: Workflow API access
- `DIFY_TIMELINE_API_KEY`: Timeline workflow key

**Task Queue**:
- `RABBITMQ_URL`: Message broker connection (default: amqp://guest:guest@rabbitmq:5672//)
- `MAX_CONCURRENT_WORKFLOWS`: Maximum parallel workflow executions (default: 10)

**Keycloak**:
- `KEYCLOAK_SERVER_URL`: Auth server URL
- `KEYCLOAK_REALM`: Authentication realm
- `KEYCLOAK_CLIENT_ID`: Client identifier
- `KEYCLOAK_ADMIN_PASSWORD`: Admin password

**CORS**:
- `CORS_ORIGIN`: Allowed frontend origins

## 📊 Database Schema

### Core Tables
- `companies` - Company profiles and data
- `workspaces` - Multi-tenancy organization
- `workspace_members` - User-workspace relationships
- `user_workspace_permissions` - Granular permissions
- `tasks` - Data collection job tracking
- `workflow_configs` - AI workflow settings

## 📈 Task Queue System

### Overview
The application uses Celery with RabbitMQ for distributed task processing, enabling asynchronous execution of Dify AI workflows with intelligent concurrency control.

### Components

#### RabbitMQ Message Broker
- **Purpose**: Reliable message queue for task distribution
- **Features**:
  - Persistent messages with 1-hour TTL
  - Priority queue support
  - Automatic task re-queueing on worker failure
- **Management UI**: http://localhost:15672 (guest/guest)

#### Celery Workers
- **Purpose**: Execute Dify workflow tasks asynchronously
- **Configuration**:
  - Concurrency: 4 workers with solo pool (for async compatibility)
  - Task timeout: 10 min soft limit, 15 min hard limit
  - Result backend: RabbitMQ RPC
- **Queue**: `dify_workflows` - Dedicated queue for AI workflow tasks

#### Flower Dashboard
- **Purpose**: Real-time monitoring and management of Celery workers
- **Features**:
  - Worker status and performance metrics
  - Task execution history and statistics
  - Queue monitoring and management
- **Access**: http://localhost:5555 (admin/admin)

### Dynamic Concurrency Control
The system implements database-based concurrency management to prevent API rate limiting:

- **Max Concurrent Workflows**: Configurable via `MAX_CONCURRENT_WORKFLOWS` (default: 10)
- **Queue Management**: Tasks wait for available slots before execution
- **Status Tracking**: Real-time monitoring via `/api/concurrency/status`
- **Stuck Task Detection**: Automatic identification of tasks running > 30 minutes
- **Graceful Degradation**: Tasks timeout after 5 minutes waiting for slots

### Task Workflow

1. **Task Creation**: API endpoint creates task in database (status: PENDING)
2. **Queue Submission**: Task sent to RabbitMQ `dify_workflows` queue
3. **Worker Processing**:
   - Worker picks up task from queue
   - Checks concurrency limits (waits if at max)
   - Updates status to RUNNING
   - Executes Dify workflow with callbacks
4. **Webhook Callbacks**: Dify sends results back via webhooks
5. **Status Updates**: Task marked COMPLETED or ERROR based on results

### Monitoring Commands

```bash
# Check worker status
docker compose -f docker-compose.dev.yml logs celery_worker

# Monitor real-time task execution
docker compose -f docker-compose.dev.yml logs -f celery_worker

# View queue status via API
curl http://localhost:8000/api/concurrency/status

# Access Flower dashboard
open http://localhost:5555

# Access RabbitMQ management
open http://localhost:15672

# Check queue health
docker compose -f docker-compose.dev.yml exec celery_worker celery -A app.core.celery_app inspect active
```

### Troubleshooting

#### Tasks Stuck in RUNNING
```bash
# Check for stuck tasks
curl -X POST http://localhost:8000/api/concurrency/cleanup

# Restart workers
docker compose -f docker-compose.dev.yml restart celery_worker
```

#### Worker Connection Issues
```bash
# Check RabbitMQ health
docker compose -f docker-compose.dev.yml ps rabbitmq

# Verify worker can connect
docker compose -f docker-compose.dev.yml exec celery_worker celery -A app.core.celery_app status
```

## 🔍 Monitoring

### Health Checks
```bash
# Service status
docker compose -f docker-compose.dev.yml ps

# Backend health
curl http://localhost:8000/docs

# Database connection
docker compose -f docker-compose.dev.yml exec backend python -c "from app.database import engine; print(engine.execute('SELECT 1').scalar())"
```

### Logs
```bash
# All services
docker compose -f docker-compose.dev.yml logs

# Specific service
docker compose -f docker-compose.dev.yml logs backend

# Real-time
docker compose -f docker-compose.dev.yml logs -f backend
```

## 🔧 Development

### Database Migrations
```bash
# Create migration
docker compose -f docker-compose.dev.yml exec backend alembic revision -m "description"

# Apply migrations
docker compose -f docker-compose.dev.yml exec backend alembic upgrade head
```

### Adding New Features
1. Create API endpoints in `mint-back/app/api/endpoints/`
2. Define data models in `mint-back/app/models/`
3. Add business logic in `mint-back/app/services/`
4. Update permissions in Keycloak realm configuration

## 🚨 Known Issues

### Alembic Migrations
Some migration files contain null bytes. Use manual table creation if needed:
```bash
docker compose -f docker-compose.dev.yml exec backend python -c "
from app.database import Base, engine
Base.metadata.create_all(bind=engine)
"
```

### Dify Connectivity
External Dify workflows may be unavailable. This only affects new data collection tasks - existing company data remains accessible.

## 📁 Project Structure

```
mint-server/
├── mint-back/              # FastAPI application
│   ├── app/
│   │   ├── api/            # REST API endpoints
│   │   ├── core/           # Configuration and security
│   │   ├── models/         # Database models
│   │   ├── services/       # Business logic
│   │   ├── schemas/        # Pydantic models
│   │   └── infrastructure/ # External integrations
│   ├── alembic/            # Database migrations
│   └── scripts/            # Utility scripts
├── docker/                 # Docker configurations
│   ├── db/                 # PostgreSQL setup
│   └── keycloak/           # Auth server config
├── create_initial_user.sh  # Admin user setup
├── create_test_users.sh    # Test users creation
├── deploy-preprod.sh       # Preprod deployment
├── logs.sh                 # Log viewing utility
└── docker-compose.*.yml    # Environment configs
```

## 🤝 Contributing

1. Follow existing code patterns and structure
2. Add appropriate permissions for new endpoints
3. Update documentation for new features
4. Test with multiple user permission levels
5. Ensure Docker compatibility

---

*This platform helps businesses discover comprehensive intelligence about companies through automated data collection and AI-powered analysis.*