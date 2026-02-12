"""Integration tests for module enablement workflows.

Tests:
- Module toggle workflow (disable -> attempt use -> error)
- Token consumption with module checks
- Module enablement validation in TokenManager
"""

import pytest
from datetime import datetime, timezone

from app.models.organization import (
    Organization,
    OrganizationModule,
    ModuleName,
    ReferenceType,
)
from app.services.token_manager import TokenManager
from app.services.exceptions import ModuleNotEnabledException


@pytest.fixture
def test_org_id():
    """Test organization ID."""
    return "test-org-123"


@pytest.fixture
def setup_org_with_modules(global_db_session, test_org_id):
    """Set up organization with initial module configuration."""
    # Create organization with balance
    org = Organization(organization_id=test_org_id, token_balance=1000)
    global_db_session.add(org)

    # Create modules - only screen enabled
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

    global_db_session.commit()
    return org, modules


class TestModuleToggleWorkflow:
    """Test complete module toggle workflow."""

    def test_toggle_module_prevents_token_consumption(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """
        Test workflow:
        1. Disable a module
        2. Attempt to consume tokens for that module
        3. Should raise ModuleNotEnabledException
        """
        token_manager = TokenManager(db=global_db_session)

        # Step 1: Verify screen module is enabled
        screen_module = token_manager.get_or_create_module(
            test_org_id, ModuleName.SCREEN
        )
        assert screen_module.enabled is True

        # Step 2: Disable screen module
        token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=False,
        )

        # Step 3: Verify module is disabled
        screen_module = token_manager.get_or_create_module(
            test_org_id, ModuleName.SCREEN
        )
        assert screen_module.enabled is False

        # Step 4: Attempt to consume tokens for disabled module
        with pytest.raises(ModuleNotEnabledException) as exc_info:
            token_manager.consume_tokens(
                org_id=test_org_id,
                amount=35,
                module_name=ModuleName.SCREEN,
                reference_type=ReferenceType.company,
                reference_id="test-company-1",
                user_id="test-user-123",
            )

        assert "not enabled" in str(exc_info.value.detail).lower()
        assert "screen" in str(exc_info.value.detail).lower()

    def test_reenable_module_allows_consumption(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """
        Test workflow:
        1. Disable module
        2. Re-enable module
        3. Token consumption should succeed
        """
        token_manager = TokenManager(db=global_db_session)

        # Disable screen module
        token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=False,
        )

        # Re-enable screen module
        token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )

        # Now consumption should succeed
        org = token_manager.consume_tokens(
            org_id=test_org_id,
            amount=35,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="test-company-1",
            user_id="test-user-123",
        )

        assert org.token_balance == 965  # 1000 - 35


class TestTokenConsumptionWithModuleChecks:
    """Test token consumption validates module enablement."""

    def test_consume_tokens_enabled_module_success(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test consuming tokens for enabled module succeeds."""
        token_manager = TokenManager(db=global_db_session)

        org = token_manager.consume_tokens(
            org_id=test_org_id,
            amount=35,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )

        assert org.token_balance == 965
        # Verify transaction was created
        transactions = token_manager.get_transaction_history(test_org_id)
        assert len(transactions) == 1
        assert transactions[0].amount == -35

    def test_consume_tokens_disabled_module_fails(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test consuming tokens for disabled module raises exception."""
        token_manager = TokenManager(db=global_db_session)

        with pytest.raises(ModuleNotEnabledException):
            token_manager.consume_tokens(
                org_id=test_org_id,
                amount=35,
                module_name=ModuleName.TARGET,  # Disabled module
                reference_type=ReferenceType.company,
                reference_id="company-1",
                user_id="user-123",
            )

        # Balance should remain unchanged
        org = global_db_session.query(Organization).filter_by(
            organization_id=test_org_id
        ).first()
        assert org.token_balance == 1000

    def test_consume_tokens_auto_enables_module_if_needed(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test consuming tokens without module check (backwards compat)."""
        token_manager = TokenManager(db=global_db_session)

        # Consume without module_name (no module check)
        org = token_manager.consume_tokens(
            org_id=test_org_id,
            amount=35,
            module_name=None,  # No module check
            reference_type=ReferenceType.manual,
            reference_id="manual-1",
            user_id="user-123",
        )

        assert org.token_balance == 965


class TestMultipleModuleEnablementScenarios:
    """Test various module enablement scenarios."""

    def test_enable_all_modules(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test enabling all modules and consuming tokens."""
        token_manager = TokenManager(db=global_db_session)

        # Enable all modules
        for module_name in [ModuleName.TARGET, ModuleName.EXPLORE]:
            token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=True,
            )

        # Consume tokens for each module
        token_manager.consume_tokens(
            org_id=test_org_id,
            amount=10,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )

        token_manager.consume_tokens(
            org_id=test_org_id,
            amount=20,
            module_name=ModuleName.TARGET,
            reference_type=ReferenceType.company,
            reference_id="company-2",
            user_id="user-123",
        )

        token_manager.consume_tokens(
            org_id=test_org_id,
            amount=30,
            module_name=ModuleName.EXPLORE,
            reference_type=ReferenceType.company,
            reference_id="company-3",
            user_id="user-123",
        )

        # Verify total balance
        org = global_db_session.query(Organization).filter_by(
            organization_id=test_org_id
        ).first()
        assert org.token_balance == 940  # 1000 - 10 - 20 - 30

    def test_disable_all_modules(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test disabling all modules prevents all consumption."""
        token_manager = TokenManager(db=global_db_session)

        # Disable all modules
        for module_name in [
            ModuleName.SCREEN,
            ModuleName.TARGET,
            ModuleName.EXPLORE,
        ]:
            token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=False,
            )

        # Verify all modules are disabled
        modules = token_manager.get_all_organization_modules(test_org_id)
        assert all(not m.enabled for m in modules)

        # Attempt consumption should fail for all
        for module_name in [
            ModuleName.SCREEN,
            ModuleName.TARGET,
            ModuleName.EXPLORE,
        ]:
            with pytest.raises(ModuleNotEnabledException):
                token_manager.consume_tokens(
                    org_id=test_org_id,
                    amount=10,
                    module_name=module_name,
                    reference_type=ReferenceType.company,
                    reference_id=f"company-{module_name.value}",
                    user_id="user-123",
                )

    def test_partial_module_enablement(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Test with only some modules enabled."""
        token_manager = TokenManager(db=global_db_session)

        # Enable target, keep screen enabled, leave explore disabled
        token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.TARGET,
            enabled=True,
        )

        # Screen and target should work
        token_manager.consume_tokens(
            org_id=test_org_id,
            amount=10,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )

        token_manager.consume_tokens(
            org_id=test_org_id,
            amount=20,
            module_name=ModuleName.TARGET,
            reference_type=ReferenceType.company,
            reference_id="company-2",
            user_id="user-123",
        )

        # Explore should fail
        with pytest.raises(ModuleNotEnabledException):
            token_manager.consume_tokens(
                org_id=test_org_id,
                amount=30,
                module_name=ModuleName.EXPLORE,
                reference_type=ReferenceType.company,
                reference_id="company-3",
                user_id="user-123",
            )

        # Balance should reflect only screen and target
        org = global_db_session.query(Organization).filter_by(
            organization_id=test_org_id
        ).first()
        assert org.token_balance == 970  # 1000 - 10 - 20
