"""User-specific folder favorites model.

This module defines the junction table for user-scoped folder favorites.
Each user can have their own list of favorite folders, independent of other users.
"""

from sqlalchemy import Column, DateTime, ForeignKey, String, UniqueConstraint
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.sql import func, text

from app.database import Base


class UserFolderFavorite(Base):
    """Junction table for user-specific folder favorites.

    This table stores which folders each user has marked as favorite.
    Each row represents one user's favorite relationship with one folder.

    Attributes:
        id: Unique identifier for the favorite record
        user_id: Keycloak user UUID who favorited the folder
        folder_id: UUID of the favorited folder
        created_at: Timestamp when the folder was favorited
    """

    __tablename__ = "user_folder_favorites"

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))

    # User reference (Keycloak user ID from JWT sub claim)
    user_id = Column(String, nullable=False, index=True)

    # Folder reference with cascade delete
    folder_id = Column(UUID(as_uuid=True), ForeignKey("folders.id", ondelete="CASCADE"), nullable=False, index=True)

    # Timestamp when favorited
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Ensure a user can only favorite a folder once
    __table_args__ = (UniqueConstraint("user_id", "folder_id", name="uq_user_folder_favorite"),)
