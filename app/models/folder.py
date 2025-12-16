"""Folder and FolderShare models for organizing companies and other items.

This module defines:
- Folder: Container for organizing items (companies, contacts, etc.)
- FolderItem: Junction table linking folders to items
- FolderShare: User-level sharing with Reader/Writer roles
- ShareRole: Enum for share permission levels
"""

import enum
from sqlalchemy import (
    Column,
    String,
    Boolean,
    DateTime,
    ForeignKey,
    Integer,
    Enum,
    UniqueConstraint,
    text,
)
from sqlalchemy.dialects.postgresql import UUID, ARRAY
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.database import Base


class ShareRole(enum.Enum):
    """Share role enum for folder sharing permissions.

    Attributes:
        reader: Can view folder and its contents, but cannot modify
        writer: Can view folder and add items (if has module permission),
                but cannot edit/delete folder or manage sharing

    Note: Enum names must be lowercase to match PostgreSQL enum values.
    """
    reader = "reader"
    writer = "writer"


class Folder(Base):
    """Folder model for organizing items.

    Folders are private by default - only the owner and explicitly shared users
    can see a folder. Sharing is managed through the FolderShare model.

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
        is_orphaned: Flag indicating owner has left org (requires admin action)
        created_at: Creation timestamp
        updated_at: Last update timestamp
        items: Related FolderItem records
        shares: Related FolderShare records for user-level sharing
    """
    __tablename__ = "folders"

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))

    # Organization-based multi-tenancy via Keycloak Organizations
    organization_id = Column(String, index=True, nullable=False)  # Keycloak organization UUID

    # Owner fields - Keycloak user identification
    owner_id = Column(String, index=True, nullable=True)  # Keycloak user UUID (from JWT sub claim)
    owner = Column(String, nullable=True)  # Username for display (denormalized, kept for backward compat)

    name = Column(String, nullable=False)
    color = Column(String, nullable=True)
    icon = Column(String, nullable=True)
    tags = Column(ARRAY(String), server_default=text("'{}'::text[]"), nullable=False)
    is_deleted = Column(Boolean, server_default=text("false"), nullable=False)

    # Orphaned flag - set when folder owner leaves organization or is disabled
    # Requires admin action to claim or reassign
    is_orphaned = Column(Boolean, server_default=text("false"), nullable=False)

    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationships
    items = relationship("FolderItem", back_populates="folder", cascade="all, delete-orphan")
    shares = relationship("FolderShare", back_populates="folder", cascade="all, delete-orphan")


class FolderItem(Base):
    """Junction table linking folders to items (companies, contacts, etc.).

    Attributes:
        id: Unique identifier (UUID)
        folder_id: Foreign key to parent folder
        item_id: ID of the linked item
        item_type: Type of item ('company', 'contact', etc.)
        position: Optional position for ordering
        added_at: Timestamp when item was added
        owner: Username who added the item (denormalized)
    """
    __tablename__ = "folder_items"

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))
    folder_id = Column(UUID(as_uuid=True), ForeignKey("folders.id", ondelete="CASCADE"), nullable=False)
    item_id = Column(String, nullable=False)
    item_type = Column(String, nullable=False)  # 'company', 'contact', etc.
    position = Column(Integer, nullable=True)
    added_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    owner = Column(String, nullable=True)

    # Relationships
    folder = relationship("Folder", back_populates="items")


class FolderShare(Base):
    """Junction table for user-level folder sharing.

    Each record represents one user's access to a folder with a specific role.
    A user can only have one share per folder (unique constraint on folder_id + user_id).

    Attributes:
        id: Unique identifier (UUID)
        folder_id: Foreign key to the shared folder
        user_id: Keycloak user UUID who has access
        user_username: Username for display (denormalized)
        role: ShareRole enum (reader/writer)
        created_at: Timestamp when share was created
        folder: Relationship to parent Folder
    """
    __tablename__ = "folder_shares"

    id = Column(
        UUID(as_uuid=True),
        primary_key=True,
        server_default=text("gen_random_uuid()")
    )

    # Folder reference with cascade delete
    folder_id = Column(
        UUID(as_uuid=True),
        ForeignKey("folders.id", ondelete="CASCADE"),
        nullable=False,
        index=True
    )

    # User reference (Keycloak user ID from JWT sub claim)
    user_id = Column(String, nullable=False, index=True)

    # Username for display (denormalized to avoid Keycloak lookups)
    user_username = Column(String, nullable=False)

    # Share role - reader can only view, writer can add items
    role = Column(
        Enum(ShareRole, name="share_role", create_type=False),
        nullable=False,
        default=ShareRole.reader
    )

    # Timestamp when share was created
    created_at = Column(
        DateTime(timezone=True),
        server_default=func.now(),
        nullable=False
    )

    # Relationship to parent folder
    folder = relationship("Folder", back_populates="shares")

    # Ensure a user can only have one share per folder
    __table_args__ = (
        UniqueConstraint('folder_id', 'user_id', name='uq_folder_share_folder_user'),
    )
