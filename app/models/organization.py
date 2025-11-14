"""Organization-related database models.

This module contains models for Keycloak organization-based multi-tenancy.
Organizations are managed in Keycloak, not in the database.
"""

from sqlalchemy import Column, Integer, String, Boolean, DateTime, Enum as SQLEnum, UniqueConstraint
from sqlalchemy.sql import func
from enum import Enum
from app.database import Base


class ModuleName(str, Enum):
    """Available modules for token-based feature gating."""
    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"


class OrganizationModule(Base):
    """Module configuration and token management per organization.

    Tracks feature module enablement and token consumption for each
    Keycloak organization. Organization membership is managed in Keycloak,
    not in this database.
    """
    __tablename__ = "organization_modules"

    id = Column(Integer, primary_key=True, index=True)
    organization_id = Column(String, nullable=False, index=True)  # Keycloak organization UUID
    module_name = Column(
        SQLEnum(ModuleName, name='modulename', values_callable=lambda x: [e.value for e in x]),
        nullable=False
    )
    enabled = Column(Boolean, default=False, nullable=False)
    token_count = Column(Integer, default=0, nullable=False)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

    # Constraints
    __table_args__ = (
        UniqueConstraint('organization_id', 'module_name', name='uq_organization_modules_organization_module'),
    )
