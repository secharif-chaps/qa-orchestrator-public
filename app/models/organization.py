"""Organization-related database models.

This module contains models for Keycloak organization-based multi-tenancy:
- Organization: Organization-level settings including global token balance
- OrganizationModule: Module configuration (enabled/disabled) per organization
- TokenTransaction: Audit log for all token operations

Organizations are managed in Keycloak, not in the database. The Organization
table stores application-specific settings tied to Keycloak organization UUIDs.
"""

from sqlalchemy import (
    Column,
    Integer,
    String,
    Boolean,
    DateTime,
    Enum as SQLEnum,
    UniqueConstraint,
    ForeignKey,
    Index,
)
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from enum import Enum
from app.database import Base


class ModuleName(str, Enum):
    """Available modules for feature gating.

    Valid modules are: screen, target, explore.
    Note: 'stream' module has been removed from the system.
    """
    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"


class TransactionType(str, Enum):
    """Token transaction types for audit trail.

    Attributes:
        add: Manual token addition by admin
        consume: Token usage (company creation, etc.)
        adjustment: System adjustments (migration, corrections)
    """
    add = "add"
    consume = "consume"
    adjustment = "adjustment"


class ReferenceType(str, Enum):
    """Reference types for token transactions.

    Indicates what triggered the token transaction.

    Attributes:
        company: Company creation
        csv_import: Bulk CSV import
        manual: Manual admin operation
        system: System operation (migration, etc.)
    """
    company = "company"
    csv_import = "csv_import"
    manual = "manual"
    system = "system"


class Organization(Base):
    """Organization-level settings for Keycloak organizations.

    Stores application-specific settings tied to Keycloak organization UUIDs.
    Organization records are auto-created when first token operation occurs
    (lazy initialization).

    Attributes:
        organization_id: Keycloak organization UUID (primary key)
        token_balance: Global token balance for the organization
        created_at: Record creation timestamp
        updated_at: Last update timestamp
        transactions: Related TokenTransaction records
    """
    __tablename__ = "organizations"

    # Primary key is the Keycloak organization UUID
    organization_id = Column(String, primary_key=True, index=True)

    # Global token balance
    token_balance = Column(Integer, default=0, nullable=False)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

    # Relationship to token transactions
    transactions = relationship(
        "TokenTransaction",
        back_populates="organization",
        cascade="all, delete-orphan",
        order_by="TokenTransaction.created_at.desc()",
    )


class TokenTransaction(Base):
    """Audit log for all token operations.

    Records all token additions, consumptions, and adjustments for
    complete audit trail. Each record captures the transaction details
    and the resulting balance after the operation.

    Attributes:
        id: Auto-incrementing primary key
        organization_id: Foreign key to Organization
        amount: Token amount (+/- for add/consume)
        balance_after: Balance after this transaction
        transaction_type: Type of transaction (add, consume, adjustment)
        reference_type: What triggered the transaction (company, csv_import, manual, system)
        reference_id: Optional ID of the referenced entity (e.g., company_id)
        created_at: Transaction timestamp
        created_by: Keycloak user ID who initiated the transaction
        organization: Relationship to parent Organization
    """
    __tablename__ = "token_transactions"

    id = Column(Integer, primary_key=True, index=True)

    # Foreign key to organization
    organization_id = Column(
        String,
        ForeignKey("organizations.organization_id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )

    # Transaction details
    amount = Column(Integer, nullable=False)  # Positive for add, negative for consume
    balance_after = Column(Integer, nullable=False)

    # Transaction type enum
    transaction_type = Column(
        SQLEnum(
            TransactionType,
            name="transaction_type",
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )

    # Reference type enum
    reference_type = Column(
        SQLEnum(
            ReferenceType,
            name="reference_type",
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )

    # Optional reference to the triggering entity
    reference_id = Column(String, nullable=True)

    # Audit fields
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    created_by = Column(String, nullable=False)  # Keycloak user ID

    # Relationship to organization
    organization = relationship("Organization", back_populates="transactions")

    # Composite index for efficient history queries
    __table_args__ = (
        Index("ix_token_transactions_org_created", "organization_id", "created_at"),
    )


class OrganizationModule(Base):
    """Module configuration per organization.

    Tracks feature module enablement for each Keycloak organization.
    Token management is handled globally in the Organization table -
    this model only tracks enabled/disabled state for each module.

    Attributes:
        id: Auto-incrementing primary key
        organization_id: Keycloak organization UUID
        module_name: Module identifier (screen, target, explore)
        enabled: Whether the module is enabled for this organization
        created_at: Record creation timestamp
        updated_at: Last update timestamp
    """
    __tablename__ = "organization_modules"

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String, nullable=False, index=True)  # Keycloak organization UUID
    module_name = Column(
        SQLEnum(ModuleName, name='modulename', values_callable=lambda x: [e.value for e in x]),
        nullable=False
    )
    enabled = Column(Boolean, default=False, nullable=False)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

    # Constraints
    __table_args__ = (
        UniqueConstraint('organization_id', 'module_name', name='uq_organization_modules_organization_module'),
    )
