# Keycloak Organization User Management

This documentation covers the organization user management endpoints that allow administrators to manage users directly from the application using Keycloak Admin API.

## Overview

The organization user management system integrates with Keycloak Organizations to provide complete user lifecycle management. Admin users with the `organization.write` permission can perform all user management operations for their organization.

## Features

- ✅ Create Keycloak users and add them to organizations
- ✅ List and search organization users with pagination
- ✅ Get detailed user information including permissions
- ✅ Update user profiles (name, email, status)
- ✅ Enable/disable user accounts
- ✅ Comprehensive error handling and validation
- ✅ Keycloak Admin API integration
- ✅ JWT-only permission model (no database permissions)

## API Endpoints

### Base Route

All endpoints use the base route: `/api/organizations/{organizationId}/users`

### 1. List Organization Users

**GET** `/api/organizations/{organizationId}/users`

Retrieves a paginated list of users in the organization from Keycloak.

**Query Parameters:**

- `page` (int, default: 1) - Page number (1-based)
- `limit` (int, default: 20, max: 100) - Items per page
- `search` (string) - Search term for name, email, or username
- `status` (enum) - Filter by status: ACTIVE, DISABLED, ALL
- `sort` (enum) - Sort field: CREATED_AT, USERNAME, EMAIL
- `order` (string) - Sort order: asc, desc (default: desc)

**Response:**

```json
{
  "data": [
    {
      "id": 12345678,
      "email": "john@example.com",
      "username": "johndoe",
      "first_name": "John",
      "last_name": "Doe",
      "created_at": "1705328400000",
      "is_active": true,
      "status": "ACTIVE",
      "permissions": ["organization.read", "company.view", "company.create"]
    }
  ],
  "meta": {
    "total": 25,
    "page": 1,
    "per_page": 20,
    "total_pages": 2
  }
}
```

### 2. Get User Details

**GET** `/api/organizations/{organizationId}/users/{userId}`

Retrieves detailed information about a specific user in the organization.

**Response:**

```json
{
  "id": 12345678,
  "email": "john@example.com",
  "username": "johndoe",
  "first_name": "John",
  "last_name": "Doe",
  "created_at": "1705328400000",
  "is_active": true,
  "status": "ACTIVE",
  "permissions": ["organization.read", "organization.write", "company.view", "company.create"]
}
```

### 3. Create User

**POST** `/api/organizations/{organizationId}/users`

Creates a new user in Keycloak and adds them to the specified organization.

**Request Body:**

```json
{
  "email": "newuser@example.com",
  "username": "newuser",
  "first_name": "New",
  "last_name": "User",
  "permissions": ["organization.read", "company.view"]
}
```

**Response:**

```json
{
  "id": 87654321,
  "email": "newuser@example.com",
  "username": "newuser",
  "first_name": "New",
  "last_name": "User",
  "created_at": "1705414800000",
  "is_active": true,
  "status": "ACTIVE",
  "permissions": ["organization.read", "company.view"],
  "temporary_password": "GeneratedPass123!"
}
```

**Notes:**

- A temporary password is automatically generated
- User will be required to change password on first login
- Email is automatically verified (emailVerified: true)
- User is added to the organization in Keycloak

### 4. Update User

**PUT** `/api/organizations/{organizationId}/users/{userId}`

Updates user profile information and permissions.

**Request Body:**

```json
{
  "email": "updated@example.com",
  "first_name": "Updated",
  "last_name": "Name",
  "is_active": true,
  "permissions": ["organization.read", "organization.write", "company.view"]
}
```

**Response:**

```json
{
  "id": 12345678,
  "email": "updated@example.com",
  "username": "johndoe",
  "first_name": "Updated",
  "last_name": "Name",
  "created_at": "1705328400000",
  "is_active": true,
  "status": "ACTIVE",
  "permissions": ["organization.read", "organization.write", "company.view"]
}
```

**Notes:**

- Username cannot be changed
- Permissions are synced with Keycloak realm roles
- Email updates are reflected in Keycloak immediately

## Authentication & Authorization

All endpoints require:

1. Valid JWT authentication token
2. `organization.write` role in the token

Example header:

```
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6...
```

### Permission Levels

**organization.read** - Basic read access to organization
**organization.write** - Manage team members and organization settings
**company.view** - View companies
**company.create** - Create/search companies
**company.delete** - Delete companies
**admin.organizations** - Global organization administration

## Error Handling

The API returns standard HTTP status codes with detailed error messages:

- **400 Bad Request** - Invalid input data or malformed request
- **401 Unauthorized** - Missing or invalid authentication token
- **403 Forbidden** - Insufficient permissions (missing organization.write role)
- **404 Not Found** - Organization or user not found
- **409 Conflict** - Username or email already exists
- **500 Internal Server Error** - Server or Keycloak connection issues

Example error response:

```json
{
  "detail": "Username already exists in Keycloak"
}
```

## Configuration

Required environment variables:

```env
# Keycloak Server Configuration
KEYCLOAK_SERVER_URL=http://localhost:8080
KEYCLOAK_REALM=your-realm
KEYCLOAK_CLIENT_ID=your-client-id
KEYCLOAK_CLIENT_SECRET=your-client-secret

# Admin Credentials for User Management
KEYCLOAK_ADMIN_USERNAME=admin
KEYCLOAK_ADMIN_PASSWORD=admin-password
```

## Security Features

### Password Generation

- Minimum 12 characters by default
- Contains uppercase, lowercase, digits, and special characters
- Cryptographically secure random generation
- Forces password reset on first login (temporary: true)

### Email Verification

- All users created with emailVerified: true
- No email server required for user creation
- Email changes reflected immediately in Keycloak

### Input Validation

- Email format validation
- Username format validation (alphanumeric + hyphens, underscores, dots)
- Field length limits
- SQL injection prevention (though not applicable - using Keycloak API)

### Access Control

- Role-based access control via Keycloak
- Organization-specific user isolation
- JWT-only permission model (no database checks)

## Keycloak Integration

### Architecture

```
Team Management Flow:
1. Frontend → Backend API (JWT authentication)
2. Backend → Keycloak Admin API (create/update/list users)
3. Keycloak → Keycloak Organizations (manage membership)
4. Keycloak → Realm Roles (manage permissions)
5. Backend ← Keycloak (user data + roles)
6. Frontend ← Backend (OrganizationUser response)
```

### Keycloak Admin API Calls

The system uses `keycloak_admin_service` to interact with Keycloak:

- `get_organization_members()` - List organization members
- `create_user()` - Create new user in Keycloak
- `add_user_to_organization()` - Add user to organization
- `update_user()` - Update user profile
- `get_user_realm_roles()` - Get user's realm roles (permissions)
- `sync_user_realm_roles()` - Sync user permissions with realm roles

## Testing

### Manual Testing

```bash
# Get auth token
TOKEN=$(python3 get_token.py)

# List organization users
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost/api/organizations/org-uuid-123/users?page=1&limit=20"

# Create user
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "username": "testuser",
    "first_name": "Test",
    "last_name": "User",
    "permissions": ["organization.read", "company.view"]
  }' \
  "http://localhost/api/organizations/org-uuid-123/users"

# Update user
curl -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newemail@example.com",
    "first_name": "Updated",
    "last_name": "Name",
    "is_active": true,
    "permissions": ["organization.write", "company.create"]
  }' \
  "http://localhost/api/organizations/org-uuid-123/users/user-uuid-456"
```

### Test Users

Use the provided test user script:

```bash
# Create test users
./create_test_users.sh --non-interactive

# Get token for organization admin
python3 get_token.py team_manager teammanager123
```

## Implementation Files

- `app/api/endpoints/team_management.py` - FastAPI endpoints
- `app/services/keycloak_admin.py` - Keycloak Admin API client
- `app/schemas/team_management.py` - Pydantic schemas
- `app/core/organization.py` - Organization context utilities

## Monitoring and Logging

The system includes comprehensive logging for:

- User creation/update/deletion events
- Keycloak API calls and responses
- Authentication failures
- Permission checks
- Organization access checks

Check application logs for troubleshooting:

```bash
# Example log entries
INFO: Creating user in Keycloak: testuser (test@example.com)
INFO: Adding user user-uuid-456 to organization org-uuid-123
INFO: Syncing permissions for user user-uuid-456: ['organization.read', 'company.view']
ERROR: Failed to create user in Keycloak: 409 - Username already exists
WARNING: Organization not found: org-uuid-999
```

## Best Practices

1. **Always use organization.write permission** for team management
2. **Monitor user creation rates** to prevent abuse
3. **Regularly audit user permissions** through Keycloak Admin Console
4. **Use strong admin credentials** for Keycloak access
5. **Enable Keycloak logging** for audit trails
6. **Test permission changes** before applying to production

## Troubleshooting

### Common Issues

1. **"Failed to authenticate with Keycloak admin"**
   - Verify `KEYCLOAK_ADMIN_USERNAME` and `KEYCLOAK_ADMIN_PASSWORD`
   - Check Keycloak server accessibility
   - Ensure admin-cli client exists and is properly configured

2. **"Username already exists"**
   - Usernames must be unique across the entire Keycloak realm
   - Check existing users in Keycloak admin console
   - Consider using email as username for uniqueness

3. **"User not found in organization"**
   - Verify organization UUID is correct
   - Check user exists in Keycloak
   - Ensure user is a member of the organization

4. **"Insufficient permissions"**
   - Verify JWT token includes organization.write role
   - Check user has access to the specific organization
   - Review organization membership in Keycloak

### Debug Steps

1. Check Keycloak admin console for user existence
2. Verify organization membership in Keycloak Organizations
3. Review application logs for detailed error messages
4. Test Keycloak Admin API connectivity:

```bash
# Get admin token
curl -X POST "${KEYCLOAK_URL}/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&client_id=admin-cli&username=admin&password=admin"

# List users
curl -H "Authorization: Bearer $ADMIN_TOKEN" \
  "${KEYCLOAK_URL}/admin/realms/your-realm/users"
```

## Migration Notes

This system replaces the old workspace user management that used database tables (`workspace_members`, `user_workspace_permissions`).

**Key Differences:**

- ✅ No database tables for users or membership
- ✅ All data comes from Keycloak Organizations
- ✅ Permissions are realm roles, not database records
- ✅ JWT-only authentication (no database permission checks)
- ✅ Organization membership managed in Keycloak

**Advantages:**

- Single source of truth (Keycloak)
- Better security (centralized identity management)
- Easier to scale (no database joins for permissions)
- Works seamlessly with SSO and external identity providers

## Future Enhancements

- [ ] Bulk user operations (import/export)
- [ ] Advanced search and filtering
- [ ] User activity tracking
- [ ] Integration with external identity providers
- [ ] Custom permission templates
- [ ] Automated user provisioning workflows
