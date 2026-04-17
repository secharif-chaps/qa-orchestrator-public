"""Token lock manager for the lock → proxy → confirm/release pattern.

Reserves tokens before proxying a request to the backend, then confirms
or releases the reservation based on the backend response.

Flow:
1. lock()   — SELECT FOR UPDATE, deduct balance, create TokenLock(status=locked)
2. proxy    — forward request to backend
3a. confirm() — backend returned 2xx → finalize lock, create TokenTransaction
3b. release() — backend returned 4xx/5xx or timeout → refund tokens
"""

from datetime import UTC, datetime, timedelta

from sqlalchemy import select, update
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.logging_config import get_logger
from app.models.organization import (
    ModuleName,
    Organization,
    ReferenceType,
    TokenLock,
    TokenLockStatus,
    TokenTransaction,
    TransactionType,
)

logger = get_logger(__name__)

# Default lock expiry if not specified by the route
DEFAULT_LOCK_EXPIRY_SECONDS = 300

# Internal headers that backends may set but should not leak to the client
INTERNAL_RESPONSE_HEADERS = frozenset({
    "x-token-cost-override",
    "x-token-reference-id",
})


class InsufficientTokensError(Exception):
    """Raised when organization balance is too low to acquire a lock."""

    def __init__(self, current_balance: int, required: int):
        self.current_balance = current_balance
        self.required = required
        super().__init__(
            f"Insufficient tokens: {current_balance} available, {required} required"
        )


class TokenLockManager:
    """Manages token lock lifecycle within a single async DB session."""

    def __init__(self, db: AsyncSession):
        self.db = db

    async def lock(
        self,
        organization_id: str,
        amount: int,
        module: ModuleName,
        user_id: str,
        correlation_id: str,
        lock_ttl: int | None = None,
    ) -> TokenLock:
        """Reserve tokens by deducting from balance and creating a lock.

        Uses SELECT FOR UPDATE on the Organization row for concurrency safety.
        Also performs inline cleanup of expired locks.

        Args:
            organization_id: Keycloak organization UUID
            amount: Number of tokens to reserve
            module: Module requesting the lock
            user_id: Keycloak user ID
            correlation_id: Request correlation ID for tracing
            lock_ttl: Lock expiry in seconds (defaults to DEFAULT_LOCK_EXPIRY_SECONDS)

        Returns:
            Created TokenLock with status=locked

        Raises:
            InsufficientTokensError: If balance < amount
        """
        expiry_seconds = lock_ttl or DEFAULT_LOCK_EXPIRY_SECONDS

        # Inline cleanup of expired locks (refund tokens)
        await self._cleanup_expired(organization_id)

        # Lock organization row
        result = await self.db.execute(
            select(Organization)
            .filter(Organization.organization_id == organization_id)
            .with_for_update()
        )
        org = result.scalar_one_or_none()

        if org is None or org.token_balance < amount:
            balance = org.token_balance if org else 0
            raise InsufficientTokensError(current_balance=balance, required=amount - balance)

        # Deduct tokens from balance
        org.token_balance -= amount

        now = datetime.now(UTC)
        token_lock = TokenLock(
            organization_id=organization_id,
            amount=amount,
            module=module,
            user_id=user_id,
            correlation_id=correlation_id,
            status=TokenLockStatus.locked,
            locked_at=now,
            expires_at=now + timedelta(seconds=expiry_seconds),
        )
        self.db.add(token_lock)

        await self.db.commit()
        await self.db.refresh(token_lock)

        logger.info(
            f"Token lock acquired: {amount} tokens for org {organization_id}",
            extra={
                "lock_id": str(token_lock.id),
                "organization_id": organization_id,
                "amount": amount,
                "token_module": module,
                "correlation_id": correlation_id,
                "expires_at": str(token_lock.expires_at),
            },
        )

        return token_lock

    async def confirm(
        self,
        lock: TokenLock,
        cost_override: int | None = None,
        reference_id: str | None = None,
    ) -> None:
        """Confirm a lock after a successful backend response.

        If cost_override is provided, adjusts the actual cost:
        - cost_override == 0 → full release (refund all tokens)
        - cost_override < lock.amount → partial refund
        - cost_override == lock.amount → no adjustment (normal confirm)

        Creates a TokenTransaction record for audit.
        Idempotent: silently skips if lock is not in 'locked' status.

        Args:
            lock: The TokenLock to confirm
            cost_override: Optional adjusted cost from backend X-Token-Cost-Override header
            reference_id: Optional reference from backend X-Token-Reference-Id header
        """
        if lock.status != TokenLockStatus.locked:
            logger.warning(
                "Skipping confirm for lock %s: status is %s",
                lock.id,
                lock.status,
            )
            return

        # Clamp cost_override to lock.amount — a backend cannot charge more than reserved
        if cost_override is not None and cost_override > lock.amount:
            logger.error(
                "cost_override %d exceeds lock amount %d for lock %s, clamping to lock amount",
                cost_override,
                lock.amount,
                lock.id,
            )
            cost_override = lock.amount

        actual_cost = lock.amount if cost_override is None else cost_override
        refund = lock.amount - actual_cost

        # If full refund (cost override = 0), release instead
        if actual_cost == 0:
            await self.release(lock)
            return

        # Always lock organization row to get a consistent balance_after
        result = await self.db.execute(
            select(Organization)
            .filter(Organization.organization_id == lock.organization_id)
            .with_for_update()
        )
        org = result.scalar_one()

        if refund > 0:
            org.token_balance += refund

        # Update lock status
        now = datetime.now(UTC)
        lock.status = TokenLockStatus.confirmed
        lock.settled_at = now
        if reference_id:
            lock.reference_id = reference_id

        # Create transaction record using the locked org's balance
        transaction = TokenTransaction(
            organization_id=lock.organization_id,
            amount=-actual_cost,
            balance_after=org.token_balance,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            reference_id=reference_id or lock.reference_id,
            created_by=lock.user_id,
        )
        self.db.add(transaction)

        await self.db.commit()

        logger.info(
            f"Token lock confirmed: {actual_cost} tokens consumed (original: {lock.amount})",
            extra={
                "lock_id": str(lock.id),
                "organization_id": lock.organization_id,
                "actual_cost": actual_cost,
                "original_amount": lock.amount,
                "refund": refund,
                "reference_id": reference_id,
                "correlation_id": lock.correlation_id,
            },
        )

    async def release(self, lock: TokenLock) -> None:
        """Release a lock and refund tokens to the organization balance.

        Called on backend error, timeout, or cost_override=0.
        Idempotent: silently skips if lock is not in 'locked' status.

        Args:
            lock: The TokenLock to release
        """
        if lock.status != TokenLockStatus.locked:
            logger.warning(
                "Skipping release for lock %s: status is %s",
                lock.id,
                lock.status,
            )
            return

        # Lock organization row and refund
        result = await self.db.execute(
            select(Organization)
            .filter(Organization.organization_id == lock.organization_id)
            .with_for_update()
        )
        org = result.scalar_one()
        org.token_balance += lock.amount

        lock.status = TokenLockStatus.released
        lock.settled_at = datetime.now(UTC)

        await self.db.commit()

        logger.info(
            f"Token lock released: {lock.amount} tokens refunded to org {lock.organization_id}",
            extra={
                "lock_id": str(lock.id),
                "organization_id": lock.organization_id,
                "amount": lock.amount,
                "correlation_id": lock.correlation_id,
            },
        )

    async def _cleanup_expired(self, organization_id: str) -> int:
        """Inline cleanup: find expired locks and refund their tokens.

        Returns the number of expired locks cleaned up.
        """
        now = datetime.now(UTC)

        # Find all expired locks for this organization
        result = await self.db.execute(
            select(TokenLock)
            .filter(
                TokenLock.organization_id == organization_id,
                TokenLock.status == TokenLockStatus.locked,
                TokenLock.expires_at < now,
            )
            .with_for_update()
        )
        expired_locks = result.scalars().all()

        if not expired_locks:
            return 0

        # Sum up the total refund
        total_refund = sum(lock.amount for lock in expired_locks)

        # Refund tokens in one update
        await self.db.execute(
            update(Organization)
            .where(Organization.organization_id == organization_id)
            .values(token_balance=Organization.token_balance + total_refund)
        )

        # Mark all as expired
        for lock in expired_locks:
            lock.status = TokenLockStatus.expired
            lock.settled_at = now

        logger.info(
            f"Cleaned up {len(expired_locks)} expired locks, refunded {total_refund} tokens",
            extra={
                "organization_id": organization_id,
                "expired_count": len(expired_locks),
                "total_refund": total_refund,
            },
        )

        return len(expired_locks)


def strip_internal_headers(headers: dict) -> dict:
    """Remove internal token headers from response before sending to client.

    Case-insensitive: handles both lowercase and mixed-case header names
    (e.g. both "x-token-cost-override" and "X-Token-Cost-Override").
    """
    return {
        key: value
        for key, value in headers.items()
        if key.lower() not in INTERNAL_RESPONSE_HEADERS
    }
