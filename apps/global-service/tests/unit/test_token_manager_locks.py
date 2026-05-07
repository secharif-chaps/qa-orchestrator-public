"""Tests for TokenManager.lock_tokens / confirm_lock / release_lock (TAR-1569).

These methods implement the reservation pattern:
- lock_tokens(): create a TokenLock without debiting the balance
- confirm_lock(): debit the locked amount and create a TokenTransaction
- release_lock(): drop the reservation without debiting

The earlier ``TokenLockManager`` (in ``app/proxy/token_lock.py``) uses a
different semantic (deduct on lock, refund on release) and is intentionally
left untouched.
"""

from datetime import UTC, datetime, timedelta

import pytest
from sqlalchemy import select

from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
    TokenLock,
    TokenLockStatus,
    TokenTransaction,
    TransactionType,
)
from app.services.exceptions import (
    InsufficientTokensException,
    LockExpiredException,
    LockNotFoundException,
    LockNotInLockedStateException,
    ModuleNotEnabledException,
)
from app.proxy.token_lock import TokenLockManager
from app.services.token_manager import TokenManager

ORG_ID = "12345678-1234-4234-a234-aaaaaaaaaaaa"
USER_ID = "user-1"


@pytest.fixture
async def org_with_balance(global_db_session):
    """Create an organization with a 100-token balance and Screen enabled."""
    org = Organization(organization_id=ORG_ID, token_balance=100)
    global_db_session.add(org)
    module = OrganizationModule(
        organization_id=ORG_ID,
        module_name=ModuleName.SCREEN,
        enabled=True,
    )
    global_db_session.add(module)
    await global_db_session.commit()
    return org


@pytest.fixture
def manager(global_db_session):
    """A TokenManager bound to the test session."""
    return TokenManager(db=global_db_session)


# ── lock_tokens ──────────────────────────────────────────────────────────────


class TestLockTokens:
    """Unit tests for TokenManager.lock_tokens()."""

    async def test_lock_creates_reservation_without_debiting(self, global_db_session, org_with_balance, manager):
        """A successful lock leaves token_balance untouched."""
        lock = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=10,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-success-1",
        )

        assert lock.status == TokenLockStatus.locked
        assert lock.amount == 10
        assert lock.organization_id == ORG_ID
        assert lock.correlation_id == "corr-success-1"
        assert lock.expires_at > lock.locked_at

        await global_db_session.refresh(org_with_balance)
        assert org_with_balance.token_balance == 100  # unchanged

    async def test_lock_insufficient_balance_raises(self, global_db_session, org_with_balance, manager):
        """Locking more than ``available_balance`` raises 402-style exception."""
        with pytest.raises(InsufficientTokensException) as exc_info:
            await manager.lock_tokens(
                org_id=ORG_ID,
                amount=200,
                module_name=ModuleName.SCREEN,
                user_id=USER_ID,
                correlation_id="corr-insufficient-1",
            )

        # current_balance reports the available balance (clamped to >= 0).
        assert exc_info.value.current_balance == 100
        assert exc_info.value.required_tokens == 200

        # No lock should have been persisted.
        result = await global_db_session.execute(
            select(TokenLock).filter(TokenLock.correlation_id == "corr-insufficient-1")
        )
        assert result.scalar_one_or_none() is None

    async def test_lock_module_not_enabled_raises(self, global_db_session, manager):
        """Locking with a disabled module is rejected."""
        # Create org without enabling any module.
        org = Organization(organization_id="org-no-module", token_balance=100)
        global_db_session.add(org)
        await global_db_session.commit()

        with pytest.raises(ModuleNotEnabledException):
            await manager.lock_tokens(
                org_id="org-no-module",
                amount=5,
                module_name=ModuleName.SCREEN,
                user_id=USER_ID,
                correlation_id="corr-disabled-module",
            )

    async def test_lock_idempotent_on_correlation_id(self, org_with_balance, manager):
        """A retry with the same active correlation_id returns the same lock."""
        first = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=10,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-idempotent-1",
        )
        second = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=10,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-idempotent-1",
        )

        assert first.id == second.id
        assert second.status == TokenLockStatus.locked

    async def test_lock_available_balance_excludes_active_locks(self, org_with_balance, manager):
        """A second lock can only consume balance not already reserved."""
        await manager.lock_tokens(
            org_id=ORG_ID,
            amount=70,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-active-1",
        )

        # Available balance is now 30. Locking 40 must fail.
        with pytest.raises(InsufficientTokensException) as exc_info:
            await manager.lock_tokens(
                org_id=ORG_ID,
                amount=40,
                module_name=ModuleName.SCREEN,
                user_id=USER_ID,
                correlation_id="corr-active-2",
            )

        assert exc_info.value.current_balance == 30


# ── confirm_lock ─────────────────────────────────────────────────────────────


class TestConfirmLock:
    """Unit tests for TokenManager.confirm_lock()."""

    async def test_confirm_debits_balance_and_creates_transaction(self, global_db_session, org_with_balance, manager):
        """Confirming a lock debits the balance and stores a transaction."""
        lock = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=15,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-confirm-1",
            reference_id="company-42",
        )

        org, transaction = await manager.confirm_lock(
            org_id=ORG_ID,
            lock_id=lock.id,
            user_id=USER_ID,
            reference_type=ReferenceType.company,
        )

        assert org.token_balance == 85  # 100 - 15
        assert transaction.amount == -15
        assert transaction.balance_after == 85
        assert transaction.transaction_type == TransactionType.consume
        assert transaction.reference_type == ReferenceType.company
        assert transaction.reference_id == "company-42"

        await global_db_session.refresh(lock)
        assert lock.status == TokenLockStatus.confirmed
        assert lock.settled_at is not None

    async def test_confirm_unknown_lock_raises_not_found(self, org_with_balance, manager):
        """A non-existent lock_id triggers LockNotFoundException."""
        import uuid

        random_lock_id = uuid.uuid4()
        with pytest.raises(LockNotFoundException):
            await manager.confirm_lock(
                org_id=ORG_ID,
                lock_id=random_lock_id,
                user_id=USER_ID,
                reference_type=ReferenceType.company,
            )

    async def test_confirm_expired_lock_raises_and_marks_expired(self, global_db_session, org_with_balance, manager):
        """Confirming after expiry sets status='expired' and raises."""
        # Insert a manually-expired lock.
        now = datetime.now(UTC)
        expired = TokenLock(
            organization_id=ORG_ID,
            amount=5,
            module=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-expired-confirm",
            status=TokenLockStatus.locked,
            locked_at=now - timedelta(minutes=10),
            expires_at=now - timedelta(minutes=1),
        )
        global_db_session.add(expired)
        await global_db_session.commit()
        await global_db_session.refresh(expired)

        with pytest.raises(LockExpiredException):
            await manager.confirm_lock(
                org_id=ORG_ID,
                lock_id=expired.id,
                user_id=USER_ID,
                reference_type=ReferenceType.company,
            )

        await global_db_session.refresh(expired)
        assert expired.status == TokenLockStatus.expired
        assert expired.settled_at is not None

        # Balance is unchanged because the confirm aborted.
        await global_db_session.refresh(org_with_balance)
        assert org_with_balance.token_balance == 100

    async def test_double_confirm_raises_state_error(self, org_with_balance, manager):
        """A second confirm on the same lock raises LockNotInLockedStateException."""
        lock = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=10,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-double-confirm",
        )

        await manager.confirm_lock(
            org_id=ORG_ID,
            lock_id=lock.id,
            user_id=USER_ID,
            reference_type=ReferenceType.company,
        )

        with pytest.raises(LockNotInLockedStateException) as exc_info:
            await manager.confirm_lock(
                org_id=ORG_ID,
                lock_id=lock.id,
                user_id=USER_ID,
                reference_type=ReferenceType.company,
            )

        assert exc_info.value.current_status == TokenLockStatus.confirmed.value


# ── release_lock ─────────────────────────────────────────────────────────────


class TestReleaseLock:
    """Unit tests for TokenManager.release_lock()."""

    async def test_release_marks_released_without_debiting(self, global_db_session, org_with_balance, manager):
        """Releasing a lock leaves token_balance untouched and creates no tx."""
        lock = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=20,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-release-1",
        )

        result = await manager.release_lock(org_id=ORG_ID, lock_id=lock.id)

        assert result.status == TokenLockStatus.released
        assert result.settled_at is not None

        await global_db_session.refresh(org_with_balance)
        assert org_with_balance.token_balance == 100

        # No transaction record should have been created.
        tx_query = await global_db_session.execute(
            select(TokenTransaction).filter(TokenTransaction.organization_id == ORG_ID)
        )
        assert tx_query.scalar_one_or_none() is None

    async def test_release_unknown_lock_raises_not_found(self, org_with_balance, manager):
        """A non-existent lock_id triggers LockNotFoundException."""
        import uuid

        random_lock_id = uuid.uuid4()
        with pytest.raises(LockNotFoundException):
            await manager.release_lock(org_id=ORG_ID, lock_id=random_lock_id)

    async def test_release_expired_lock_raises(self, global_db_session, org_with_balance, manager):
        """Releasing an expired lock marks it as expired and raises."""
        now = datetime.now(UTC)
        expired = TokenLock(
            organization_id=ORG_ID,
            amount=5,
            module=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-expired-release",
            status=TokenLockStatus.locked,
            locked_at=now - timedelta(minutes=10),
            expires_at=now - timedelta(minutes=1),
        )
        global_db_session.add(expired)
        await global_db_session.commit()
        await global_db_session.refresh(expired)

        with pytest.raises(LockExpiredException):
            await manager.release_lock(org_id=ORG_ID, lock_id=expired.id)

        await global_db_session.refresh(expired)
        assert expired.status == TokenLockStatus.expired

    async def test_double_release_raises_state_error(self, org_with_balance, manager):
        """A second release raises LockNotInLockedStateException."""
        lock = await manager.lock_tokens(
            org_id=ORG_ID,
            amount=8,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-double-release",
        )

        await manager.release_lock(org_id=ORG_ID, lock_id=lock.id)

        with pytest.raises(LockNotInLockedStateException) as exc_info:
            await manager.release_lock(org_id=ORG_ID, lock_id=lock.id)

        assert exc_info.value.current_status == TokenLockStatus.released.value


# ── get_available_balance ────────────────────────────────────────────────────


class TestGetAvailableBalance:
    """Unit tests for TokenManager.get_available_balance()."""

    async def test_no_active_locks_returns_full_balance(self, org_with_balance, manager):
        assert await manager.get_available_balance(ORG_ID) == 100

    async def test_active_lock_reduces_available_balance(self, org_with_balance, manager):
        await manager.lock_tokens(
            org_id=ORG_ID,
            amount=30,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-avail-1",
        )
        assert await manager.get_available_balance(ORG_ID) == 70

    async def test_proxy_lock_does_not_double_count_against_available_balance(
        self, global_db_session, org_with_balance, manager
    ):
        """Proxy locks already debited token_balance — must not be re-subtracted.

        Without the ``debit_on_lock`` discriminator, ``_sum_active_locks`` would
        include proxy locks and shrink ``available_balance`` by twice the same
        amount: once via the actual ``token_balance`` decrement, once via the
        active-lock sum. This regression test pins the fix.
        """
        proxy_manager = TokenLockManager(global_db_session)
        await proxy_manager.lock(
            organization_id=ORG_ID,
            amount=40,
            module=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-proxy-1",
        )

        # Proxy lock debits balance: token_balance is now 60, available also 60.
        await global_db_session.refresh(org_with_balance)
        assert org_with_balance.token_balance == 60
        assert await manager.get_available_balance(ORG_ID) == 60

        # A new service-side reservation must see the full 60 as available.
        await manager.lock_tokens(
            org_id=ORG_ID,
            amount=60,
            module_name=ModuleName.SCREEN,
            user_id=USER_ID,
            correlation_id="corr-service-after-proxy",
        )

        # Now 60 reservation-only on top of the 60 token_balance → 0 available.
        assert await manager.get_available_balance(ORG_ID) == 0
