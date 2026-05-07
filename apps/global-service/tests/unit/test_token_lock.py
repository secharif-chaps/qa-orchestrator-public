"""Tests for TokenLockManager — lock/confirm/release/cleanup lifecycle.

Tests the token reservation pattern (TAR-1387):
- Locking tokens with sufficient/insufficient balance
- Confirming with and without cost override
- Releasing and refunding tokens
- Cleanup of expired locks
- Internal header stripping
"""

from datetime import UTC, datetime, timedelta

import pytest
from sqlalchemy import select
from sqlalchemy.exc import IntegrityError

from app.models.organization import (
    Organization,
    TokenLock,
    TokenLockStatus,
    TokenTransaction,
    TransactionType,
)
from app.proxy.token_lock import (
    INTERNAL_RESPONSE_HEADERS,
    InsufficientTokensError,
    TokenLockManager,
    strip_internal_headers,
)

ORG_ID = "lock-test-org-00000000-0000-4000-a000-000000000001"


@pytest.fixture
async def org_with_tokens(global_db_session):
    """Create a test organization with 100 tokens."""
    org = Organization(organization_id=ORG_ID, token_balance=100)
    global_db_session.add(org)
    await global_db_session.commit()
    return org


@pytest.fixture
def manager(global_db_session):
    """Create a TokenLockManager bound to the test session."""
    return TokenLockManager(global_db_session)


# ── Lock ─────────────────────────────────────────────────────────────────────


class TestLock:
    """Test TokenLockManager.lock()."""

    async def test_lock_deducts_balance_and_creates_record(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-1",
        )

        assert lock.status == TokenLockStatus.locked
        assert lock.amount == 10
        assert lock.module == "screen"
        assert lock.user_id == "user-1"
        assert lock.correlation_id == "corr-1"
        assert lock.id is not None
        # Proxy locks deduct the balance up-front — flag them so service-side
        # _sum_active_locks does not double-count them.
        assert lock.debit_on_lock is True

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 90

    async def test_lock_insufficient_balance_raises(
        self, org_with_tokens, manager
    ):
        with pytest.raises(InsufficientTokensError) as exc_info:
            await manager.lock(
                organization_id=ORG_ID,
                amount=200,
                module="screen",
                user_id="user-1",
                correlation_id="corr-2",
            )

        assert exc_info.value.current_balance == 100
        assert exc_info.value.required == 100  # 200 requested - 100 available

    async def test_lock_nonexistent_org_raises(self, manager):
        with pytest.raises(InsufficientTokensError) as exc_info:
            await manager.lock(
                organization_id="nonexistent-org",
                amount=10,
                module="screen",
                user_id="user-1",
                correlation_id="corr-3",
            )

        assert exc_info.value.current_balance == 0
        assert exc_info.value.required == 10  # 10 requested - 0 available

    async def test_lock_respects_custom_ttl(
        self, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=5,
            module="screen",
            user_id="user-1",
            correlation_id="corr-4",
            lock_ttl=60,
        )

        delta = lock.expires_at - lock.locked_at
        assert abs(delta - timedelta(seconds=60)) < timedelta(seconds=2)

    async def test_lock_exact_balance_succeeds(
        self, global_db_session, org_with_tokens, manager
    ):
        """Locking the exact remaining balance should succeed."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=100,
            module="screen",
            user_id="user-1",
            correlation_id="corr-5",
        )

        assert lock.status == TokenLockStatus.locked
        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 0


# ── Confirm ──────────────────────────────────────────────────────────────────


class TestConfirm:
    """Test TokenLockManager.confirm()."""

    async def test_confirm_finalizes_lock_and_creates_transaction(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-1",
        )

        await manager.confirm(lock, reference_id="company-42")

        assert lock.status == TokenLockStatus.confirmed
        assert lock.settled_at is not None
        assert lock.reference_id == "company-42"

        # Balance stays at 90 (deducted during lock, no refund)
        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 90

        # A TokenTransaction should exist
        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        tx = result.scalar_one()
        assert tx.amount == -10
        assert tx.balance_after == 90
        assert tx.transaction_type == TransactionType.consume
        assert tx.reference_id == "company-42"

    async def test_confirm_with_cost_override_partial_refund(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-2",
        )
        # Balance is now 90

        # Backend says actual cost was 3 → refund 7
        await manager.confirm(lock, cost_override=3)

        assert lock.status == TokenLockStatus.confirmed
        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 97  # 90 + 7 refund

        # Transaction records actual cost
        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        tx = result.scalar_one()
        assert tx.amount == -3
        assert tx.balance_after == 97

    async def test_confirm_with_cost_override_zero_releases(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-3",
        )

        # cost_override=0 means the operation was free → full release
        await manager.confirm(lock, cost_override=0)

        assert lock.status == TokenLockStatus.released
        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 100  # fully refunded

    async def test_confirm_without_override_uses_original_amount(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=25,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-4",
        )

        await manager.confirm(lock)

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 75  # no refund

    async def test_confirm_already_confirmed_is_noop(
        self, global_db_session, org_with_tokens, manager
    ):
        """Calling confirm() on an already-confirmed lock is a silent no-op."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-idem-1",
        )

        await manager.confirm(lock, reference_id="ref-1")
        assert lock.status == TokenLockStatus.confirmed

        await global_db_session.refresh(org_with_tokens)
        balance_after_first_confirm = org_with_tokens.token_balance

        # Count transactions before duplicate confirm
        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        tx_count_before = len(result.scalars().all())

        # Second confirm — should be a no-op
        await manager.confirm(lock, reference_id="ref-duplicate")

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == balance_after_first_confirm

        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        tx_count_after = len(result.scalars().all())
        assert tx_count_after == tx_count_before

    async def test_confirm_after_release_is_noop(
        self, global_db_session, org_with_tokens, manager
    ):
        """Calling confirm() on a released lock is a silent no-op."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-confirm-idem-2",
        )

        await manager.release(lock)
        assert lock.status == TokenLockStatus.released

        await global_db_session.refresh(org_with_tokens)
        balance_after_release = org_with_tokens.token_balance
        assert balance_after_release == 100  # fully refunded

        # Confirm after release — should be a no-op
        await manager.confirm(lock, reference_id="ref-after-release")

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == balance_after_release

        # No transaction should have been created
        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        assert result.scalar_one_or_none() is None


# ── Release ──────────────────────────────────────────────────────────────────


class TestRelease:
    """Test TokenLockManager.release()."""

    async def test_release_refunds_full_amount(
        self, global_db_session, org_with_tokens, manager
    ):
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=25,
            module="screen",
            user_id="user-1",
            correlation_id="corr-release-1",
        )

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 75

        await manager.release(lock)

        assert lock.status == TokenLockStatus.released
        assert lock.settled_at is not None

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 100

    async def test_release_does_not_create_transaction(
        self, global_db_session, org_with_tokens, manager
    ):
        """Release is a refund — no transaction record, just balance restore."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-release-2",
        )

        await manager.release(lock)

        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        assert result.scalar_one_or_none() is None

    async def test_release_already_released_is_noop(
        self, global_db_session, org_with_tokens, manager
    ):
        """Calling release() on an already-released lock is a silent no-op."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=20,
            module="screen",
            user_id="user-1",
            correlation_id="corr-release-idem-1",
        )

        await manager.release(lock)
        assert lock.status == TokenLockStatus.released

        await global_db_session.refresh(org_with_tokens)
        balance_after_first_release = org_with_tokens.token_balance
        assert balance_after_first_release == 100

        # Second release — should be a no-op, balance must NOT change
        await manager.release(lock)

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == balance_after_first_release

    async def test_release_after_confirm_is_noop(
        self, global_db_session, org_with_tokens, manager
    ):
        """Calling release() on a confirmed lock is a silent no-op."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=20,
            module="screen",
            user_id="user-1",
            correlation_id="corr-release-idem-2",
        )

        await manager.confirm(lock, reference_id="ref-confirmed")
        assert lock.status == TokenLockStatus.confirmed

        await global_db_session.refresh(org_with_tokens)
        balance_after_confirm = org_with_tokens.token_balance
        assert balance_after_confirm == 80  # 100 - 20

        # Release after confirm — should be a no-op, balance must NOT change
        await manager.release(lock)

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == balance_after_confirm


# ── Cleanup expired ──────────────────────────────────────────────────────────


class TestCleanupExpired:
    """Test TokenLockManager._cleanup_expired()."""

    async def test_cleanup_refunds_and_marks_expired(
        self, global_db_session, org_with_tokens, manager
    ):
        now = datetime.now(UTC)
        expired_lock = TokenLock(
            organization_id=ORG_ID,
            amount=15,
            module="screen",
            user_id="user-1",
            correlation_id="corr-expired-1",
            status=TokenLockStatus.locked,
            debit_on_lock=True,
            locked_at=now - timedelta(hours=1),
            expires_at=now - timedelta(minutes=30),
        )
        # Simulate the deduction that happened when the lock was created
        org_with_tokens.token_balance -= 15  # 100 → 85
        global_db_session.add(expired_lock)
        await global_db_session.commit()

        cleaned = await manager._cleanup_expired(ORG_ID)
        # _cleanup_expired doesn't commit (lock() does), so commit here
        await global_db_session.commit()

        assert cleaned == 1

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 100  # 85 + 15 refund

        await global_db_session.refresh(expired_lock)
        assert expired_lock.status == TokenLockStatus.expired
        assert expired_lock.settled_at is not None

    async def test_no_cleanup_for_active_locks(
        self, org_with_tokens, manager
    ):
        await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-active-1",
            lock_ttl=3600,
        )

        cleaned = await manager._cleanup_expired(ORG_ID)
        assert cleaned == 0

    async def test_cleanup_multiple_expired_locks(
        self, global_db_session, org_with_tokens, manager
    ):
        now = datetime.now(UTC)
        total_locked = 0
        for i in range(3):
            amount = 10 * (i + 1)  # 10, 20, 30
            total_locked += amount
            lock = TokenLock(
                organization_id=ORG_ID,
                amount=amount,
                module="screen",
                user_id="user-1",
                correlation_id=f"corr-multi-expired-{i}",
                status=TokenLockStatus.locked,
                debit_on_lock=True,
                locked_at=now - timedelta(hours=1),
                expires_at=now - timedelta(minutes=30),
            )
            global_db_session.add(lock)

        org_with_tokens.token_balance -= total_locked  # 100 - 60 = 40
        await global_db_session.commit()

        cleaned = await manager._cleanup_expired(ORG_ID)
        await global_db_session.commit()
        assert cleaned == 3

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 100  # 40 + 60 refund

    async def test_cleanup_mixed_expired_and_active_locks(
        self, global_db_session, org_with_tokens, manager
    ):
        """Only expired locks are cleaned up; active locks remain locked."""
        now = datetime.now(UTC)

        # Two expired locks
        for i in range(2):
            expired_lock = TokenLock(
                organization_id=ORG_ID,
                amount=10,
                module="screen",
                user_id="user-1",
                correlation_id=f"corr-mixed-expired-{i}",
                status=TokenLockStatus.locked,
                debit_on_lock=True,
                locked_at=now - timedelta(hours=1),
                expires_at=now - timedelta(minutes=30),
            )
            global_db_session.add(expired_lock)

        # One active lock (not expired)
        active_lock = TokenLock(
            organization_id=ORG_ID,
            amount=15,
            module="screen",
            user_id="user-1",
            correlation_id="corr-mixed-active",
            status=TokenLockStatus.locked,
            debit_on_lock=True,
            locked_at=now - timedelta(minutes=1),
            expires_at=now + timedelta(hours=1),
        )
        global_db_session.add(active_lock)

        # Simulate balance deduction: 100 - 10 - 10 - 15 = 65
        org_with_tokens.token_balance -= 35
        await global_db_session.commit()

        cleaned = await manager._cleanup_expired(ORG_ID)
        await global_db_session.commit()

        assert cleaned == 2

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 85  # 65 + 20 refund (only expired)

        await global_db_session.refresh(active_lock)
        assert active_lock.status == TokenLockStatus.locked

    async def test_freshly_created_lock_not_cleaned_up(
        self, org_with_tokens, manager
    ):
        """A lock created just now with TTL 300s should not be cleaned up."""
        await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-fresh-lock",
            lock_ttl=300,
        )

        cleaned = await manager._cleanup_expired(ORG_ID)
        assert cleaned == 0

    async def test_cleanup_on_org_with_no_locks(
        self, org_with_tokens, manager
    ):
        """Cleanup on an org that has no locks returns 0 without error."""
        cleaned = await manager._cleanup_expired(ORG_ID)
        assert cleaned == 0


# ── Strip internal headers ───────────────────────────────────────────────────


class TestStripInternalHeaders:
    """Test that internal token headers are removed from responses."""

    def test_strips_token_headers_preserves_others(self):
        headers = {
            "content-type": "application/json",
            "x-token-cost-override": "5",
            "x-token-reference-id": "comp-123",
            "x-correlation-id": "abc-def",
        }
        result = strip_internal_headers(headers)

        assert "content-type" in result
        assert "x-correlation-id" in result
        assert "x-token-cost-override" not in result
        assert "x-token-reference-id" not in result

    def test_strip_headers_case_insensitive(self):
        """Mixed-case internal headers must also be stripped."""
        headers = {
            "Content-Type": "application/json",
            "X-Token-Cost-Override": "5",
            "X-Token-Reference-Id": "comp-456",
            "X-Correlation-Id": "xyz-789",
        }
        result = strip_internal_headers(headers)

        assert "Content-Type" in result
        assert "X-Correlation-Id" in result
        assert "X-Token-Cost-Override" not in result
        assert "X-Token-Reference-Id" not in result

    def test_internal_headers_constant_matches_spec(self):
        """Verify the headers listed in INTERNAL_RESPONSE_HEADERS."""
        assert "x-token-cost-override" in INTERNAL_RESPONSE_HEADERS
        assert "x-token-reference-id" in INTERNAL_RESPONSE_HEADERS
        # x-correlation-id must NOT be in the set
        assert "x-correlation-id" not in INTERNAL_RESPONSE_HEADERS


# ── Value limits ─────────────────────────────────────────────────────────────


class TestValueLimits:
    """Test boundary values: cost override clamping, zero/negative amounts."""

    async def test_confirm_cost_override_exceeds_lock_amount(
        self, global_db_session, org_with_tokens, manager
    ):
        """cost_override > lock.amount is clamped to lock.amount."""
        lock = await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-clamp-1",
        )
        # Balance is 90 after locking 10

        await manager.confirm(lock, cost_override=50)

        # Clamped to 10 → no refund, balance stays 90
        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 90

        # Transaction amount should be -10 (clamped), not -50
        result = await global_db_session.execute(
            select(TokenTransaction).filter(
                TokenTransaction.organization_id == ORG_ID
            )
        )
        tx = result.scalar_one()
        assert tx.amount == -10
        assert tx.balance_after == 90

    async def test_lock_amount_zero_rejected(
        self, org_with_tokens, manager  # noqa: ARG002
    ):
        """Locking with amount=0 violates the DB check constraint."""
        with pytest.raises(IntegrityError):
            await manager.lock(
                organization_id=ORG_ID,
                amount=0,
                module="screen",
                user_id="user-1",
                correlation_id="corr-zero-amount",
            )

    async def test_lock_negative_amount_rejected(
        self, org_with_tokens, manager  # noqa: ARG002
    ):
        """Locking with a negative amount violates the DB check constraint."""
        with pytest.raises(IntegrityError):
            await manager.lock(
                organization_id=ORG_ID,
                amount=-5,
                module="screen",
                user_id="user-1",
                correlation_id="corr-negative-amount",
            )


# ── Sequential locks ────────────────────────────────────────────────────────


class TestSequentialLocks:
    """Test multiple sequential lock acquisitions on the same organization."""

    async def test_two_sequential_locks_deduct_correctly(
        self, global_db_session, org_with_tokens, manager
    ):
        """Two consecutive locks deduct their amounts cumulatively."""
        await manager.lock(
            organization_id=ORG_ID,
            amount=10,
            module="screen",
            user_id="user-1",
            correlation_id="corr-seq-1",
        )

        await manager.lock(
            organization_id=ORG_ID,
            amount=20,
            module="screen",
            user_id="user-1",
            correlation_id="corr-seq-2",
        )

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 70  # 100 - 10 - 20

    async def test_second_lock_fails_when_first_exhausted_balance(
        self, global_db_session, org_with_tokens, manager
    ):
        """Second lock fails if the first lock consumed most of the balance."""
        await manager.lock(
            organization_id=ORG_ID,
            amount=80,
            module="screen",
            user_id="user-1",
            correlation_id="corr-exhaust-1",
        )

        await global_db_session.refresh(org_with_tokens)
        assert org_with_tokens.token_balance == 20

        with pytest.raises(InsufficientTokensError) as exc_info:
            await manager.lock(
                organization_id=ORG_ID,
                amount=30,
                module="screen",
                user_id="user-1",
                correlation_id="corr-exhaust-2",
            )

        assert exc_info.value.current_balance == 20
        assert exc_info.value.required == 10  # 30 requested - 20 available
