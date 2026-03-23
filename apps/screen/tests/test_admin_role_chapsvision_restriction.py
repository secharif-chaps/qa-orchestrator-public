"""Tests for Admin Role ChapsVision Restriction.

These tests verify that the admin.organizations permission can only be assigned
to users with @chapsvision.com email addresses.
"""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi import HTTPException, status
from fastapi_keycloak import OIDCUser


@pytest.fixture
def mock_chapsvision_user():
    """Mock user with ChapsVision email."""
    return {
        "id": "chapsvision-user-id",
        "username": "adnane.saber",
        "email": "adnane.saber@chapsvision.com",
        "firstName": "Adnane",
        "lastName": "Saber",
        "enabled": True,
    }


@pytest.fixture
def mock_non_chapsvision_user():
    """Mock user with non-ChapsVision email."""
    return {
        "id": "external-user-id",
        "username": "john.doe",
        "email": "john.doe@example.com",
        "firstName": "John",
        "lastName": "Doe",
        "enabled": True,
    }


@pytest.fixture
def mock_admin_user():
    """Mock OIDCUser for admin making the request."""
    user = MagicMock(spec=OIDCUser)
    user.preferred_username = "admin@chapsvision.com"
    user.sub = "admin-user-id"
    return user


class TestAdminRoleRestriction:
    """Test suite for admin.organizations permission assignment restrictions."""

    @pytest.mark.asyncio
    async def test_chapsvision_user_can_receive_admin_permission(self, mock_chapsvision_user, mock_admin_user):
        """Test that ChapsVision user CAN receive admin.organizations permission."""
        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            # Mock Keycloak calls
            mock_kc_service.get_user = AsyncMock(return_value=mock_chapsvision_user)
            mock_kc_service.sync_user_realm_roles = AsyncMock(return_value=True)
            mock_kc_service.get_user_realm_roles = AsyncMock(return_value=[{"name": "admin.organizations"}])

            # Create request with admin.organizations permission
            request = UpdatePermissionsRequest(permissions=["admin.organizations"])

            # Should succeed without raising HTTPException
            result = await update_user_permissions(user_id="chapsvision-user-id", request=request, user=mock_admin_user)

            # Verify user was fetched (once for email validation, once for updated data)
            assert mock_kc_service.get_user.call_count == 2

            # Verify roles were synced
            mock_kc_service.sync_user_realm_roles.assert_called_once()

            # Verify result contains admin permission
            assert "admin.organizations" in result["permissions"]

    @pytest.mark.asyncio
    async def test_non_chapsvision_user_cannot_receive_admin_permission(
        self, mock_non_chapsvision_user, mock_admin_user
    ):
        """Test that non-ChapsVision user CANNOT receive admin.organizations permission."""
        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            # Mock Keycloak to return non-ChapsVision user
            mock_kc_service.get_user = AsyncMock(return_value=mock_non_chapsvision_user)

            # Create request with admin.organizations permission
            request = UpdatePermissionsRequest(permissions=["admin.organizations"])

            # Should raise HTTPException with 403 status
            with pytest.raises(HTTPException) as exc_info:
                await update_user_permissions(user_id="external-user-id", request=request, user=mock_admin_user)

            # Verify 403 Forbidden status
            assert exc_info.value.status_code == status.HTTP_403_FORBIDDEN

            # Verify error message
            assert "Admin role can only be assigned to ChapsVision employees" in exc_info.value.detail

            # Verify user was fetched to check email
            mock_kc_service.get_user.assert_called_once_with("external-user-id")

            # Verify sync_user_realm_roles was NOT called (validation failed before sync)
            mock_kc_service.sync_user_realm_roles.assert_not_called()

    @pytest.mark.asyncio
    async def test_non_admin_permissions_work_for_non_chapsvision_users(
        self, mock_non_chapsvision_user, mock_admin_user
    ):
        """Test that non-admin permissions work for non-ChapsVision users."""
        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            # Mock Keycloak calls
            mock_kc_service.get_user = AsyncMock(return_value=mock_non_chapsvision_user)
            mock_kc_service.sync_user_realm_roles = AsyncMock(return_value=True)
            mock_kc_service.get_user_realm_roles = AsyncMock(
                return_value=[{"name": "company.create"}, {"name": "organization.read"}]
            )

            # Create request with non-admin permissions
            request = UpdatePermissionsRequest(permissions=["company.create", "organization.read"])

            # Should succeed
            result = await update_user_permissions(user_id="external-user-id", request=request, user=mock_admin_user)

            # Verify roles were synced (no validation needed for non-admin permissions)
            mock_kc_service.sync_user_realm_roles.assert_called_once()

            # Verify result contains the permissions
            assert "company.create" in result["permissions"]
            assert "organization.read" in result["permissions"]

    @pytest.mark.asyncio
    async def test_error_response_format_matches_spec(self, mock_non_chapsvision_user, mock_admin_user):
        """Test that error response format matches specification."""
        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            mock_kc_service.get_user = AsyncMock(return_value=mock_non_chapsvision_user)

            request = UpdatePermissionsRequest(permissions=["admin.organizations"])

            with pytest.raises(HTTPException) as exc_info:
                await update_user_permissions(user_id="external-user-id", request=request, user=mock_admin_user)

            # Verify error details match spec
            assert exc_info.value.status_code == 403
            assert isinstance(exc_info.value.detail, str)
            assert exc_info.value.detail == "Admin role can only be assigned to ChapsVision employees"

    @pytest.mark.asyncio
    async def test_logging_of_unauthorized_assignment_attempts(
        self, mock_non_chapsvision_user, mock_admin_user, caplog
    ):
        """Test that unauthorized assignment attempts are logged."""
        import logging

        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            mock_kc_service.get_user = AsyncMock(return_value=mock_non_chapsvision_user)

            request = UpdatePermissionsRequest(permissions=["admin.organizations"])

            with caplog.at_level(logging.WARNING), pytest.raises(HTTPException):
                await update_user_permissions(user_id="external-user-id", request=request, user=mock_admin_user)

            # Verify warning was logged
            assert any("Unauthorized admin assignment attempt" in record.message for record in caplog.records)

    @pytest.mark.asyncio
    async def test_validation_happens_before_keycloak_update(self, mock_non_chapsvision_user, mock_admin_user):
        """Test that validation happens BEFORE calling Keycloak sync."""
        from app.api.endpoints.users import UpdatePermissionsRequest, update_user_permissions

        with patch("app.api.endpoints.users.keycloak_admin_service") as mock_kc_service:
            # Mock get_user to return non-ChapsVision user
            mock_kc_service.get_user = AsyncMock(return_value=mock_non_chapsvision_user)
            # Mock sync to track if it was called
            mock_kc_service.sync_user_realm_roles = AsyncMock(return_value=True)

            request = UpdatePermissionsRequest(permissions=["admin.organizations"])

            with pytest.raises(HTTPException):
                await update_user_permissions(user_id="external-user-id", request=request, user=mock_admin_user)

            # Verify get_user was called (to fetch email for validation)
            mock_kc_service.get_user.assert_called_once()

            # Verify sync_user_realm_roles was NOT called (validation failed first)
            mock_kc_service.sync_user_realm_roles.assert_not_called()
