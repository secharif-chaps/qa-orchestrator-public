"""Concurrency tests for TokenManager operations.

Tests that concurrent operations handle race conditions correctly:
- Concurrent token additions
- Concurrent token consumptions
- Concurrent module updates
- Concurrent organization creation

These tests verify that row-level locking and atomic upserts work correctly.

NOTE: These tests require PostgreSQL and are marked as integration tests.
SQLite doesn't support the same concurrency semantics or schema handling.
Run with: pytest -m integration
"""

import asyncio

import pytest
from sqlalchemy import func, select

from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
)
from app.services.exceptions import InsufficientTokensException
from app.services.token_manager import TokenManager

pytestmark = pytest.mark.integration


@pytest.fixture
def test_org_id():
    """Test organization ID."""
    return "concurrent-test-org"


@pytest.fixture
async def setup_org_with_balance(test_org_id, setup_test_db):
    """Set up test organization with initial token balance."""
    from tests.conftest import TestingSessionLocal

    async with TestingSessionLocal() as session:
        org = Organization(organization_id=test_org_id, token_balance=1000)
        session.add(org)
        await session.commit()
        # Return a detached copy with the values we need
        return type('obj', (object,), {
            'organization_id': org.organization_id,
            'token_balance': org.token_balance
        })()


@pytest.mark.asyncio
class TestConcurrentTokenOperations:
    """Test concurrent token operations."""

    async def test_concurrent_token_additions(
        self, test_org_id, setup_org_with_balance
    ):
        """Test that concurrent token additions maintain correct balance.

        Scenario: 5 concurrent additions of 100 tokens each
        Expected: Final balance = 1000 (initial) + 500 (5 x 100) = 1500
        """
        from tests.conftest import TestingSessionLocal

        initial_balance = setup_org_with_balance.token_balance

        # Create 5 concurrent addition tasks
        # Each operation gets its own session (like real requests)
        async def add_tokens():
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.add_tokens(
                    org_id=test_org_id,
                    amount=100,
                    user_id="test-user",
                )

        # Run 5 additions concurrently
        results = await asyncio.gather(
            add_tokens(),
            add_tokens(),
            add_tokens(),
            add_tokens(),
            add_tokens(),
        )

        # Verify all succeeded
        assert len(results) == 5
        for org in results:
            assert org.organization_id == test_org_id

        # Verify final balance is correct
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            final_balance = await manager.get_balance(test_org_id)
            assert final_balance == initial_balance + 500

    async def test_concurrent_token_consumptions(
        self, test_org_id, setup_org_with_balance
    ):
        """Test that concurrent token consumptions maintain correct balance.

        Scenario: 5 concurrent consumptions of 100 tokens each
        Expected: Final balance = 1000 (initial) - 500 (5 x 100) = 500
        """
        from tests.conftest import TestingSessionLocal

        initial_balance = setup_org_with_balance.token_balance

        # Enable a module for consumption
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            await manager.update_module_config(
                organization_id=test_org_id,
                module_name=ModuleName.SCREEN,
                enabled=True,
            )

        # Create 5 concurrent consumption tasks
        async def consume_tokens(index):
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.consume_tokens(
                    org_id=test_org_id,
                    amount=100,
                    module_name=ModuleName.SCREEN,
                    reference_type=ReferenceType.company,
                    reference_id=f"company-{index}",
                    user_id="test-user",
                )

        # Run 5 consumptions concurrently
        results = await asyncio.gather(
            consume_tokens(1),
            consume_tokens(2),
            consume_tokens(3),
            consume_tokens(4),
            consume_tokens(5),
        )

        # Verify all succeeded
        assert len(results) == 5

        # Verify final balance is correct
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            final_balance = await manager.get_balance(test_org_id)
            assert final_balance == initial_balance - 500

    async def test_concurrent_mixed_operations(
        self, test_org_id, setup_org_with_balance
    ):
        """Test concurrent mixed additions and consumptions.

        Scenario: Mix of additions and consumptions
        Expected: Correct final balance
        """
        from tests.conftest import TestingSessionLocal

        initial_balance = setup_org_with_balance.token_balance

        # Enable module
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            await manager.update_module_config(
                organization_id=test_org_id,
                module_name=ModuleName.SCREEN,
                enabled=True,
            )

        # Create mixed operations
        async def add_200():
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.add_tokens(
                    org_id=test_org_id, amount=200, user_id="admin"
                )

        async def consume_100(ref_id):
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.consume_tokens(
                    org_id=test_org_id,
                    amount=100,
                    module_name=ModuleName.SCREEN,
                    reference_type=ReferenceType.company,
                    reference_id=ref_id,
                    user_id="user",
                )

        # Run mixed operations: +200, -100, -100, +200 = +200 net
        results = await asyncio.gather(
            add_200(),
            consume_100("c1"),
            consume_100("c2"),
            add_200(),
        )

        assert len(results) == 4

        # Final balance = 1000 + 200 = 1200
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            final_balance = await manager.get_balance(test_org_id)
            assert final_balance == initial_balance + 200

    async def test_concurrent_consumption_with_insufficient_tokens(
        self, test_org_id, setup_test_db
    ):
        """Test that concurrent consumptions properly handle insufficient tokens.

        Scenario: Balance = 100, try to consume 80 tokens 3 times concurrently
        Expected: Only 1 succeeds, others fail with InsufficientTokensException
        """
        from tests.conftest import TestingSessionLocal

        # Create org with only 100 tokens
        async with TestingSessionLocal() as session:
            org = Organization(organization_id=test_org_id, token_balance=100)
            session.add(org)
            await session.commit()

        # Enable module
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            await manager.update_module_config(
                organization_id=test_org_id,
                module_name=ModuleName.SCREEN,
                enabled=True,
            )

        # Try to consume 80 tokens 3 times (only 1 should succeed)
        async def consume_80(ref_id):
            try:
                async with TestingSessionLocal() as session:
                    manager = TokenManager(db=session)
                    return await manager.consume_tokens(
                        org_id=test_org_id,
                        amount=80,
                        module_name=ModuleName.SCREEN,
                        reference_type=ReferenceType.company,
                        reference_id=ref_id,
                        user_id="user",
                    )
            except InsufficientTokensException:
                return None

        results = await asyncio.gather(
            consume_80("c1"),
            consume_80("c2"),
            consume_80("c3"),
        )

        # Exactly one should succeed, two should fail
        successful = [r for r in results if r is not None]
        failed = [r for r in results if r is None]

        assert len(successful) == 1
        assert len(failed) == 2

        # Final balance should be 20 (100 - 80)
        async with TestingSessionLocal() as session:
            manager = TokenManager(db=session)
            final_balance = await manager.get_balance(test_org_id)
            assert final_balance == 20


@pytest.mark.asyncio
class TestConcurrentModuleOperations:
    """Test concurrent module operations."""

    async def test_concurrent_module_updates(
        self, test_org_id, setup_test_db
    ):
        """Test concurrent updates to the same module.

        Scenario: Multiple concurrent toggle operations
        Expected:
            - No unique-constraint violation (atomic upsert handles the INSERT race)
            - Exactly one module row exists after the storm
            - Final state is one of the submitted values
        Commit order under asyncio.gather is non-deterministic, so we can't
        assert which specific value wins.
        """
        from tests.conftest import TestingSessionLocal

        # Create org
        async with TestingSessionLocal() as session:
            org = Organization(organization_id=test_org_id, token_balance=1000)
            session.add(org)
            await session.commit()

        # Concurrent updates to same module
        async def toggle_module(enabled: bool):
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.update_module_config(
                    organization_id=test_org_id,
                    module_name=ModuleName.SCREEN,
                    enabled=enabled,
                )

        submitted_values = [True, False, True, True]
        results = await asyncio.gather(
            *(toggle_module(v) for v in submitted_values)
        )

        # All upserts returned without raising
        assert len(results) == len(submitted_values)

        # Exactly one row exists — no duplicates from a lost race on INSERT
        async with TestingSessionLocal() as session:
            row_count = await session.scalar(
                select(func.count())
                .select_from(OrganizationModule)
                .where(
                    OrganizationModule.organization_id == test_org_id,
                    OrganizationModule.module_name == ModuleName.SCREEN,
                )
            )
            assert row_count == 1

            manager = TokenManager(db=session)
            final_module = await manager.get_or_create_module(
                test_org_id, ModuleName.SCREEN
            )
            # Final enabled must be one of the submitted values; we can't
            # assert which one wins because commit order is non-deterministic.
            assert final_module.enabled in submitted_values

    async def test_concurrent_get_all_modules(
        self, test_org_id, setup_test_db
    ):
        """Test concurrent calls to get_all_organization_modules.

        Scenario: Multiple concurrent reads
        Expected: All succeed, all modules created exactly once
        """
        from tests.conftest import TestingSessionLocal

        # Create org
        async with TestingSessionLocal() as session:
            org = Organization(organization_id=test_org_id, token_balance=1000)
            session.add(org)
            await session.commit()

        # Concurrent get_all calls
        async def get_all_modules():
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.get_all_organization_modules(test_org_id)

        results = await asyncio.gather(
            get_all_modules(),
            get_all_modules(),
            get_all_modules(),
        )

        # All should succeed
        assert len(results) == 3

        # All should return one entry per ModuleName (SCREEN, TARGET, EXPLORE, STREAM)
        expected_count = len(ModuleName)
        assert len(results[0]) == expected_count
        assert len(results[1]) == expected_count
        assert len(results[2]) == expected_count


@pytest.mark.asyncio
class TestConcurrentOrganizationCreation:
    """Test concurrent organization creation."""

    async def test_concurrent_organization_creation(self, setup_test_db):
        """Test concurrent creation of same organization.

        Scenario: Multiple requests try to create same org
        Expected: Only one creates, others get existing (atomic upsert works)
        """
        from tests.conftest import TestingSessionLocal

        test_org_id = "concurrent-org-create"

        # Concurrent organization creation via get_balance
        # (which calls _ensure_organization_exists)
        async def get_balance():
            async with TestingSessionLocal() as session:
                manager = TokenManager(db=session)
                return await manager.get_balance(test_org_id)

        results = await asyncio.gather(
            get_balance(),
            get_balance(),
            get_balance(),
        )

        # All should return 0 (new org)
        assert all(balance == 0 for balance in results)

        # Verify only one organization record exists
        async with TestingSessionLocal() as session:
            result = await session.execute(
                select(func.count()).select_from(Organization).filter(
                    Organization.organization_id == test_org_id
                )
            )
            count = result.scalar()
            assert count == 1


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
