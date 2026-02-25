"""Token management service for global organization token balance.

This module provides the TokenManager class for managing organization tokens
with a global balance approach. All token operations create transaction
records for complete audit trail.

Key operations:
- get_balance(): Get current token balance for organization
- add_tokens(): Add tokens to organization balance (admin operation)
- consume_tokens(): Consume tokens for operations (with module enablement check)
- get_transaction_history(): Query transaction history with filters
"""

from datetime import datetime
from typing import List, Optional

from sqlalchemy.dialects.postgresql import insert
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from sqlalchemy.sql import Select

from app.core.logging_config import get_logger
from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)
from app.services.exceptions import (
    InsufficientTokensException,
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
        result = await self.db.execute(
            select(Organization).filter(Organization.organization_id == org_id)
        )
        org = result.scalar_one_or_none()

        if not org:
            # Should never happen - indicates serious database issue
            raise RuntimeError(
                f"Failed to ensure organization {org_id} exists"
            )

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
            select(Organization)
            .filter(Organization.organization_id == org_id)
            .with_for_update()
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
        reference_id: Optional[str],
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
            select(Organization)
            .filter(Organization.organization_id == org_id)
            .with_for_update()
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

    def _build_transaction_filters(
        self,
        query: Select,
        org_id: str,
        transaction_type: Optional[TransactionType] = None,
        reference_type: Optional[ReferenceType] = None,
        date_from: Optional[datetime] = None,
        date_to: Optional[datetime] = None,
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
        transaction_type: Optional[TransactionType] = None,
        reference_type: Optional[ReferenceType] = None,
        date_from: Optional[datetime] = None,
        date_to: Optional[datetime] = None,
        page: int = 1,
        size: int = 50,
    ) -> List[TokenTransaction]:
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
        query = self._build_transaction_filters(
            query, org_id, transaction_type, reference_type, date_from, date_to
        )

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
        transaction_type: Optional[TransactionType] = None,
        reference_type: Optional[ReferenceType] = None,
        date_from: Optional[datetime] = None,
        date_to: Optional[datetime] = None,
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
        from sqlalchemy import func

        query = select(func.count()).select_from(TokenTransaction)
        query = self._build_transaction_filters(
            query, org_id, transaction_type, reference_type, date_from, date_to
        )

        result = await self.db.execute(query)
        return result.scalar() or 0

    # Module management methods

    async def get_or_create_module(
        self, organization_id: str, module_name: ModuleName
    ) -> OrganizationModule:
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
            .on_conflict_do_nothing(
                index_elements=["organization_id", "module_name"]
            )
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
            raise RuntimeError(
                f"Failed to ensure module {module_name} exists "
                f"for organization {organization_id}"
            )

        return module

    async def get_all_organization_modules(
        self, organization_id: str
    ) -> List[OrganizationModule]:
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
            .on_conflict_do_nothing(
                index_elements=["organization_id", "module_name"]
            )
        )

        await self._execute_upsert(
            stmt,
            "ensure modules exist",
            {"organization_id": organization_id},
        )

        # Fetch all modules in one query (2 DB calls total: 1 upsert + 1 select)
        result = await self.db.execute(
            select(OrganizationModule).filter(
                OrganizationModule.organization_id == organization_id
            )
        )
        return list(result.scalars().all())

    async def update_module_config(
        self,
        organization_id: str,
        module_name: ModuleName,
        enabled: Optional[bool] = None,
    ) -> OrganizationModule:
        """Update module configuration (enabled/disabled).

        Uses row-level locking within a single transaction to prevent
        race conditions during concurrent updates.

        Args:
            organization_id: Keycloak organization UUID
            module_name: Module to update
            enabled: Whether the module should be enabled

        Returns:
            Updated OrganizationModule record
        """
        # Lock module row for update to prevent race conditions
        result = await self.db.execute(
            select(OrganizationModule)
            .filter(
                OrganizationModule.organization_id == organization_id,
                OrganizationModule.module_name == module_name,
            )
            .with_for_update()
        )
        module = result.scalar_one_or_none()

        if not module:
            # Create if doesn't exist (within same transaction)
            module = OrganizationModule(
                organization_id=organization_id,
                module_name=module_name,
                enabled=enabled if enabled is not None else False,
            )
            self.db.add(module)
        elif enabled is not None:
            module.enabled = enabled

        try:
            await self.db.commit()
            await self.db.refresh(module)
        except SQLAlchemyError as e:
            await self.db.rollback()
            logger.error(
                f"Failed to update module config: {e}",
                extra={
                    "organization_id": organization_id,
                    "module_name": module_name.value,
                    "enabled": enabled,
                    "error": str(e),
                },
            )
            raise

        return module
