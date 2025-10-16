# Keycloak User Management for Workspace Administration

This documentation covers the Keycloak user management endpoints that allow workspace administrators to create, manage, and delete users directly from the application.

## Overview

The workspace user management system integrates with Keycloak to provide complete user lifecycle management within workspaces. Admin users with the `admin.workspaces` role can perform all user management operations.

## Features

- ✅ Create Keycloak users with temporary passwords
- ✅ List and search workspace users with pagination
- ✅ Get detailed user information
- ✅ Update user profiles
- ✅ Enable/disable user accounts
- ✅ Send password reset emails
- ✅ Remove users from workspaces
- ✅ Comprehensive error handling and validation
- ✅ Security best practices implementation

## API Endpoints

### 1. Create User
**POST** `/workspace/admin/{workspaceId}/users`

Creates a new user in Keycloak and adds them to the specified workspace.

**Request Body:**
```json
{
  "username": "johndoe",
  "email": "john@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "temporaryPassword": "TempPass123!" // Optional - auto-generated if not provided
}
```

**Response:**
```json
{
  "id": "keycloak-user-uuid",
  "username": "johndoe",
  "email": "john@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "enabled": true,
  "emailVerified": false,
  "createdAt": "2024-01-15T10:30:00Z",
  "lastLogin": null,
  "status": "PENDING",
  "temporaryPassword": "GeneratedPass123!"
}
```

### 2. List Users
**GET** `/workspace/admin/{workspaceId}/users`

Retrieves a paginated list of users in the workspace.

**Query Parameters:**
- `page` (int, default: 0) - Page number (0-based)
- `limit` (int, default: 20, max: 100) - Items per page
- `search` (string) - Search term for username or email
- `status` (string) - Filter by status: ACTIVE, INACTIVE, PENDING

**Response:**
```json
{
  "users": [
    {
      "id": "user-uuid-1",
      "username": "johndoe",
      "email": "john@example.com",
      "firstName": "John",
      "lastName": "Doe",
      "enabled": true,
      "emailVerified": true,
      "createdAt": "2024-01-15T10:30:00Z",
      "lastLogin": "2024-01-16T09:15:00Z",
      "status": "ACTIVE"
    }
  ],
  "total": 25,
  "page": 0,
  "limit": 20
}
```

### 3. Get User Details
**GET** `/workspace/admin/{workspaceId}/users/{userId}`

Retrieves detailed information about a specific user.

### 4. Update User
**PUT** `/workspace/admin/{workspaceId}/users/{userId}`

Updates user profile information.

**Request Body:**
```json
{
  "username": "newusername",
  "email": "newemail@example.com",
  "firstName": "Updated",
  "lastName": "Name",
  "enabled": true
}
```

### 5. Toggle User Status
**PATCH** `/workspace/admin/{workspaceId}/users/{userId}/status`

Enables or disables a user account.

**Request Body:**
```json
{
  "enabled": false
}
```

### 6. Remove User
**DELETE** `/workspace/admin/{workspaceId}/users/{userId}`

Removes a user from the workspace (soft delete - sets status to REVOKED).

**Response:**
```json
{
  "message": "User removed from workspace successfully"
}
```

### 7. Send Password Reset
**POST** `/workspace/admin/{workspaceId}/users/{userId}/reset-password`

Sends a password reset email to the user via Keycloak.

**Response:**
```json
{
  "message": "Password reset email sent successfully",
  "userId": "user-uuid"
}
```

## Authentication & Authorization

All endpoints require:
1. Valid JWT authentication token
2. `admin.workspaces` role in the token

Example header:
```
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6...
```

## Error Handling

The API returns standard HTTP status codes with detailed error messages:

- **400 Bad Request** - Invalid input data or malformed request
- **401 Unauthorized** - Missing or invalid authentication token
- **403 Forbidden** - Insufficient permissions (missing admin.workspaces role)
- **404 Not Found** - Workspace or user not found
- **409 Conflict** - Username or email already exists
- **500 Internal Server Error** - Server or Keycloak connection issues

Example error response:
```json
{
  "detail": "Username already exists"
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
- Minimum 8 characters (12 by default)
- Contains uppercase, lowercase, digits, and special characters
- Cryptographically secure random generation
- Forces password reset on first login

### Input Validation
- Email format validation
- Username format validation (alphanumeric + hyphens, underscores, dots)
- Field length limits
- SQL injection prevention

### Access Control
- Role-based access control
- Workspace-specific user isolation
- Admin-only operations

## Database Integration

The system uses the existing `workspace_members` table to track user-workspace relationships:

```sql
-- Existing table structure
CREATE TABLE workspace_members (
    id SERIAL PRIMARY KEY,
    workspace_id INTEGER REFERENCES workspaces(id),
    user_id VARCHAR(255) NOT NULL,  -- Keycloak user ID
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

## Testing

Use the provided test script to validate the implementation:

```bash
python3 test_keycloak_endpoints.py
```

Make sure to:
1. Update the `ADMIN_TOKEN` variable with a valid JWT token
2. Ensure Keycloak is running and accessible
3. Verify the admin user has proper permissions

## Implementation Files

- `app/services/keycloak_admin.py` - Keycloak Admin API client
- `app/services/workspace_user.py` - Workspace user management service
- `app/schemas/workspace_user.py` - Pydantic schemas for request/response
- `app/api/endpoints/workspace.py` - FastAPI endpoints (extended)

## Monitoring and Logging

The system includes comprehensive logging for:
- User creation/deletion events
- Authentication failures
- Keycloak API errors
- Database transaction errors

Check application logs for troubleshooting:
```bash
# Example log entries
INFO: User created successfully: testuser (uuid-123)
ERROR: Failed to create user in Keycloak: 409 - Username already exists
WARNING: Failed to get Keycloak user uuid-456: User not found
```

## Best Practices

1. **Always use temporary passwords** that force users to reset on first login
2. **Monitor user creation rates** to prevent abuse
3. **Regularly audit user permissions** and workspace memberships
4. **Implement rate limiting** on user creation endpoints
5. **Use strong admin credentials** for Keycloak access
6. **Enable Keycloak logging** for audit trails
7. **Test password reset flows** in your email configuration

## Troubleshooting

### Common Issues

1. **"Failed to authenticate with Keycloak admin"**
   - Verify `KEYCLOAK_ADMIN_USERNAME` and `KEYCLOAK_ADMIN_PASSWORD`
   - Check Keycloak server accessibility
   - Ensure admin-cli client exists in master realm

2. **"Username already exists"**
   - Usernames must be unique across the entire Keycloak realm
   - Check existing users in Keycloak admin console

3. **"Failed to send password reset email"**
   - Verify Keycloak email configuration
   - Check SMTP settings in Keycloak realm
   - Ensure user has a valid email address

4. **"User not found in workspace"**
   - Verify user exists in workspace_members table
   - Check workspace ID is correct
   - Ensure user hasn't been soft-deleted (status = 'revoked')

### Debug Steps

1. Check Keycloak admin console for user creation
2. Query database for workspace_members entries
3. Review application logs for detailed error messages
4. Test Keycloak connectivity with curl:

```bash
curl -X POST "${KEYCLOAK_URL}/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&client_id=admin-cli&username=admin&password=admin"
```

## Future Enhancements

- [ ] Bulk user operations (create/delete multiple users)
- [ ] User import from CSV/Excel files
- [ ] Advanced user search and filtering
- [ ] User activity tracking and analytics
- [ ] Integration with external identity providers
- [ ] Automated user provisioning workflows
- [ ] Role and group management within workspaces