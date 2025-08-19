# Claude Code Configuration

## Remote Server Access

### SSH Connection to Production Server
- **Server**: 10.0.1.2 (nmercier@10.0.1.2)
- **Project Location**: ~/mint (NOT ~/mint-server)
- **Database Container**: mint-server-db-1

### Accessing Database on Remote Server
```bash
# Connect to database from remote server
ssh nmercier@10.0.1.2 "docker exec mint-server-db-1 psql -U postgres -d mint_db -c \"YOUR_SQL_QUERY\""

# List tables
ssh nmercier@10.0.1.2 "docker exec mint-server-db-1 psql -U postgres -d mint_db -c \"\\dt\""

# Describe table structure
ssh nmercier@10.0.1.2 "docker exec mint-server-db-1 psql -U postgres -d mint_db -c \"\\d TABLE_NAME\""
```

**Important Notes:**
- The Docker container name is `mint-server-db-1`, not accessed via docker-compose
- Use double quotes for the SQL query to handle escaping properly
- No need to cd into directories when using docker exec directly

## Docker Compose Commands

This project uses different Docker Compose files for different environments:

### Development Environment
- **File**: `docker-compose.dev.yml`  
- **Commands**: Use `-f docker-compose.dev.yml` flag
- **Examples**:
  ```bash
  docker compose -f docker-compose.dev.yml ps
  docker compose -f docker-compose.dev.yml up -d
  docker compose -f docker-compose.dev.yml restart keycloak
  docker compose -f docker-compose.dev.yml logs keycloak
  ```

### Production Environment  
- **File**: `docker-compose.prod.yml`
- **Commands**: Use `-f docker-compose.prod.yml` flag
- **Examples**:
  ```bash
  docker compose -f docker-compose.prod.yml ps
  docker compose -f docker-compose.prod.yml up -d
  docker compose -f docker-compose.prod.yml restart
  ```

### Services Available
- **backend**: FastAPI backend service (port 8000)
- **frontend**: Nuxt.js frontend service (port 3000)
- **keycloak**: Authentication service (port 8080)
- **db**: PostgreSQL database service (port 5432)

**Note**: Always specify the compose file with `-f` flag to avoid "no configuration file provided" errors.

## Database Migrations

### Creating Migrations
Always create migrations from within the Docker container:
```bash
docker compose -f docker-compose.dev.yml exec backend alembic revision -m "description"
```

### Applying Migrations
```bash
docker compose -f docker-compose.dev.yml exec backend alembic upgrade head
```

### Checking Migration Status
```bash
docker compose -f docker-compose.dev.yml exec backend alembic current
```

**Important**: Never run alembic commands locally - the database host is configured as 'db' which only resolves inside Docker network.

## Backend Development

### Running Python Scripts
To run Python scripts that need database access:
```bash
docker compose -f docker-compose.dev.yml exec backend python script_name.py
```

### Testing Endpoints
The backend API is available at `http://localhost:8000/api/v1/`

### Common Commands
- Check logs: `docker compose -f docker-compose.dev.yml logs backend`
- Restart backend: `docker compose -f docker-compose.dev.yml restart backend`
- Enter backend shell: `docker compose -f docker-compose.dev.yml exec backend bash`

## Permission System Guidelines

### Available Permissions

#### Workspace Permissions (workspace-specific)
- **workspace.read**: View workspace content (basic access)
- **workspace.write**: Modify workspace content and manage team members

#### Company Permissions (workspace-specific)  
- **company.view**: View companies in workspace
- **company.create**: Search and create companies (search form functionality)
- **company.update**: Update existing companies (future feature)
- **company.delete**: Delete companies from workspace

#### Global Admin Permissions
- **admin.workspaces**: Global workspace administration

### Permission Implementation Rules

#### When Adding New Features
1. **ALWAYS ask user about permissions** before implementing
2. **Check if existing permission covers the feature**:
   - company.create = search + create companies  
   - workspace.write = workspace modifications + user management
3. **Only create NEW permissions if existing ones don't fit**
4. **User MUST decide** on permission choice before implementation

#### Frontend Implementation
- Use `usePermissions()` composable for permission checks
- Show/hide UI elements based on permissions (v-if="canCreateCompany")
- Display helpful messages for users without permissions

#### Backend Implementation  
- Always verify permissions in API endpoints using `verify_*_permission()` functions
- Return 403 Forbidden with clear error messages
- Check permissions BEFORE executing business logic

#### Permission Naming Convention
- Format: `resource.action` (e.g., company.create, workspace.write)
- Workspace permissions: workspace-specific only
- Admin permissions: global only
- Company permissions: workspace-specific only

### Example Permission Checks
```python
# Backend - Always check before action
verify_company_permission(workspace_context, "company.create")

# Frontend - Show/hide UI elements  
<PrimaryButton v-if="canCreateCompany">Create Company</PrimaryButton>
```