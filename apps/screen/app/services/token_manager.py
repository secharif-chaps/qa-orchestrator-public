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

from fastapi import HTTPException, status
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models.organization import (
    ModuleName,
    Organization,
    OrganizationModule,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)
from app.schemas.token import TokenError

logger = get_logger(__name__)

# Token cost for company creation - each company consumes 35 tokens
TOKENS_PER_COMPANY = 35


class InsufficientTokensException(HTTPException):
    """Exception raised when organization has insufficient token balance.

    Returns HTTP 402 Payment Required with details about current balance
    and required tokens.
    """

    def __init__(self, current_balance: int, required_tokens: int):
        error_detail = TokenError(
            message=f"Insufficient tokens. Current balance: {current_balance}, required: {required_tokens}",
            current_balance=current_balance,
            required_tokens=required_tokens,
        )
        super().__init__(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=error_detail.model_dump(),
        )


class ModuleNotEnabledException(HTTPException):
    """Exception raised when module is not enabled for organization.

    Returns HTTP 403 Forbidden indicating the module must be enabled
    before token consumption is allowed.
    """

    def __init__(self, module_name: ModuleName):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Module {module_name.value} is not enabled for this organization",
        )


class TokenManager:
    """Service for managing organization token balances.

    Provides global token balance operations with transaction history
    for audit purposes. Uses row-level locking to prevent race conditions
    during concurrent token operations.

    Attributes:
        db: SQLAlchemy database session
    """

    def __init__(self, db: Session):
        """Initialize TokenManager with database session.

        Args:
            db: SQLAlchemy database session for token operations
        """
        self.db = db

    def _ensure_organization_exists(self, org_id: str) -> Organization:
        """Ensure organization record exists, creating it if necessary.

        Uses lazy initialization - organization records are created on first
        token operation if they don't exist. Handles race conditions where
        multiple processes might try to create the same organization.

        Args:
            org_id: Keycloak organization UUID

        Returns:
            Organization record (existing or newly created)
        """
        org = (
            self.db.query(Organization)
            .filter(Organization.organization_id == org_id)
            .first()
        )

        if not org:
            org = Organization(organization_id=org_id, token_balance=0)
            self.db.add(org)
            try:
                self.db.commit()
            except IntegrityError:
                self.db.rollback()
                # Handle race condition - another process created it
                org = (
                    self.db.query(Organization)
                    .filter(Organization.organization_id == org_id)
                    .first()
                )
                if not org:
                    raise
            self.db.refresh(org)

        return org

    def _check_module_enabled(self, org_id: str, module_name: ModuleName) -> None:
        """Check if module is enabled for organization.

        Args:
            org_id: Keycloak organization UUID
            module_name: Module to check

        Raises:
            ModuleNotEnabledException: If module is not enabled
        """
        module = (
            self.db.query(OrganizationModule)
            .filter(
                OrganizationModule.organization_id == org_id,
                OrganizationModule.module_name == module_name,
            )
            .first()
        )

        if not module or not module.enabled:
            raise ModuleNotEnabledException(module_name)

    def get_balance(self, org_id: str) -> int:
        """Get current token balance for organization.

        Creates organization record via lazy initialization if it doesn't exist.

        Args:
            org_id: Keycloak organization UUID

        Returns:
            Current token balance (0 for new organizations)
        """
        org = self._ensure_organization_exists(org_id)
        return org.token_balance

    def add_tokens(
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
        self._ensure_organization_exists(org_id)

        # Lock organization row for update to prevent race conditions
        org = (
            self.db.query(Organization)
            .filter(Organization.organization_id == org_id)
            .with_for_update()
            .first()
        )

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

        self.db.commit()
        self.db.refresh(org)

        logger.info(
            f"Added {amount} tokens to organization {org_id}. New balance: {new_balance}",
            extra={"organization_id": org_id, "amount": amount, "user_id": user_id},
        )

        return org

    def consume_tokens(
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

        # Check module is enabled first
        self._check_module_enabled(org_id, module_name)

        # Ensure organization exists first
        self._ensure_organization_exists(org_id)

        # Lock organization row for update to prevent race conditions
        org = (
            self.db.query(Organization)
            .filter(Organization.organization_id == org_id)
            .with_for_update()
            .first()
        )

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

        self.db.commit()
        self.db.refresh(org)

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

    def get_transaction_history(
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
        query = self.db.query(TokenTransaction).filter(
            TokenTransaction.organization_id == org_id
        )

        # Apply optional filters
        if transaction_type is not None:
            query = query.filter(TokenTransaction.transaction_type == transaction_type)

        if reference_type is not None:
            query = query.filter(TokenTransaction.reference_type == reference_type)

        if date_from is not None:
            query = query.filter(TokenTransaction.created_at >= date_from)

        if date_to is not None:
            query = query.filter(TokenTransaction.created_at <= date_to)

        # Order by most recent first
        query = query.order_by(TokenTransaction.created_at.desc())

        # Apply pagination
        offset = (page - 1) * size
        query = query.offset(offset).limit(size)

        return query.all()

    def get_transaction_count(
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
        query = self.db.query(TokenTransaction).filter(
            TokenTransaction.organization_id == org_id
        )

        if transaction_type is not None:
            query = query.filter(TokenTransaction.transaction_type == transaction_type)

        if reference_type is not None:
            query = query.filter(TokenTransaction.reference_type == reference_type)

        if date_from is not None:
            query = query.filter(TokenTransaction.created_at >= date_from)

        if date_to is not None:
            query = query.filter(TokenTransaction.created_at <= date_to)

        return query.count()

    # Module management methods (kept for backward compatibility)

    def get_or_create_module(
        self, organization_id: str, module_name: ModuleName
    ) -> OrganizationModule:
        """Get or create a organization module configuration.

        Args:
            organization_id: Keycloak organization UUID
            module_name: Module to get or create

        Returns:
            OrganizationModule record
        """
        module = (
            self.db.query(OrganizationModule)
            .filter(
                OrganizationModule.organization_id == organization_id,
                OrganizationModule.module_name == module_name,
            )
            .first()
        )

        if not module:
            module = OrganizationModule(
                organization_id=organization_id,
                module_name=module_name,
                enabled=False,
            )
            self.db.add(module)
            try:
                self.db.commit()
            except IntegrityError:
                self.db.rollback()
                # Handle race condition
                module = (
                    self.db.query(OrganizationModule)
                    .filter(
                        OrganizationModule.organization_id == organization_id,
                        OrganizationModule.module_name == module_name,
                    )
                    .first()
                )
                if not module:
                    raise
            self.db.refresh(module)

        return module

    def get_all_organization_modules(
        self, organization_id: str
    ) -> List[OrganizationModule]:
        """Get all modules for an organization.

        Ensures all module types exist for the organization.

        Args:
            organization_id: Keycloak organization UUID

        Returns:
            List of all OrganizationModule records for the organization
        """
        # Ensure all modules exist
        for module_name in ModuleName:
            self.get_or_create_module(organization_id, module_name)

        return (
            self.db.query(OrganizationModule)
            .filter(OrganizationModule.organization_id == organization_id)
            .all()
        )

    def update_module_config(
        self,
        organization_id: str,
        module_name: ModuleName,
        enabled: Optional[bool] = None,
    ) -> OrganizationModule:
        """Update module configuration (enabled/disabled).

        Args:
            organization_id: Keycloak organization UUID
            module_name: Module to update
            enabled: Whether the module should be enabled

        Returns:
            Updated OrganizationModule record
        """
        module = self.get_or_create_module(organization_id, module_name)

        if enabled is not None:
            module.enabled = enabled

        self.db.commit()
        self.db.refresh(module)
        return module
