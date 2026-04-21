"""Integration tests for module enablement workflows.

Tests:
- Module toggle workflow (disable -> attempt use -> error)
- Token consumption with module checks
- Module enablement validation in TokenManager
"""

import pytest
from sqlalchemy import select

pytestmark = [pytest.mark.integration, pytest.mark.asyncio]

from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
)
from app.services.exceptions import ModuleNotEnabledException
from app.services.token_manager import TokenManager


@pytest.fixture
def test_org_id():
    """Test organization ID."""
    return "test-org-123"


@pytest.fixture
async def setup_org_with_modules(global_db_session, test_org_id):
    """Set up organization with initial module configuration."""
    org = Organization(organization_id=test_org_id, token_balance=1000)
    global_db_session.add(org)

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
        OrganizationModule(
            organization_id=test_org_id,
            module_name=ModuleName.STREAM,
            enabled=False,
        ),
    ]
    for module in modules:
        global_db_session.add(module)

    await global_db_session.commit()
    return org, modules


async def _get_org(session, org_id: str) -> Organization:
    result = await session.execute(
        select(Organization).where(Organization.organization_id == org_id)
    )
    return result.scalar_one()


class TestModuleToggleWorkflow:
    """Test complete module toggle workflow."""

    async def test_toggle_module_prevents_token_consumption(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Disable a module then attempt to consume → ModuleNotEnabledException."""
        token_manager = TokenManager(db=global_db_session)

        screen_module = await token_manager.get_or_create_module(
            test_org_id, ModuleName.SCREEN
        )
        assert screen_module.enabled is True

        await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=False,
        )

        screen_module = await token_manager.get_or_create_module(
            test_org_id, ModuleName.SCREEN
        )
        assert screen_module.enabled is False

        with pytest.raises(ModuleNotEnabledException) as exc_info:
            await token_manager.consume_tokens(
                org_id=test_org_id,
                amount=35,
                module_name=ModuleName.SCREEN,
                reference_type=ReferenceType.company,
                reference_id="test-company-1",
                user_id="test-user-123",
            )

        assert "not enabled" in str(exc_info.value.detail).lower()
        assert "screen" in str(exc_info.value.detail).lower()

    async def test_reenable_module_allows_consumption(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Disable → re-enable → consumption should succeed."""
        token_manager = TokenManager(db=global_db_session)

        await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=False,
        )

        await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.SCREEN,
            enabled=True,
        )

        org = await token_manager.consume_tokens(
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

    async def test_consume_tokens_enabled_module_success(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Consuming tokens for an enabled module succeeds."""
        token_manager = TokenManager(db=global_db_session)

        org = await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=35,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )

        assert org.token_balance == 965

        transactions = await token_manager.get_transaction_history(test_org_id)
        assert len(transactions) == 1
        assert transactions[0].amount == -35

    async def test_consume_tokens_disabled_module_fails(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Consuming tokens for a disabled module raises."""
        token_manager = TokenManager(db=global_db_session)

        with pytest.raises(ModuleNotEnabledException):
            await token_manager.consume_tokens(
                org_id=test_org_id,
                amount=35,
                module_name=ModuleName.TARGET,
                reference_type=ReferenceType.company,
                reference_id="company-1",
                user_id="user-123",
            )

        org = await _get_org(global_db_session, test_org_id)
        assert org.token_balance == 1000


class TestMultipleModuleEnablementScenarios:
    """Test various module enablement scenarios."""

    async def test_enable_all_modules(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Enable all modules and consume tokens for each."""
        token_manager = TokenManager(db=global_db_session)

        for module_name in [ModuleName.TARGET, ModuleName.EXPLORE]:
            await token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=True,
            )

        await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=10,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )
        await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=20,
            module_name=ModuleName.TARGET,
            reference_type=ReferenceType.company,
            reference_id="company-2",
            user_id="user-123",
        )
        await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=30,
            module_name=ModuleName.EXPLORE,
            reference_type=ReferenceType.company,
            reference_id="company-3",
            user_id="user-123",
        )

        org = await _get_org(global_db_session, test_org_id)
        assert org.token_balance == 940  # 1000 - 10 - 20 - 30

    async def test_disable_all_modules(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Disabling all modules prevents any consumption."""
        token_manager = TokenManager(db=global_db_session)

        for module_name in [
            ModuleName.SCREEN,
            ModuleName.TARGET,
            ModuleName.EXPLORE,
            ModuleName.STREAM,
        ]:
            await token_manager.update_module_config(
                organization_id=test_org_id,
                module_name=module_name,
                enabled=False,
            )

        modules = await token_manager.get_all_organization_modules(test_org_id)
        assert all(not m.enabled for m in modules)

        for module_name in [
            ModuleName.SCREEN,
            ModuleName.TARGET,
            ModuleName.EXPLORE,
            ModuleName.STREAM,
        ]:
            with pytest.raises(ModuleNotEnabledException):
                await token_manager.consume_tokens(
                    org_id=test_org_id,
                    amount=10,
                    module_name=module_name,
                    reference_type=ReferenceType.company,
                    reference_id=f"company-{module_name.value}",
                    user_id="user-123",
                )

    async def test_partial_module_enablement(
        self, global_db_session, test_org_id, setup_org_with_modules
    ):
        """Only some modules enabled → enabled ones succeed, disabled one fails."""
        token_manager = TokenManager(db=global_db_session)

        await token_manager.update_module_config(
            organization_id=test_org_id,
            module_name=ModuleName.TARGET,
            enabled=True,
        )

        await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=10,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            user_id="user-123",
        )
        await token_manager.consume_tokens(
            org_id=test_org_id,
            amount=20,
            module_name=ModuleName.TARGET,
            reference_type=ReferenceType.company,
            reference_id="company-2",
            user_id="user-123",
        )

        with pytest.raises(ModuleNotEnabledException):
            await token_manager.consume_tokens(
                org_id=test_org_id,
                amount=30,
                module_name=ModuleName.EXPLORE,
                reference_type=ReferenceType.company,
                reference_id="company-3",
                user_id="user-123",
            )

        org = await _get_org(global_db_session, test_org_id)
        assert org.token_balance == 970  # 1000 - 10 - 20
