"""Token management service for global organization token balance.

This module provides the TokenManager class for managing organization tokens
with a global balance approach. All token operations create transaction
records for complete audit trail.

Key operations:
- get_balance(): Get current token balance for organization
- add_tokens(): Add tokens to organization balance (admin operation)
- consume_tokens(): Consume tokens for operations (with module enablement check)
- lock_tokens(): Reserve tokens (no debit) for an upcoming operation
- confirm_lock(): Debit reserved tokens after the operation succeeds
- release_lock(): Release a reservation without debiting tokens
- get_transaction_history(): Query transaction history with filters
"""

from datetime import UTC, datetime, timedelta
from uuid import UUID

from sqlalchemy import func, select
from sqlalchemy.dialects.postgresql import insert
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.sql import Select

from app.core.config import settings
from app.core.logging_config import get_logger
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

logger = get_logger(__name__)

# Token cost for company creation - each company consumes 35 tokens
TOKENS_PER_COMPANY = 35


class TokenManager:
    """Service for managing organization token balances.

    Provides global token balance operations with transaction history
    for audit purposes. Uses row-level locking to prevent race conditions
    during concurrent token operations.

    Attributes:
        db: Async SQLAlchemy database session
    """

    def __init__(self, db: AsyncSession):
        """Initialize TokenManager with async database session.

        Args:
            db: Async SQLAlchemy database session for token operations
        """
        self.db = db

    async def _execute_upsert(
        self,
        stmt,
        operation_name: str,
        context: dict,
    ) -> None:
        """Execute atomic upsert with standardized error handling.

        This helper method provides consistent error handling for all
        INSERT ... ON CONFLICT DO NOTHING operations. It follows the DRY
        principle by centralizing the execute-commit-rollback-log pattern.

        Args:
            stmt: SQLAlchemy insert statement with on_conflict_do_nothing
            operation_name: Human-readable operation name for logging
            context: Dictionary of context data for error logging

        Raises:
            SQLAlchemyError: If database operation fails (after rollback)

        Example:
            >>> stmt = insert(Organization).values(...).on_conflict_do_nothing(...)
            >>> await self._execute_upsert(
            ...     stmt,
            ...     "ensure organization exists",
            ...     {"organization_id": org_id}
            ... )
        """
        await self.db.execute(stmt)
        try:
            await self.db.commit()
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to {operation_name}: {e}",
                extra={**context, "error": str(e)},
            )
            raise

    async def _ensure_organization_exists(self, org_id: str) -> Organization:
        """Ensure organization record exists using atomic upsert.

        Uses PostgreSQL INSERT ... ON CONFLICT DO NOTHING for atomic,
        race-condition-free organization creation. This avoids TOCTOU
        (Time-of-Check to Time-of-Use) issues and eliminates unnecessary
        IntegrityError exceptions under concurrent load.

        The atomic upsert approach:
        - Attempts INSERT
        - If organization exists (conflict), does nothing
        - No rollbacks, no wasted database operations
        - Guaranteed correctness under concurrent requests

        Args:
            org_id: Keycloak organization UUID

        Returns:
            Organization: The guaranteed-to-exist organization record

        Raises:
            RuntimeError: If organization cannot be ensured (database issue)
        """
        # Atomic upsert - no race condition, no wasted rollbacks
        stmt = (
            insert(Organization)
            .values(organization_id=org_id, token_balance=0)
            .on_conflict_do_nothing(index_elements=["organization_id"])
        )

        await self._execute_upsert(
            stmt,
            "ensure organization exists",
            {"organization_id": org_id},
        )

        # Fetch guaranteed-to-exist record
        result = await self.db.execute(select(Organization).filter(Organization.organization_id == org_id))
        org = result.scalar_one_or_none()

        if not org:
            # Should never happen - indicates serious database issue
            raise RuntimeError(f"Failed to ensure organization {org_id} exists")

        return org

    async def _check_module_enabled(self, org_id: str, module_name: ModuleName) -> None:
        """Check if module is enabled for organization.

        Args:
            org_id: Keycloak organization UUID
            module_name: Module to check

        Raises:
            ModuleNotEnabledException: If module is not enabled
        """
        result = await self.db.execute(
            select(OrganizationModule).filter(
                OrganizationModule.organization_id == org_id,
                OrganizationModule.module_name == module_name,
            )
        )
        module = result.scalar_one_or_none()

        if not module or not module.enabled:
            raise ModuleNotEnabledException(module_name)

    async def get_balance(self, org_id: str) -> int:
        """Get current token balance for organization.

        Creates organization record via lazy initialization if it doesn't exist.

        Args:
            org_id: Keycloak organization UUID

        Returns:
            Current token balance (0 for new organizations)
        """
        org = await self._ensure_organization_exists(org_id)
        return org.token_balance

    async def get_available_balance(self, org_id: str) -> int:
        """Get available token balance accounting for active reservations.

        Available balance = ``token_balance`` minus the sum of amounts
        currently locked by non-expired ``TokenLock`` rows. This is the
        figure that should be checked before reserving new tokens.

        Args:
            org_id: Keycloak organization UUID.

        Returns:
            Available balance, never negative (clamped to 0).
        """
        org = await self._ensure_organization_exists(org_id)
        active_sum = await self._sum_active_locks(org_id, datetime.now(UTC))
        return max(org.token_balance - active_sum, 0)

    async def add_tokens(
        self,
        org_id: str,
        amount: int,
        user_id: str,
    ) -> Organization:
        """Add tokens to organization balance.

        Creates transaction record for audit trail. Uses row-level locking
        to prevent race conditions.

        Args:
            org_id: Keycloak organization UUID
            amount: Number of tokens to add (must be positive)
            user_id: Keycloak user ID of admin performing the operation

        Returns:
            Updated Organization with new balance

        Raises:
            ValueError: If amount is not positive
        """
        if amount <= 0:
            raise ValueError("Token amount must be positive")

        # Ensure organization exists first
        await self._ensure_organization_exists(org_id)

        # Lock organization row for update to prevent race conditions
        result = await self.db.execute(
            select(Organization).filter(Organization.organization_id == org_id).with_for_update()
        )
        org = result.scalar_one()

        # Update balance
        org.token_balance += amount
        new_balance = org.token_balance

        # Create transaction record
        transaction = TokenTransaction(
            organization_id=org_id,
            amount=amount,
            balance_after=new_balance,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_by=user_id,
        )
        self.db.add(transaction)

        try:
            await self.db.commit()
            await self.db.refresh(org)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to add tokens: {e}",
                extra={
                    "organization_id": org_id,
                    "amount": amount,
                    "user_id": user_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            f"Added {amount} tokens to organization {org_id}. New balance: {new_balance}",
            extra={"organization_id": org_id, "amount": amount, "user_id": user_id},
        )

        return org

    async def consume_tokens(
        self,
        org_id: str,
        amount: int,
        module_name: ModuleName,
        reference_type: ReferenceType,
        reference_id: str | None,
        user_id: str,
    ) -> Organization:
        """Consume tokens from organization balance.

        Checks module enablement before consumption. Creates transaction
        record for audit trail. Uses row-level locking to prevent race
        conditions.

        Args:
            org_id: Keycloak organization UUID
            amount: Number of tokens to consume (must be positive)
            module_name: Module consuming the tokens (must be enabled)
            reference_type: Type of operation consuming tokens
            reference_id: Optional ID of the referenced entity (e.g., company_id)
            user_id: Keycloak user ID performing the operation

        Returns:
            Updated Organization with new balance

        Raises:
            ValueError: If amount is not positive
            ModuleNotEnabledException: If module is not enabled
            InsufficientTokensException: If balance is insufficient
        """
        if amount <= 0:
            raise ValueError("Token consumption amount must be positive")

        # Always check module is enabled before consuming tokens
        await self._check_module_enabled(org_id, module_name)

        # Ensure organization exists first
        await self._ensure_organization_exists(org_id)

        # Lock organization row for update to prevent race conditions
        result = await self.db.execute(
            select(Organization).filter(Organization.organization_id == org_id).with_for_update()
        )
        org = result.scalar_one()

        # Check sufficient balance
        if org.token_balance < amount:
            raise InsufficientTokensException(
                current_balance=org.token_balance,
                required_tokens=amount,
            )

        # Deduct tokens
        org.token_balance -= amount
        new_balance = org.token_balance

        # Create transaction record (amount is negative for consumption)
        transaction = TokenTransaction(
            organization_id=org_id,
            amount=-amount,  # Negative for consumption
            balance_after=new_balance,
            transaction_type=TransactionType.consume,
            reference_type=reference_type,
            reference_id=reference_id,
            created_by=user_id,
        )
        self.db.add(transaction)

        try:
            await self.db.commit()
            await self.db.refresh(org)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to consume tokens: {e}",
                extra={
                    "organization_id": org_id,
                    "amount": amount,
                    "module_name": module_name.value,
                    "reference_type": reference_type.value,
                    "reference_id": reference_id,
                    "user_id": user_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            f"Consumed {amount} tokens from organization {org_id}. "
            f"New balance: {new_balance}. Reference: {reference_type.value}/{reference_id}",
            extra={
                "organization_id": org_id,
                "amount": amount,
                "token_module": module_name.value,
                "reference_type": reference_type.value,
                "reference_id": reference_id,
                "user_id": user_id,
            },
        )

        return org

    # ------------------------------------------------------------------
    # Token lock lifecycle (TAR-1569): lock → confirm | release
    #
    # Semantics differ from `consume_tokens`:
    # - lock_tokens() does NOT debit the balance. It creates a TokenLock with
    #   status='locked' and an expiry. Available balance is computed as
    #   token_balance - sum(active locked amounts).
    # - confirm_lock() performs the actual debit and creates a TokenTransaction.
    # - release_lock() abandons the reservation without any debit.
    # ------------------------------------------------------------------

    @staticmethod
    def _ensure_aware(value: datetime) -> datetime:
        """Coerce a possibly-naive datetime to UTC-aware.

        ``DateTime(timezone=True)`` columns are stored as UTC-aware in PostgreSQL
        but SQLite (used by the unit-test suite) drops the timezone on read.
        Comparing such a value with an aware ``datetime.now(UTC)`` raises
        ``TypeError``, so we defensively re-attach UTC when missing.
        """
        return value if value.tzinfo is not None else value.replace(tzinfo=UTC)

    async def _sum_active_locks(self, org_id: str, now: datetime) -> int:
        """Return the total amount currently reserved by active (non-expired) locks.

        A lock is considered "active" when it is in the ``locked`` state and its
        ``expires_at`` is still in the future. Expired locks no longer count
        against the available balance.

        Only reservation-only locks (``debit_on_lock=False``) are summed.
        Proxy locks (``debit_on_lock=True``) have already been deducted from
        ``token_balance`` at lock time, so subtracting them here would
        double-count the same amount and produce spurious 402 errors.

        Args:
            org_id: Keycloak organization UUID.
            now: Reference timestamp used to compare against ``expires_at``.

        Returns:
            Sum of reserved amounts, or 0 when no active locks exist.
        """
        result = await self.db.execute(
            select(func.coalesce(func.sum(TokenLock.amount), 0)).filter(
                TokenLock.organization_id == org_id,
                TokenLock.status == TokenLockStatus.locked,
                TokenLock.expires_at > now,
                TokenLock.debit_on_lock.is_(False),
            )
        )
        return int(result.scalar_one() or 0)

    async def lock_tokens(
        self,
        org_id: str,
        amount: int,
        module_name: ModuleName,
        user_id: str,
        correlation_id: str,
        reference_id: str | None = None,
    ) -> TokenLock:
        """Reserve tokens for an upcoming operation by creating a TokenLock.

        Reservation logic:
        - available_balance = organization.token_balance - sum(active locks)
        - if available_balance < amount → InsufficientTokensException
        - otherwise create a TokenLock(status='locked') with an expiry computed
          from ``settings.TOKEN_LOCK_TIMEOUT_SECONDS``.

        Idempotency: if a lock with the same ``correlation_id`` already exists
        in the ``locked`` state and is not expired, the existing lock is
        returned unchanged. This makes the operation safe to retry.

        Args:
            org_id: Keycloak organization UUID.
            amount: Number of tokens to reserve (must be positive).
            module_name: Module reserving the tokens (must be enabled).
            user_id: Keycloak user ID who initiates the lock.
            correlation_id: Unique idempotency key for this reservation.
            reference_id: Optional reference (e.g. company_id, delivery_id).

        Returns:
            The created (or existing idempotent) TokenLock.

        Raises:
            ValueError: If ``amount`` is not strictly positive.
            ModuleNotEnabledException: If the module is not enabled for the org.
            InsufficientTokensException: If the available balance is too low.
        """
        if amount <= 0:
            raise ValueError("Token lock amount must be positive")

        # Module gate first — same contract as consume_tokens.
        await self._check_module_enabled(org_id, module_name)

        # Make sure the org row exists before we lock it.
        await self._ensure_organization_exists(org_id)

        now = datetime.now(UTC)

        # Idempotency check: returning the same lock for the same correlation_id
        # while it is still active makes the endpoint retry-safe even when the
        # caller didn't get the previous response.
        existing_result = await self.db.execute(select(TokenLock).filter(TokenLock.correlation_id == correlation_id))
        existing_lock = existing_result.scalar_one_or_none()
        if existing_lock is not None:
            if (
                existing_lock.status == TokenLockStatus.locked
                and self._ensure_aware(existing_lock.expires_at) > now
                and existing_lock.organization_id == org_id
                and existing_lock.amount == amount
            ):
                logger.info(
                    "Returning existing token lock for correlation_id (idempotent retry)",
                    extra={
                        "lock_id": str(existing_lock.id),
                        "organization_id": org_id,
                        "correlation_id": correlation_id,
                    },
                )
                return existing_lock
            # Any other prior state for the same correlation_id is a programming
            # error; let the unique constraint surface it on insert below.

        # SELECT FOR UPDATE on the org row to serialize concurrent reservations.
        result = await self.db.execute(
            select(Organization).filter(Organization.organization_id == org_id).with_for_update()
        )
        org = result.scalar_one()

        active_locked_sum = await self._sum_active_locks(org_id, now)
        available_balance = org.token_balance - active_locked_sum

        if available_balance < amount:
            # No DB mutation happened; balance stays untouched.
            raise InsufficientTokensException(
                current_balance=max(available_balance, 0),
                required_tokens=amount,
            )

        expires_at = now + timedelta(seconds=settings.TOKEN_LOCK_TIMEOUT_SECONDS)

        token_lock = TokenLock(
            organization_id=org_id,
            amount=amount,
            module=module_name,
            user_id=user_id,
            correlation_id=correlation_id,
            reference_id=reference_id,
            status=TokenLockStatus.locked,
            locked_at=now,
            expires_at=expires_at,
        )
        self.db.add(token_lock)

        try:
            await self.db.commit()
            await self.db.refresh(token_lock)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to create token lock: {e}",
                extra={
                    "organization_id": org_id,
                    "amount": amount,
                    "module_name": module_name.value,
                    "correlation_id": correlation_id,
                    "user_id": user_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            f"Locked {amount} tokens for org {org_id} (lock_id={token_lock.id})",
            extra={
                "lock_id": str(token_lock.id),
                "organization_id": org_id,
                "amount": amount,
                "token_module": module_name.value,
                "correlation_id": correlation_id,
                "reference_id": reference_id,
                "user_id": user_id,
                "expires_at": str(expires_at),
            },
        )

        return token_lock

    async def _load_lock_for_update(self, org_id: str, lock_id: UUID) -> TokenLock:
        """Load a TokenLock row for update, scoped to the given organization.

        Args:
            org_id: Keycloak organization UUID.
            lock_id: UUID of the TokenLock to load.

        Returns:
            The TokenLock row, locked FOR UPDATE.

        Raises:
            LockNotFoundException: If no lock matches the given org_id/lock_id.
        """
        result = await self.db.execute(
            select(TokenLock).filter(TokenLock.id == lock_id, TokenLock.organization_id == org_id).with_for_update()
        )
        lock = result.scalar_one_or_none()
        if lock is None:
            raise LockNotFoundException(str(lock_id))
        return lock

    async def confirm_lock(
        self,
        org_id: str,
        lock_id: UUID,
        user_id: str,
        reference_type: ReferenceType,
    ) -> tuple[Organization, TokenTransaction]:
        """Confirm a token lock: debit the reserved tokens for real.

        Side effects:
        - Decrements ``organization.token_balance`` by the lock amount.
        - Creates a ``TokenTransaction`` (negative amount, type=consume).
        - Marks the lock as ``confirmed`` and stamps ``settled_at``.

        Expired locks are transitioned to the ``expired`` state and the
        operation fails with ``LockExpiredException`` so the caller can
        observe the terminal state.

        Args:
            org_id: Keycloak organization UUID.
            lock_id: UUID of the lock to confirm.
            user_id: Keycloak user ID performing the confirmation.
            reference_type: ReferenceType to attach to the resulting
                TokenTransaction.

        Returns:
            Tuple of (updated Organization, created TokenTransaction).

        Raises:
            LockNotFoundException: If no matching lock exists for this org.
            LockExpiredException: If the lock had expired before confirmation.
            LockNotInLockedStateException: If the lock is already settled.
        """
        now = datetime.now(UTC)
        lock = await self._load_lock_for_update(org_id, lock_id)

        # Auto-expire locks that exceeded their TTL before any other state
        # transition is allowed.
        if lock.status == TokenLockStatus.locked and self._ensure_aware(lock.expires_at) <= now:
            lock.status = TokenLockStatus.expired
            lock.settled_at = now
            try:
                await self.db.commit()
            except SQLAlchemyError as e:
                await self.db.rollback()
                logger.error(
                    f"Failed to auto-expire token lock during confirm: {e}",
                    extra={
                        "lock_id": str(lock_id),
                        "organization_id": org_id,
                        "error": str(e),
                    },
                )
                raise
            logger.info(
                "Token lock auto-expired during confirm",
                extra={
                    "lock_id": str(lock_id),
                    "organization_id": org_id,
                    "user_id": user_id,
                },
            )
            raise LockExpiredException(str(lock_id))

        if lock.status != TokenLockStatus.locked:
            raise LockNotInLockedStateException(str(lock_id), lock.status.value)

        # Lock the org row for the actual debit.
        org_result = await self.db.execute(
            select(Organization).filter(Organization.organization_id == org_id).with_for_update()
        )
        org = org_result.scalar_one()

        org.token_balance -= lock.amount
        new_balance = org.token_balance

        transaction = TokenTransaction(
            organization_id=org_id,
            amount=-lock.amount,
            balance_after=new_balance,
            transaction_type=TransactionType.consume,
            reference_type=reference_type,
            reference_id=lock.reference_id,
            created_by=user_id,
        )
        self.db.add(transaction)

        lock.status = TokenLockStatus.confirmed
        lock.settled_at = now

        try:
            await self.db.commit()
            await self.db.refresh(org)
            await self.db.refresh(transaction)
            await self.db.refresh(lock)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to confirm token lock: {e}",
                extra={
                    "lock_id": str(lock_id),
                    "organization_id": org_id,
                    "amount": lock.amount,
                    "user_id": user_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            f"Confirmed token lock {lock_id}: debited {lock.amount} tokens, new balance {new_balance}",
            extra={
                "lock_id": str(lock_id),
                "organization_id": org_id,
                "amount": lock.amount,
                "new_balance": new_balance,
                "reference_type": reference_type.value,
                "reference_id": lock.reference_id,
                "user_id": user_id,
            },
        )

        return org, transaction

    async def release_lock(
        self,
        org_id: str,
        lock_id: UUID,
    ) -> TokenLock:
        """Release a token lock without debiting any tokens.

        Same expiration/state checks as :py:meth:`confirm_lock`, but no balance
        change and no transaction is created.

        Args:
            org_id: Keycloak organization UUID.
            lock_id: UUID of the lock to release.

        Returns:
            The TokenLock with status=``released``.

        Raises:
            LockNotFoundException: If no matching lock exists for this org.
            LockExpiredException: If the lock had expired.
            LockNotInLockedStateException: If the lock is already settled.
        """
        now = datetime.now(UTC)
        lock = await self._load_lock_for_update(org_id, lock_id)

        if lock.status == TokenLockStatus.locked and self._ensure_aware(lock.expires_at) <= now:
            lock.status = TokenLockStatus.expired
            lock.settled_at = now
            try:
                await self.db.commit()
            except SQLAlchemyError as e:
                await self.db.rollback()
                logger.error(
                    f"Failed to auto-expire token lock during release: {e}",
                    extra={
                        "lock_id": str(lock_id),
                        "organization_id": org_id,
                        "error": str(e),
                    },
                )
                raise
            logger.info(
                "Token lock auto-expired during release",
                extra={
                    "lock_id": str(lock_id),
                    "organization_id": org_id,
                },
            )
            raise LockExpiredException(str(lock_id))

        if lock.status != TokenLockStatus.locked:
            raise LockNotInLockedStateException(str(lock_id), lock.status.value)

        lock.status = TokenLockStatus.released
        lock.settled_at = now

        try:
            await self.db.commit()
            await self.db.refresh(lock)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to release token lock: {e}",
                extra={
                    "lock_id": str(lock_id),
                    "organization_id": org_id,
                    "error": str(e),
                },
            )
            raise

        logger.info(
            f"Released token lock {lock_id} for org {org_id} (no debit)",
            extra={
                "lock_id": str(lock_id),
                "organization_id": org_id,
                "amount": lock.amount,
            },
        )

        return lock

    def _build_transaction_filters(
        self,
        query: Select,
        org_id: str,
        transaction_type: TransactionType | None = None,
        reference_type: ReferenceType | None = None,
        date_from: datetime | None = None,
        date_to: datetime | None = None,
    ) -> Select:
        """Build base query with transaction filters.

        This helper method applies common filtering logic for both
        get_transaction_history and get_transaction_count to follow DRY principle.

        Args:
            query: SQLAlchemy select query to apply filters to
            org_id: Keycloak organization UUID
            transaction_type: Optional filter by transaction type
            reference_type: Optional filter by reference type
            date_from: Optional filter for transactions after this date
            date_to: Optional filter for transactions before this date

        Returns:
            Query with all filters applied
        """
        query = query.filter(TokenTransaction.organization_id == org_id)

        if transaction_type is not None:
            query = query.filter(TokenTransaction.transaction_type == transaction_type)

        if reference_type is not None:
            query = query.filter(TokenTransaction.reference_type == reference_type)

        if date_from is not None:
            query = query.filter(TokenTransaction.created_at >= date_from)

        if date_to is not None:
            query = query.filter(TokenTransaction.created_at <= date_to)

        return query

    async def get_transaction_history(
        self,
        org_id: str,
        transaction_type: TransactionType | None = None,
        reference_type: ReferenceType | None = None,
        date_from: datetime | None = None,
        date_to: datetime | None = None,
        page: int = 1,
        size: int = 50,
    ) -> list[TokenTransaction]:
        """Get transaction history for organization with optional filters.

        Results are ordered by created_at descending (most recent first).

        Args:
            org_id: Keycloak organization UUID
            transaction_type: Optional filter by transaction type
            reference_type: Optional filter by reference type
            date_from: Optional filter for transactions after this date
            date_to: Optional filter for transactions before this date
            page: Page number (1-indexed)
            size: Number of results per page

        Returns:
            List of TokenTransaction records matching filters
        """
        query = select(TokenTransaction)
        query = self._build_transaction_filters(query, org_id, transaction_type, reference_type, date_from, date_to)

        # Order by most recent first
        query = query.order_by(TokenTransaction.created_at.desc())

        # Apply pagination
        offset = (page - 1) * size
        query = query.offset(offset).limit(size)

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def get_transaction_count(
        self,
        org_id: str,
        transaction_type: TransactionType | None = None,
        reference_type: ReferenceType | None = None,
        date_from: datetime | None = None,
        date_to: datetime | None = None,
    ) -> int:
        """Get total count of transactions for pagination.

        Args:
            org_id: Keycloak organization UUID
            transaction_type: Optional filter by transaction type
            reference_type: Optional filter by reference type
            date_from: Optional filter for transactions after this date
            date_to: Optional filter for transactions before this date

        Returns:
            Total count of matching transactions
        """
        query = select(func.count()).select_from(TokenTransaction)
        query = self._build_transaction_filters(query, org_id, transaction_type, reference_type, date_from, date_to)

        result = await self.db.execute(query)
        return result.scalar() or 0

    # Module management methods

    async def get_or_create_module(self, organization_id: str, module_name: ModuleName) -> OrganizationModule:
        """Get or create a organization module configuration using atomic upsert.

        Uses PostgreSQL INSERT ... ON CONFLICT DO NOTHING for atomic,
        race-condition-free module creation. This avoids TOCTOU issues
        and eliminates unnecessary IntegrityError exceptions under
        concurrent load.

        Args:
            organization_id: Keycloak organization UUID
            module_name: Module to get or create

        Returns:
            OrganizationModule record

        Raises:
            RuntimeError: If module cannot be ensured (database issue)
        """
        # Atomic upsert - no race condition, no wasted rollbacks
        stmt = (
            insert(OrganizationModule)
            .values(
                organization_id=organization_id,
                module_name=module_name,
                enabled=False,
            )
            .on_conflict_do_nothing(index_elements=["organization_id", "module_name"])
        )

        await self._execute_upsert(
            stmt,
            "get or create module",
            {
                "organization_id": organization_id,
                "module_name": module_name.value,
            },
        )

        # Fetch guaranteed-to-exist record
        result = await self.db.execute(
            select(OrganizationModule).filter(
                OrganizationModule.organization_id == organization_id,
                OrganizationModule.module_name == module_name,
            )
        )
        module = result.scalar_one_or_none()

        if not module:
            # Should never happen - indicates serious database issue
            raise RuntimeError(f"Failed to ensure module {module_name} exists for organization {organization_id}")

        return module

    async def get_all_organization_modules(self, organization_id: str) -> list[OrganizationModule]:
        """Get all modules for an organization.

        Ensures all module types exist for the organization using bulk upsert.
        Optimized to use only 2 DB calls instead of N+1.

        Args:
            organization_id: Keycloak organization UUID

        Returns:
            List of all OrganizationModule records for the organization
        """
        # Bulk upsert - insert all missing modules in one query
        # This is more efficient than calling get_or_create_module in a loop
        values = [
            {
                "organization_id": organization_id,
                "module_name": module_name,
                "enabled": False,
            }
            for module_name in ModuleName
        ]

        stmt = (
            insert(OrganizationModule)
            .values(values)
            .on_conflict_do_nothing(index_elements=["organization_id", "module_name"])
        )

        await self._execute_upsert(
            stmt,
            "ensure modules exist",
            {"organization_id": organization_id},
        )

        # Fetch all modules in one query (2 DB calls total: 1 upsert + 1 select)
        result = await self.db.execute(
            select(OrganizationModule).filter(OrganizationModule.organization_id == organization_id)
        )
        return list(result.scalars().all())

    async def update_module_config(
        self,
        organization_id: str,
        module_name: ModuleName,
        enabled: bool | None = None,
    ) -> OrganizationModule:
        """Update module configuration (enabled/disabled).

        Uses an atomic INSERT ... ON CONFLICT DO UPDATE so concurrent callers
        can't race into a unique-constraint violation (previously two tasks
        could both observe "no row" and both try to INSERT).

        Args:
            organization_id: Keycloak organization UUID
            module_name: Module to update
            enabled: Whether the module should be enabled

        Returns:
            Updated OrganizationModule record
        """
        insert_stmt = insert(OrganizationModule).values(
            organization_id=organization_id,
            module_name=module_name,
            enabled=enabled if enabled is not None else False,
        )

        if enabled is None:
            # No change requested → just ensure the row exists
            stmt = insert_stmt.on_conflict_do_nothing(index_elements=["organization_id", "module_name"])
        else:
            stmt = insert_stmt.on_conflict_do_update(
                index_elements=["organization_id", "module_name"],
                set_={"enabled": enabled},
            )

        await self._execute_upsert(
            stmt,
            "update module config",
            {
                "organization_id": organization_id,
                "module_name": module_name.value,
                "enabled": enabled,
            },
        )

        # The upsert was issued via raw SQL, so any previously-loaded
        # OrganizationModule instance in the session is stale. populate_existing
        # refreshes only the rows returned by this query, avoiding the session-wide
        # side effects of expire_all().
        result = await self.db.execute(
            select(OrganizationModule)
            .filter(
                OrganizationModule.organization_id == organization_id,
                OrganizationModule.module_name == module_name,
            )
            .execution_options(populate_existing=True)
        )
        module = result.scalar_one()
        return module
