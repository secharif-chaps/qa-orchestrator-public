"""Tests for module enablement endpoints.

Tests:
- GET /organizations/{id}/modules - list all modules
- PUT /organizations/{id}/modules - bulk update modules
- PUT /organizations/{id}/modules/{module}/toggle - toggle single module

Note: These tests focus on database layer and business logic.
Full integration tests with auth are in test_module_workflows.py
"""

import pytest

from app.models.organization import (
    Organization,
    OrganizationModule,
    ModuleName,
)
from app.services.token_manager import TokenManager


@pytest.fixture
def test_org_id():
    """Test organization ID."""
    return "test-org-123"


@pytest.fixture
async def setup_test_modules(global_db_session, test_org_id):
    """Set up test organization with modules."""
    # Create organization
    org = Organization(organization_id=test_org_id, token_balance=1000)
    global_db_session.add(org)

    # Create modules (screen enabled, target/explore disabled)
    modules = [
        OrganizationModule(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        ),
        OrganizationModule(
            organization_id=test_org_id,
            module_name=ModuleName.TARGET,
            enabled=False,
        ),
        OrganizationModule(
            organization_id=test_org_id,
            module_name=ModuleName.EXPLORE,
            enabled=False,
        ),
    ]
    for module in modules:
        global_db_session.add(module)

    await global_db_session.commit()
    return org, modules


class TestModuleEndpointsBasic:
    """Basic tests for module endpoints that can work without full auth."""

    async def test_get_modules_endpoint_registered(self, client, test_org_id):
        """Test GET modules endpoint is registered."""
        response = await client.get(f"/api/organizations/{test_org_id}/modules")

        # Endpoint exists (may fail auth but endpoint is there)
        assert response.status_code in [200, 401, 403, 422]

    async def test_bulk_update_endpoint_registered(self, client, test_org_id):
        """Test PUT bulk update endpoint is registered."""
        response = await client.put(
            f"/api/organizations/{test_org_id}/modules",
            json={"screen": {"enabled": True}},
        )

        # Endpoint exists (may fail auth/validation)
        assert response.status_code in [200, 400, 401, 403, 422]

    async def test_toggle_endpoint_registered(self, client, test_org_id):
        """Test PUT toggle endpoint is registered."""
        response = await client.put(
            f"/api/organizations/{test_org_id}/modules/screen/toggle"
        )

        # Endpoint exists (may fail auth)
        assert response.status_code in [200, 401, 403, 404, 422]

    async def test_toggle_invalid_module_validation(self, client, test_org_id):
        """Test validation rejects invalid module names."""
        response = await client.put(
            f"/api/organizations/{test_org_id}/modules/invalid_module/toggle"
        )

        # Should fail validation
        assert response.status_code == 422


class TestTokenManagerModuleOperations:
    """Test TokenManager module operations directly."""

    async def test_get_all_organization_modules(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test getting all modules for an organization."""
        token_manager = TokenManager(db=global_db_session)

        modules = await token_manager.get_all_organization_modules(test_org_id)

        assert len(modules) == 3
        modules_by_name = {m.module_name: m for m in modules}
        assert modules_by_name[ModuleName.SCREEN].enabled is True
        assert modules_by_name[ModuleName.TARGET].enabled is False
        assert modules_by_name[ModuleName.EXPLORE].enabled is False

    async def test_get_or_create_module(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test getting specific module configuration."""
        token_manager = TokenManager(db=global_db_session)

        screen_module = await token_manager.get_or_create_module(
            test_org_id, ModuleName.SCREEN
        )

        assert screen_module.module_name == ModuleName.SCREEN
        assert screen_module.enabled is True

    async def test_update_module_config(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test updating module configuration."""
        token_manager = TokenManager(db=global_db_session)

        # Update target module to enabled
        updated_module = await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.TARGET,
            enabled=True,
        )

        assert updated_module.enabled is True

        # Verify it persisted
        module = await token_manager.get_or_create_module(test_org_id, ModuleName.TARGET)
        assert module.enabled is True

    async def test_update_nonexistent_module_creates_it(
        self, global_db_session
    ):
        """Test updating module for org without modules creates them."""
        # Create org without modules
        new_org_id = "new-org-456"
        org = Organization(organization_id=new_org_id, token_balance=500)
        global_db_session.add(org)
        await global_db_session.commit()

        token_manager = TokenManager(db=global_db_session)

        # Update should create the module
        module = await token_manager.update_module_config(
            organization_id=new_org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )

        assert module.enabled is True
        assert module.organization_id == new_org_id
        assert module.module_name == ModuleName.SCREEN


class TestModuleBulkOperations:
    """Test bulk module operations."""

    async def test_enable_all_modules(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test enabling all modules at once."""
        token_manager = TokenManager(db=global_db_session)

        # Enable all modules
        for module_name in [ModuleName.TARGET, ModuleName.EXPLORE]:
            await token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=True,
            )

        # Verify all are enabled
        modules = await token_manager.get_all_organization_modules(test_org_id)
        assert all(m.enabled for m in modules)

    async def test_disable_all_modules(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test disabling all modules."""
        token_manager = TokenManager(db=global_db_session)

        # Disable all modules
        for module_name in [
            ModuleName.SCREEN,
            ModuleName.TARGET,
            ModuleName.EXPLORE,
        ]:
            await token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=False,
            )

        # Verify all are disabled
        modules = await token_manager.get_all_organization_modules(test_org_id)
        assert all(not m.enabled for m in modules)

    async def test_partial_enablement(
        self, global_db_session, test_org_id, setup_test_modules
    ):
        """Test enabling only some modules."""
        token_manager = TokenManager(db=global_db_session)

        # Enable target, keep screen enabled, leave explore disabled
        await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.TARGET,
            enabled=True,
        )

        modules = await token_manager.get_all_organization_modules(test_org_id)
        modules_by_name = {m.module_name: m for m in modules}

        assert modules_by_name[ModuleName.SCREEN].enabled is True
        assert modules_by_name[ModuleName.TARGET].enabled is True
        assert modules_by_name[ModuleName.EXPLORE].enabled is False


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
