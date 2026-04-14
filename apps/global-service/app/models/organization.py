"""Organization-related database models for global-service.

This module contains models for Keycloak organization-based multi-tenancy:
- Organization: Organization-level settings including global token balance
- OrganizationModule: Module configuration (enabled/disabled) per organization
- OrganizationFeatureFlag: Feature flag configuration (add-on capabilities) per organization
- TokenTransaction: Audit log for all token operations
- TokenLock: Token reservation for lock/unlock pattern

Organizations are managed in Keycloak, not in the database. The Organization
table stores application-specific settings tied to Keycloak organization UUIDs.

All models are stored in the global_schema for organization-scoped resources.
"""

from enum import StrEnum

from sqlalchemy import (
    JSON,
    Boolean,
    CheckConstraint,
    Column,
    DateTime,
    ForeignKey,
    Index,
    Integer,
    String,
    UniqueConstraint,
)
from sqlalchemy import (
    Enum as SQLEnum,
)
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import GLOBAL_SCHEMA, GlobalBase


class ModuleName(StrEnum):
    """Available core modules for feature gating.

    Valid modules are: screen, target, explore, stream.
    Note: 'translation' is now a FeatureFlag, not a core module.
    """

    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"
    STREAM = "stream"


class TransactionType(StrEnum):
    """Token transaction types for audit trail.

    Attributes:
        add: Manual token addition by admin
        consume: Token usage (company creation, etc.)
        adjustment: System adjustments (migration, corrections)
    """

    add = "add"
    consume = "consume"
    adjustment = "adjustment"


class ReferenceType(StrEnum):
    """Reference types for token transactions.

    Indicates what triggered the token transaction.

    Attributes:
        company: Company creation
        csv_import: Bulk CSV import
        refresh: Company data refresh
        manual: Manual admin operation
        system: System operation (migration, etc.)
    """

    company = "company"
    csv_import = "csv_import"
    refresh = "refresh"
    manual = "manual"
    system = "system"


class FeatureFlag(StrEnum):
    """Organization-level feature flags for add-on capabilities.

    Feature flags are OFF by default. Unlike core modules (screen, target, explore),
    feature flags represent optional enhancements that can be enabled per organization.

    Attributes:
        TRANSLATION: Translation feature for translating company data
        DISCOVER: External Discover dashboard integration with configurable URL
        PAPPERS: Pappers API integration for company data enrichment
        WORLDCHECK: WorldCheck One API for due diligence screening (sanctions, PEP, adverse media)
        STREAM: Multi-channel event distribution (Teams, Slack, Webhook)
    """

    TRANSLATION = "translation"
    DISCOVER = "discover"
    PAPPERS = "pappers"
    WORLDCHECK = "worldcheck"
    STREAM = "stream"


class TokenLockStatus(StrEnum):
    """Status of a token lock reservation.

    Attributes:
        locked: Tokens are reserved, awaiting confirmation or release
        confirmed: Lock confirmed, tokens consumed from balance
        released: Lock released, tokens returned to available balance
        expired: Lock expired without confirmation (auto-released)
    """

    locked = "locked"
    confirmed = "confirmed"
    released = "released"
    expired = "expired"


class Organization(GlobalBase):
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
    __table_args__ = {"schema": GLOBAL_SCHEMA}

    # Primary key is the Keycloak organization UUID
    organization_id = Column(String, primary_key=True, index=True)

    # Global token balance
    token_balance = Column(Integer, default=0, nullable=False)

    # Timestamps
    created_at = Column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

    # Relationship to token transactions
    transactions = relationship(
        "TokenTransaction",
        back_populates="organization",
        cascade="all, delete-orphan",
        order_by="TokenTransaction.created_at.desc()",
    )

    # Relationship to token locks
    token_locks = relationship(
        "TokenLock",
        back_populates="organization",
        cascade="all, delete-orphan",
        order_by="TokenLock.locked_at.desc()",
    )


class TokenTransaction(GlobalBase):
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
    __table_args__ = (
        Index(
            "ix_token_transactions_org_created",
            "organization_id",
            "created_at",
        ),
        {"schema": GLOBAL_SCHEMA},
    )

    id = Column(Integer, primary_key=True, index=True)

    # Foreign key to organization (schema-qualified)
    organization_id = Column(
        String,
        ForeignKey(
            f"{GLOBAL_SCHEMA}.organizations.organization_id", ondelete="CASCADE"
        ),
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
            schema=GLOBAL_SCHEMA,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )

    # Reference type enum
    reference_type = Column(
        SQLEnum(
            ReferenceType,
            name="reference_type",
            schema=GLOBAL_SCHEMA,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )

    # Optional reference to the triggering entity
    reference_id = Column(String, nullable=True)

    # Audit fields
    created_at = Column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    created_by = Column(String, nullable=False)  # Keycloak user ID

    # Relationship to organization
    organization = relationship("Organization", back_populates="transactions")


class OrganizationModule(GlobalBase):
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
    __table_args__ = (
        UniqueConstraint(
            "organization_id",
            "module_name",
            name="uq_organization_modules_organization_module",
        ),
        {"schema": GLOBAL_SCHEMA},
    )

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(
        String, nullable=False, index=True
    )  # Keycloak organization UUID
    module_name = Column(
        SQLEnum(
            ModuleName,
            name="modulename",
            schema=GLOBAL_SCHEMA,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )
    enabled = Column(Boolean, default=False, nullable=False)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class OrganizationFeatureFlag(GlobalBase):
    """Organization feature flag configuration.

    Tracks which add-on features are enabled for each organization.
    Features are OFF by default - only enabled flags are stored.

    TODO(TAR-1253): No REST API endpoints exist for this model yet.
    Only used by scripts/migrate_organization_data.py for initial seeding.
    Implement CRUD endpoints when feature flag management is needed.

    Attributes:
        id: Auto-incrementing primary key
        organization_id: Keycloak organization UUID
        flag: The feature flag enum value
        enabled: Whether the feature is enabled
        enabled_at: Timestamp when feature was enabled
        config: Optional JSON configuration for the feature
        created_at: Record creation timestamp
        updated_at: Last update timestamp
    """

    __tablename__ = "organization_feature_flags"
    __table_args__ = (
        UniqueConstraint(
            "organization_id", "flag", name="uq_organization_feature_flags_org_flag"
        ),
        Index(
            "ix_organization_feature_flags_org_enabled", "organization_id", "enabled"
        ),
        {"schema": GLOBAL_SCHEMA},
    )

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String, nullable=False, index=True)
    flag = Column(
        SQLEnum(
            FeatureFlag,
            name="featureflag",
            schema=GLOBAL_SCHEMA,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )
    enabled = Column(Boolean, default=False, nullable=False)
    enabled_at = Column(DateTime(timezone=True), nullable=True)
    config = Column(JSON, nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())


class TokenLock(GlobalBase):
    """Token reservation for the lock/unlock pattern.

    Allows pre-reserving tokens before an operation (lock), then
    confirming consumption (confirm) or releasing them (release).
    Locks that exceed their timeout are auto-expired.

    Designed for use with SELECT FOR UPDATE to prevent race conditions.

    Attributes:
        id: UUID primary key
        organization_id: Foreign key to Organization
        amount: Number of tokens reserved
        module: Module that requested the lock (e.g. "screen")
        user_id: Keycloak user ID who initiated the lock
        correlation_id: Unique ID for tracing the lock lifecycle
        reference_id: Optional reference filled by backend (e.g. company_id)
        status: Current lock status (locked, confirmed, released, expired)
        locked_at: When the lock was created
        expires_at: When the lock will auto-expire
        settled_at: When the lock was confirmed, released, or expired
        organization: Relationship to parent Organization
    """

    __tablename__ = "token_locks"
    __table_args__ = (
        CheckConstraint("amount > 0", name="ck_token_locks_amount_positive"),
        UniqueConstraint("correlation_id", name="uq_token_locks_correlation_id"),
        Index("ix_token_locks_org_status", "organization_id", "status"),
        Index("ix_token_locks_expires_at", "expires_at"),
        {"schema": GLOBAL_SCHEMA},
    )

    id = Column(
        UUID(as_uuid=True),
        primary_key=True,
        server_default=func.gen_random_uuid(),
    )

    organization_id = Column(
        String,
        ForeignKey(
            f"{GLOBAL_SCHEMA}.organizations.organization_id", ondelete="CASCADE"
        ),
        nullable=False,
    )

    amount = Column(Integer, nullable=False)
    module = Column(
        SQLEnum(
            ModuleName,
            name="modulename",
            schema=GLOBAL_SCHEMA,
            create_type=False,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
    )
    user_id = Column(String, nullable=False)
    correlation_id = Column(String, nullable=False)
    reference_id = Column(String, nullable=True)

    status = Column(
        SQLEnum(
            TokenLockStatus,
            name="token_lock_status",
            schema=GLOBAL_SCHEMA,
            values_callable=lambda x: [e.value for e in x],
        ),
        nullable=False,
        server_default=TokenLockStatus.locked.value,
    )

    locked_at = Column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    expires_at = Column(DateTime(timezone=True), nullable=False)
    settled_at = Column(DateTime(timezone=True), nullable=True)

    organization = relationship("Organization", back_populates="token_locks")
