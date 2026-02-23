"""Folder model for organizing items in global_schema.

This module defines only the Folder table.
FolderItem and FolderShare will be added in US-3.3 and US-3.4.
"""

from sqlalchemy import Column, String, Boolean, DateTime, text
from sqlalchemy.dialects.postgresql import UUID, ARRAY
from sqlalchemy.sql import func

from app.database import GlobalBase, GLOBAL_SCHEMA


class Folder(GlobalBase):
    """Folder model for organizing items.

    Folders are private by default - only the owner and explicitly shared users
    can see a folder. Sharing will be managed through FolderShare (US-3.3).

    Attributes:
        id: Unique identifier (UUID)
        organization_id: Keycloak organization UUID for multi-tenancy
        owner_id: Keycloak user UUID of the folder owner
        owner: Username for display (denormalized)
        name: Folder name
        color: Optional color for visual customization
        icon: Optional icon for visual customization
        tags: Array of tag strings
        is_deleted: Soft delete flag
        is_orphaned: Flag indicating owner has left org
        created_at: Creation timestamp
        updated_at: Last update timestamp
    """

    __tablename__ = "folders"
    __table_args__ = {"schema": GLOBAL_SCHEMA}

    id = Column(
        UUID(as_uuid=True),
        primary_key=True,
        server_default=text("gen_random_uuid()"),
    )

    # Organization-based multi-tenancy via Keycloak Organizations
    organization_id = Column(String, index=True, nullable=False)

    # Owner fields - Keycloak user identification
    owner_id = Column(String, index=True, nullable=True)
    owner = Column(String, nullable=True)

    name = Column(String, nullable=False)
    color = Column(String, nullable=True)
    icon = Column(String, nullable=True)
    tags = Column(
        ARRAY(String),
        server_default=text("'{}'::text[]"),
        nullable=False,
    )
    is_deleted = Column(
        Boolean,
        server_default=text("false"),
        nullable=False,
    )
    is_orphaned = Column(
        Boolean,
        server_default=text("false"),
        nullable=False,
    )

    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
    updated_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False,
    )
