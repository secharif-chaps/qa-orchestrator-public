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

- **nginx**: Reverse proxy (port 80) - single entry point at http://localhost
- **backend**: FastAPI backend service (internal)
- **frontend**: Vue.js frontend service (internal, behind nginx)
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

## Deployment Rules

### CRITICAL: Never Copy Files Directly to Production Server

- **NEVER** use scp, ssh, or any method to directly copy files to the production server
- **NEVER** create or modify files directly on the production server
- **ALWAYS** commit and push changes, then ask user to deploy via proper deployment process
- This ensures version control integrity and proper deployment procedures

### Proper Deployment Process

1. Make changes locally in development environment
2. Test changes locally
3. Commit changes with descriptive commit message
4. Push to repository
5. Ask user to deploy using their deployment process
6. Verify deployment worked correctly

## Backend Development

### Running Python Scripts

To run Python scripts that need database access:

```bash
docker compose -f docker-compose.dev.yml exec backend python script_name.py
```

### Testing Endpoints

The backend API is available at `http://localhost/api`

### Common Commands

- Check logs: `docker compose -f docker-compose.dev.yml logs backend`
- Restart backend: `docker compose -f docker-compose.dev.yml restart backend`
- Enter backend shell: `docker compose -f docker-compose.dev.yml exec backend bash`

## Permission System Guidelines

### Available Permissions

#### Organization Permissions (organization-specific)

- **organization.read**: View organization content (basic access)
- **organization.write**: Modify organization content and manage team members

#### Company Permissions (organization-specific)

- **company.view**: View companies in organization
- **company.create**: Search and create companies (search form functionality)
- **company.update**: Update existing companies (future feature)
- **company.delete**: Delete companies from organization

#### Global Admin Permissions

- **admin.organizations**: Global organization administration

### Permission Implementation Rules

#### When Adding New Features

1. **ALWAYS ask user about permissions** before implementing
2. **Check if existing permission covers the feature**:
   - company.create = search + create companies
   - organization.write = organization modifications + user management
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

- Format: `resource.action` (e.g., company.create, organization.write)
- Organization permissions: organization-specific only
- Admin permissions: global only
- Company permissions: organization-specific only

### Example Permission Checks

```python
# Backend - Always check before action (using fastapi-keycloak)
user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"]))

# Frontend - Show/hide UI elements
<PrimaryButton v-if="canCreateCompany">Create Company</PrimaryButton>
```

## Testing and Deployment Workflow

### Local Testing

- **Backend API**: Available at `http://localhost/api`
- **Frontend**: Available at `http://localhost`
- **Keycloak**: Available at `http://localhost:8080`
- Use `docker compose -f docker-compose.dev.yml` for all local Docker operations

### Authentication for Testing

#### Setting Up Test Users (One-time setup)

```bash
# Run this once to create test users in Keycloak and database
./create_test_users.sh --non-interactive
```

This creates multiple test users with different permission levels:

- `admin` / `admin123` - Full admin access
- `company_manager` / `manager123` - Full company management
- `company_creator` / `creator123` - Can create companies
- `company_viewer` / `viewer123` - Read-only access
- `organization_manager` / `orgmanager123` - Organization management
- `no_access` / `noaccess123` - No permissions (for testing 403 errors)

#### Getting Authentication Token

```bash
# Get token for default test user
python3 get_token.py

# Get token for specific user
python3 get_token.py company_manager manager123
```

The script will output:

1. The access token
2. Example curl command with the token

#### Using Token in API Calls

```bash
# Example: Get folders
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/folders/

# Example: Create company
curl -X POST http://localhost/api/companies/ \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Company"}'
```

### Testing Process

1. Make code changes locally
2. Test locally using Docker development environment
3. Check logs: `docker compose -f docker-compose.dev.yml logs backend`
4. Once working, commit and push changes
5. Ask user to deploy to production server

### Deployment Process

1. Make changes locally in development environment
2. Test changes locally with Docker
3. Commit changes with descriptive commit message using gitmoji
4. Push to repository
5. Ask user to deploy using their deployment process
6. Verify deployment worked correctly

## Git Commit Guidelines

### Gitmoji Usage

**ALWAYS** use gitmoji in commit messages to provide visual context:

Common gitmojis for this project:

- ✨ `:sparkles:` - New features
- 🐛 `:bug:` - Bug fixes
- 🔧 `:wrench:` - Configuration changes
- 📝 `:memo:` - Documentation updates
- 🗃️ `:card_file_box:` - Database changes/migrations
- 🔒 `:lock:` - Security improvements
- ♻️ `:recycle:` - Refactoring code
- 🚀 `:rocket:` - Deployment/performance improvements
- 🔥 `:fire:` - Removing code/files
- 💄 `:lipstick:` - UI/styling updates
- 🧪 `:test_tube:` - Adding tests
- 📦 `:package:` - Dependencies/packages

### Commit Message Format

```
<gitmoji> <type>: <description>

[optional body]
```

### Examples

```bash
# Feature
✨ feat: add user authentication system

# Bug fix
🐛 fix: resolve validation error for company names with ampersand

# Database change
🗃️ feat: populate workflow_configs with 8 task types in initial migration

# Security fix
🔒 fix: sanitize user input to prevent XSS attacks
```

## Database Schema Guidelines

### User and Organization Reference Architecture

**CRITICAL**: This application does NOT use database tables for users or organizations.

- **User References**: User IDs are Keycloak UUIDs stored as strings
- **Organization References**: Organization IDs are Keycloak Organization UUIDs stored as strings
- **No Users Table**: There is NO `users` table in the database
- **No Organizations Table**: There is NO `organizations` or `organization_members` table
- **Authentication**: User authentication is handled entirely by Keycloak
- **User & Organization Data**: All stored in Keycloak, not in application database

### Table Schema Rules

- **folders.owner_id**: `VARCHAR/UUID` field containing Keycloak user ID
- **folders.owner_username**: `VARCHAR` field containing username (denormalized for display)
- **folders.organization_id**: `VARCHAR/UUID` field containing Keycloak organization ID
- **companies.owner_id**: `VARCHAR/UUID` field containing Keycloak user ID
- **companies.owner_username**: `VARCHAR` field containing username (denormalized for display)
- **companies.organization_id**: `VARCHAR/UUID` field containing Keycloak organization ID

### Model Relationships

- **NO foreign key relationships to users table** (doesn't exist)
- **NO foreign key relationships to organizations table** (doesn't exist)
- **NO SQLAlchemy relationships to User or Organization models** (don't exist)
- All user/organization references are simple string/UUID fields
- User and organization data is fetched from Keycloak when needed

### Migration Rules

- Never create `users` or `organizations` tables
- Never create foreign keys to users or organizations
- Always use VARCHAR/String/UUID fields for user and organization references
- Organization ID extracted from JWT token (Keycloak Organizations feature)
