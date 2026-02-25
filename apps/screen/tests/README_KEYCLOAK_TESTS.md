# Keycloak Integration Tests

This directory contains integration tests for fastapi-keycloak authentication.

## Test File

- `test_admin_keycloak_integration.py` - Comprehensive tests for all admin endpoints with role-based access control

## Test Coverage

The integration tests verify:
- ✅ Admin endpoints require `admin` role
- ✅ Organization admin endpoints require `admin.organizations` role
- ✅ Workflow admin endpoints require `admin.workflows` role
- ✅ Users with wrong roles receive 403 Forbidden
- ✅ Users with correct roles can access endpoints
- ✅ Cross-role access is properly restricted
- ✅ Users with multiple roles have appropriate access

**Total Tests**: 24 integration tests covering all 12 admin endpoints

## Prerequisites

### Keycloak Configuration Required

For these tests to run, the Keycloak `admin-cli` client must be properly configured:

1. **Enable Full Scope Allowed**:
   - Go to Keycloak Admin Console → Your Realm → Clients → `admin-cli`
   - Settings tab → Enable "Full Scope Allowed"
   - Save

2. **Configure Service Account Roles**:
   - Go to "Service Account Roles" tab
   - Add roles from `realm-management`:
     - `manage-users`
     - `view-users`
     - `query-users`
     - `manage-clients`
   - Add roles from `account` client

### Environment

Tests can run in two ways:

**Option A: Docker Environment** (Recommended)
```bash
docker compose -f docker-compose.dev.yml exec backend poetry run pytest tests/test_admin_keycloak_integration.py -v
```

**Option B: Local Environment**
- Requires Keycloak server accessible at configured KEYCLOAK_SERVER_URL
- Requires proper admin-cli configuration (see above)
```bash
poetry run pytest tests/test_admin_keycloak_integration.py -v
```

## Running Tests

### Run all Keycloak integration tests:
```bash
poetry run pytest tests/test_admin_keycloak_integration.py -v
```

### Run specific test class:
```bash
poetry run pytest tests/test_admin_keycloak_integration.py::TestAdminEndpointsAccess -v
```

### Run specific test:
```bash
poetry run pytest tests/test_admin_keycloak_integration.py::TestAdminEndpointsAccess::test_admin_get_companies_with_admin_role -v
```

### Run with coverage:
```bash
poetry run pytest tests/test_admin_keycloak_integration.py --cov=app/api/endpoints/admin --cov-report=html
```

## Test Structure

### Test Classes

1. **TestAdminEndpointsAccess** - Tests for endpoints requiring `admin` role
2. **TestOrganizationAdminEndpointsAccess** - Tests for endpoints requiring `admin.organizations` role
3. **TestWorkflowAdminEndpointsAccess** - Tests for endpoints requiring `admin.workflows` role
4. **TestCrossRoleAccess** - Tests that roles don't grant access to wrong endpoints
5. **TestMultipleRoles** - Tests for users with multiple admin roles

### Mock Fixtures

Tests use mock `OIDCUser` objects with different role combinations:
- `mock_admin_user` - Has `admin` role
- `mock_organization_admin_user` - Has `admin.organizations` role
- `mock_workflow_admin_user` - Has `admin.workflows` role
- `mock_regular_user` - Has `company.view` role (no admin access)
- `mock_no_roles_user` - Has no roles

## Troubleshooting

### Error: "The access required was not contained in the access token for the admin-cli"

**Cause**: Keycloak admin-cli client is not properly configured

**Solution**: Follow the Keycloak Configuration steps above to enable Full Scope Allowed and add Service Account Roles

### Tests fail with connection errors

**Cause**: Keycloak server is not reachable

**Solution**:
- Ensure Keycloak is running
- Check KEYCLOAK_SERVER_URL in your configuration
- If running locally, ensure you can reach the Keycloak server
- Consider running tests in Docker environment instead

## Related Documentation

- [FastAPI Keycloak Docs](https://fastapi-keycloak.code-specialist.com/)
- [Backend Code Quality Refactor Spec](../.agent-os/specs/2025-11-06-backend-code-quality-refactor/spec.md)
- [Task 4.7 of Phase 3](../.agent-os/specs/2025-11-06-backend-code-quality-refactor/tasks.md)
