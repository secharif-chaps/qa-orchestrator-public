"""Database models for global-service.

This package contains SQLAlchemy models for the global_schema,
which stores organization-scoped resources shared across modules.
"""

from app.models.folder import Folder, FolderItem, FolderShare, ItemType, ShareRole
from app.models.organization import (
    FeatureFlag,
    ModuleName,
    Organization,
    OrganizationFeatureFlag,
    OrganizationModule,
    ReferenceType,
    TokenLock,
    TokenLockStatus,
    TokenTransaction,
    TransactionType,
)
from app.models.user_folder_favorite import UserFolderFavorite
from app.models.user_preferences import UserPreferences

__all__ = [
    # Models
    "Organization",
    "TokenTransaction",
    "OrganizationModule",
    "OrganizationFeatureFlag",
    "TokenLock",
    "UserPreferences",
    "Folder",
    "FolderItem",
    "FolderShare",
    "ShareRole",
    "UserFolderFavorite",
    # Enums
    "ModuleName",
    "TransactionType",
    "ReferenceType",
    "FeatureFlag",
    "TokenLockStatus",
    "ItemType",
]
