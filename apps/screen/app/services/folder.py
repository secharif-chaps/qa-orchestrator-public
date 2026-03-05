"""Folder service layer for folder operations, sharing, and access control.

This module provides business logic for:
- Folder CRUD operations
- Folder item management
- Folder sharing (share_folder, unshare_folder, get_folder_shares, update_share_role)
- Access control (has_folder_access, get_user_folder_role, is_folder_owner)
- User favorites
- Orphaned folder management
"""

from typing import List, Optional, Dict, Any, Set
from uuid import UUID
from sqlalchemy.orm import Session
from sqlalchemy import or_
from datetime import datetime, timezone
import logging

from app.models import Folder, FolderItem, Company, UserFolderFavorite
from app.models.folder import FolderShare, ShareRole
from app.schemas.folder import FolderCreate, FolderUpdate

logger = logging.getLogger(__name__)


def _user_is_manager(user_roles: List[str]) -> bool:
    """Check if user has manager permissions.

    A user is considered a manager if they have either:
    - organization.manage role (can manage their organization)
    - admin.organizations role (global admin)

    Args:
        user_roles: List of realm roles from Keycloak

    Returns:
        True if user is a manager, False otherwise
    """
    return 'organization.manage' in user_roles or 'admin.organizations' in user_roles


class FolderService:
    """Service class for folder operations."""

    # ==================== Folder CRUD Methods ====================

    @staticmethod
    def create_folder(
        db: Session,
        organization_id: str,
        owner_id: str,
        owner_username: str,
        folder_data: FolderCreate
    ) -> Folder:
        """Create a new folder.

        Args:
            db: Database session
            organization_id: Organization UUID for multi-tenancy
            owner_id: Keycloak user UUID of the folder owner
            owner_username: Username for display (denormalized)
            folder_data: Folder creation data

        Returns:
            Newly created Folder instance
        """
        folder = Folder(
            organization_id=organization_id,
            owner_id=owner_id,  # Keycloak user UUID
            owner=owner_username,  # Username for display
            name=folder_data.name,
            color=folder_data.color,
            icon=folder_data.icon,
            tags=folder_data.tags or []
        )
        db.add(folder)
        db.commit()
        db.refresh(folder)
        return folder

    @staticmethod
    def get_folder(
        db: Session,
        folder_id: UUID,
        organization_id: str,
        include_deleted: bool = False
    ) -> Optional[Folder]:
        """Get a folder by ID.

        Args:
            db: Database session
            folder_id: Folder UUID to retrieve
            organization_id: Organization UUID for access validation
            include_deleted: If True, include soft-deleted folders

        Returns:
            Folder instance if found, None otherwise
        """
        query = db.query(Folder).filter(
            Folder.id == folder_id,
            Folder.organization_id == organization_id
        )

        if not include_deleted:
            query = query.filter(~Folder.is_deleted)

        return query.first()

    @staticmethod
    def _get_folder_items_summary(
        db: Session,
        folder_id: UUID,
        item_archived_filter: bool = False
    ) -> List[Dict[str, Any]]:
        """Get complete items for a folder - returns dicts for internal use."""
        items = []
        folder_items = db.query(FolderItem).filter(
            FolderItem.folder_id == folder_id
        ).order_by(FolderItem.position.nullsfirst(), FolderItem.added_at).all()

        for item in folder_items:
            if item.item_type == 'company':
                try:
                    company_id = int(item.item_id)
                    company_query = db.query(Company).filter(Company.id == company_id)

                    # Apply the archived filter
                    company_query = company_query.filter(Company.is_deleted == item_archived_filter)

                    company = company_query.first()

                    if company:
                        items.append({
                            'id': str(item.item_id),
                            'type': item.item_type,
                            'position': item.position,
                            'added_at': item.added_at.isoformat() if item.added_at else None,
                            'name': company.name,
                            'website': company.website,
                            'created_at': company.created_at.isoformat() if company.created_at else None,
                            'owner': company.owner_username or 'Unknown',
                            'is_deleted': company.is_deleted
                        })
                except (ValueError, TypeError):
                    continue
            # Add support for other item types (contact, document) here in the future

        return items

    @staticmethod
    def get_folder_with_items(
        db: Session,
        folder_id: UUID,
        organization_id: str,
        item_archived_filter: bool = False
    ) -> Optional[Dict[str, Any]]:
        """Get folder with summary of its items."""
        folder = FolderService.get_folder(db, folder_id, organization_id)
        if not folder:
            return None

        # Get folder items with company details, applying filters
        items = FolderService._get_folder_items_summary(
            db,
            folder_id,
            item_archived_filter=item_archived_filter
        )

        return {
            'id': str(folder.id),
            'name': folder.name,
            'color': folder.color,
            'icon': folder.icon,
            'tags': folder.tags,
            'is_deleted': folder.is_deleted,
            'created_at': folder.created_at.isoformat() if folder.created_at else None,
            'updated_at': folder.updated_at.isoformat() if folder.updated_at else None,
            'owner': folder.owner,
            'organization_id': folder.organization_id,
            'items': items
            # Note: is_favorite is computed per-user and added by the endpoint
        }

    @staticmethod
    def list_folders(
        db: Session,
        organization_id: str,
        user_id: str,
        archived: bool = False,
        favorites_only: bool = False,
        username: str | None = None
    ) -> List[Folder]:
        """List folders accessible to a user (owned + shared).

        This method returns only folders that the user owns or has been explicitly
        shared with. It filters by organization and optionally by archived status
        or favorites.

        Args:
            db: Database session
            organization_id: Organization to filter by
            user_id: Current user ID for ownership and sharing checks
            archived: If True, show deleted folders; if False, show active folders
            favorites_only: If True, only show folders favorited by this user
            username: Optional username for legacy fallback when owner_id is NULL

        Returns:
            List of Folder instances the user has access to
        """
        from sqlalchemy import and_

        logger.debug(
            f"list_folders - organization_id: {organization_id}, "
            f"user_id: {user_id}, archived: {archived}, favorites_only: {favorites_only}"
        )

        # Build ownership conditions
        # Primary: match by owner_id
        # Fallback: match by owner (username) if owner_id is NULL (legacy data)
        ownership_conditions = [Folder.owner_id == user_id]
        if username:
            ownership_conditions.append(
                and_(Folder.owner_id.is_(None), Folder.owner == username)
            )

        # Build base query with ownership OR sharing filter
        # This uses a LEFT JOIN with folder_shares to include both owned and shared folders
        query = db.query(Folder).outerjoin(
            FolderShare,
            FolderShare.folder_id == Folder.id
        ).filter(
            Folder.organization_id == organization_id,
            or_(
                *ownership_conditions,  # User owns the folder (by ID or username)
                FolderShare.user_id == user_id  # User has been shared the folder
            )
        )

        # Apply archived filter
        if archived:
            logger.debug("Filtering for archived (deleted) folders")
            query = query.filter(Folder.is_deleted)
        else:
            logger.debug("Filtering for non-archived folders")
            query = query.filter(~Folder.is_deleted)

        # Apply favorites filter
        if favorites_only:
            logger.debug("Filtering for user's favorites only")
            query = query.join(
                UserFolderFavorite,
                (UserFolderFavorite.folder_id == Folder.id) &
                (UserFolderFavorite.user_id == user_id)
            )

        # Ensure distinct results since a user could own AND be shared a folder
        # (though this shouldn't happen, we handle it gracefully)
        query = query.distinct()

        # Log the SQL query
        logger.debug(f"SQL Query: {query}")

        folders = query.order_by(Folder.created_at.desc()).all()

        logger.info(f"list_folders result: Found {len(folders)} folders")
        for folder in folders:
            logger.debug(
                f"  - Folder: {folder.id} | {folder.name} | "
                f"org: {folder.organization_id} | deleted: {folder.is_deleted}"
            )

        return folders

    @staticmethod
    def list_all_org_folders(
        db: Session,
        organization_id: str,
        archived: bool = False,
        favorites_only: bool = False,
        user_id: str | None = None
    ) -> List[Folder]:
        """List ALL folders in an organization (for managers).

        Unlike list_folders which returns only owned/shared folders, this method
        returns ALL folders in the organization regardless of ownership or sharing.
        This is intended for users with organization.manage permission.

        Args:
            db: Database session
            organization_id: Organization to filter by
            archived: If True, show deleted folders; if False, show active folders
            favorites_only: If True, only show folders favorited by this user
            user_id: User ID for favorites filtering (required if favorites_only=True)

        Returns:
            List of all Folder instances in the organization
        """
        import logging
        logger = logging.getLogger(__name__)

        logger.debug(
            f"list_all_org_folders - organization_id: {organization_id}, "
            f"archived: {archived}, favorites_only: {favorites_only}"
        )

        # Build base query for ALL folders in organization
        query = db.query(Folder).filter(
            Folder.organization_id == organization_id
        )

        # Apply archived filter
        if archived:
            logger.debug("Filtering for archived (deleted) folders")
            query = query.filter(Folder.is_deleted)
        else:
            logger.debug("Filtering for non-archived folders")
            query = query.filter(~Folder.is_deleted)

        # Apply favorites filter (requires user_id)
        if favorites_only:
            if not user_id:
                raise ValueError("user_id is required when favorites_only=True")
            logger.debug("Filtering for user's favorites only")
            query = query.join(
                UserFolderFavorite,
                (UserFolderFavorite.folder_id == Folder.id) &
                (UserFolderFavorite.user_id == user_id)
            )

        folders = query.order_by(Folder.created_at.desc()).all()

        logger.info(f"list_all_org_folders result: Found {len(folders)} folders")
        for folder in folders:
            logger.debug(
                f"  - Folder: {folder.id} | {folder.name} | "
                f"owner: {folder.owner} | deleted: {folder.is_deleted}"
            )

        return folders

    @staticmethod
    def update_folder(
        db: Session,
        folder: Folder,
        folder_update: FolderUpdate
    ) -> Folder:
        """Update a folder."""
        update_data = folder_update.dict(exclude_unset=True)

        for field, value in update_data.items():
            setattr(folder, field, value)

        folder.updated_at = datetime.utcnow()
        db.commit()
        db.refresh(folder)
        return folder

    @staticmethod
    def soft_delete_folder(
        db: Session,
        folder: Folder
    ) -> Folder:
        """Soft delete a folder."""
        folder.is_deleted = True
        folder.updated_at = datetime.utcnow()
        db.commit()
        db.refresh(folder)
        return folder

    @staticmethod
    def restore_folder(
        db: Session,
        folder: Folder
    ) -> Folder:
        """Restore a soft-deleted folder."""
        folder.is_deleted = False
        folder.updated_at = datetime.utcnow()
        db.commit()
        db.refresh(folder)
        return folder

    # ==================== Folder Item Methods ====================

    @staticmethod
    def add_item_to_folder(
        db: Session,
        folder_id: UUID,
        item_id: str,
        item_type: str,
        owner: str,  # Username
        position: Optional[int] = None
    ) -> FolderItem:
        """Add an item to a folder."""
        try:
            # Check if item already exists in folder
            existing = db.query(FolderItem).filter(
                FolderItem.folder_id == folder_id,
                FolderItem.item_id == item_id,
                FolderItem.item_type == item_type
            ).first()

            if existing:
                logger.debug("Item already exists in folder, updating position if provided")
                # Update position if provided
                if position is not None:
                    existing.position = position
                    db.commit()
                    db.refresh(existing)
                return existing

            logger.debug(
                f"Creating new folder item - folder_id: {folder_id}, "
                f"item_id: {item_id}, item_type: {item_type}, owner: {owner}"
            )

            folder_item = FolderItem(
                folder_id=folder_id,
                item_id=item_id,
                item_type=item_type,
                owner=owner,
                position=position
            )
            db.add(folder_item)
            db.commit()
            db.refresh(folder_item)

            logger.debug(f"Successfully created folder item with id: {folder_item.id}")
            return folder_item
        except Exception as e:
            logger.error(f"Error in add_item_to_folder: {str(e)}", exc_info=True)
            db.rollback()
            raise

    @staticmethod
    def remove_item_from_folder(
        db: Session,
        folder_id: UUID,
        item_id: str,
        item_type: str
    ) -> bool:
        """Remove an item from a folder."""
        folder_item = db.query(FolderItem).filter(
            FolderItem.folder_id == folder_id,
            FolderItem.item_id == item_id,
            FolderItem.item_type == item_type
        ).first()

        if folder_item:
            db.delete(folder_item)
            db.commit()
            return True

        return False

    @staticmethod
    def update_item_folder(
        db: Session,
        folder_id: UUID,
        item_id: str,
        item_type: str,
        destination_folder_id: UUID,
        organization_id: str
    ) -> Optional[FolderItem]:
        """Update the folder_id of an item (move it to a different folder).

        This is a RESTful PATCH operation that updates the folder_id attribute
        of a FolderItem, effectively moving the item to a new folder.

        Args:
            db: Database session
            folder_id: Current folder ID (used to find the item)
            item_id: ID of the item to move
            item_type: Type of item ('company', 'contact', etc.)
            destination_folder_id: New folder ID to move the item to
            organization_id: Organization ID for validation

        Returns:
            Updated FolderItem if successful, None if item not found
        """
        # Find the folder item
        folder_item = db.query(FolderItem).filter(
            FolderItem.folder_id == folder_id,
            FolderItem.item_id == item_id,
            FolderItem.item_type == item_type
        ).first()

        if not folder_item:
            logger.warning(
                f"Item not found in folder - item_id: {item_id}, folder_id: {folder_id}"
            )
            return None

        # Check if item already exists in destination folder
        existing_in_destination = db.query(FolderItem).filter(
            FolderItem.folder_id == destination_folder_id,
            FolderItem.item_id == item_id,
            FolderItem.item_type == item_type
        ).first()

        if existing_in_destination:
            logger.info(
                f"Item already exists in destination folder, removing from source - "
                f"item_id: {item_id}, destination_folder_id: {destination_folder_id}"
            )
            # Delete from source since it's already in destination
            db.delete(folder_item)
            db.commit()
            return existing_in_destination

        # Update the folder_id to move the item
        logger.info(
            f"Moving item from folder {folder_id} to {destination_folder_id} - "
            f"item_id: {item_id}, item_type: {item_type}"
        )
        folder_item.folder_id = destination_folder_id
        folder_item.added_at = datetime.now(timezone.utc)  # Update timestamp to reflect move
        db.commit()
        db.refresh(folder_item)

        return folder_item

    @staticmethod
    def get_folders_for_item(
        db: Session,
        item_id: str,
        item_type: str,
        organization_id: str
    ) -> List[Folder]:
        """Get all folders containing a specific item."""
        folder_items = db.query(FolderItem).filter(
            FolderItem.item_id == item_id,
            FolderItem.item_type == item_type
        ).all()

        folder_ids = [fi.folder_id for fi in folder_items]

        if not folder_ids:
            return []

        return db.query(Folder).filter(
            Folder.id.in_(folder_ids),
            Folder.organization_id == organization_id,
            not Folder.is_deleted
        ).all()

    # ==================== Folder Sharing Methods ====================

    @staticmethod
    def share_folder(
        db: Session,
        folder_id: UUID,
        user_id: str,
        user_username: str,
        role: ShareRole
    ) -> FolderShare:
        """Share a folder with a user.

        Creates a new FolderShare record granting the specified user access
        to the folder with the given role.

        Args:
            db: Database session
            folder_id: UUID of the folder to share
            user_id: Keycloak user UUID to share with
            user_username: Username for display (denormalized)
            role: ShareRole enum (READER or WRITER)

        Returns:
            Newly created FolderShare instance

        Raises:
            ValueError: If a share already exists for this folder-user combination
        """

        # Check for existing share
        existing = db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id,
            FolderShare.user_id == user_id
        ).first()

        if existing:
            logger.warning(
                f"Share already exists for folder {folder_id} and user {user_id}"
            )
            raise ValueError(f"Folder is already shared with user {user_username}")

        # Create new share
        share = FolderShare(
            folder_id=folder_id,
            user_id=user_id,
            user_username=user_username,
            role=role
        )
        db.add(share)
        db.commit()
        db.refresh(share)

        logger.info(
            f"Created folder share: folder={folder_id}, user={user_id}, role={role.value}"
        )
        return share

    @staticmethod
    def unshare_folder(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> bool:
        """Remove a user's access to a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID to remove access for

        Returns:
            True if share was removed, False if no share existed
        """

        share = db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id,
            FolderShare.user_id == user_id
        ).first()

        if not share:
            logger.debug(f"No share found for folder {folder_id} and user {user_id}")
            return False

        db.delete(share)
        db.commit()

        logger.info(f"Removed folder share: folder={folder_id}, user={user_id}")
        return True

    @staticmethod
    def get_folder_shares(
        db: Session,
        folder_id: UUID
    ) -> List[FolderShare]:
        """Get all shares for a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder

        Returns:
            List of FolderShare instances for the folder
        """
        return db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id
        ).order_by(FolderShare.created_at.desc()).all()

    @staticmethod
    def update_share_role(
        db: Session,
        folder_id: UUID,
        user_id: str,
        role: ShareRole
    ) -> Optional[FolderShare]:
        """Update a user's share role for a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID
            role: New ShareRole to set

        Returns:
            Updated FolderShare instance, or None if no share exists
        """

        share = db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id,
            FolderShare.user_id == user_id
        ).first()

        if not share:
            logger.debug(f"No share found for folder {folder_id} and user {user_id}")
            return None

        old_role = share.role
        share.role = role
        db.commit()
        db.refresh(share)

        logger.info(
            f"Updated folder share role: folder={folder_id}, user={user_id}, "
            f"old_role={old_role.value}, new_role={role.value}"
        )
        return share

    # ==================== Access Control Methods ====================

    @staticmethod
    def has_folder_access(
        db: Session,
        folder_id: UUID,
        user_id: str,
        organization_id: str,
        username: str | None = None,
        user_roles: List[str] | None = None
    ) -> bool:
        """Check if a user has access to a folder.

        A user has access if they:
        - Are a manager (organization.manage or admin.organizations role)
        - Are the folder owner
        - Have a share record for the folder

        Also validates that the folder belongs to the specified organization.

        Args:
            db: Database session
            folder_id: UUID of the folder to check
            user_id: Keycloak user UUID to check access for
            organization_id: Organization UUID to validate against
            username: Optional username for legacy fallback when owner_id is NULL
            user_roles: Optional list of user roles for manager check

        Returns:
            True if user has access, False otherwise
        """

        # First check if folder exists in the organization
        folder = db.query(Folder).filter(
            Folder.id == folder_id,
            Folder.organization_id == organization_id
        ).first()

        if not folder:
            return False

        # Managers have access to ALL folders in their organization
        if user_roles and _user_is_manager(user_roles):
            logger.info(f"Manager access granted to folder {folder_id} for user {user_id}")
            return True

        # Check if user is owner (by owner_id)
        if folder.owner_id and folder.owner_id == user_id:
            return True

        # Legacy fallback: if owner_id is NULL, check owner (username) field
        # This ensures backward compatibility for folders created before owner_id was populated
        if folder.owner_id is None and username and folder.owner == username:
            logger.warning(
                f"Folder {folder_id} has NULL owner_id - using legacy username check. "
                f"Consider running migration to populate owner_id."
            )
            return True

        # Check if user has a share
        share = db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id,
            FolderShare.user_id == user_id
        ).first()

        return share is not None

    @staticmethod
    def get_user_folder_role(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> Optional[str]:
        """Get a user's role for a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID

        Returns:
            'owner' if user owns the folder
            'writer' if user has writer share
            'reader' if user has reader share
            None if user has no access
        """
        # Check if user is owner
        folder = db.query(Folder).filter(Folder.id == folder_id).first()

        if not folder:
            return None

        if folder.owner_id == user_id:
            return "owner"

        # Check share role
        share = db.query(FolderShare).filter(
            FolderShare.folder_id == folder_id,
            FolderShare.user_id == user_id
        ).first()

        if not share:
            return None

        return share.role.value  # Returns 'reader' or 'writer'

    @staticmethod
    def is_folder_owner(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> bool:
        """Check if a user is the owner of a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID

        Returns:
            True if user is the folder owner, False otherwise
        """
        folder = db.query(Folder).filter(
            Folder.id == folder_id,
            Folder.owner_id == user_id
        ).first()

        return folder is not None

    # ==================== Orphaned Folder Methods ====================

    @staticmethod
    def flag_folder_orphaned(
        db: Session,
        folder_id: UUID
    ) -> bool:
        """Flag a folder as orphaned.

        Used when a folder owner leaves the organization or is disabled.
        Orphaned folders require admin action to claim or reassign.

        Args:
            db: Database session
            folder_id: UUID of the folder to flag

        Returns:
            True if folder was flagged, False if folder not found
        """

        folder = db.query(Folder).filter(Folder.id == folder_id).first()

        if not folder:
            logger.debug(f"Folder {folder_id} not found for orphan flagging")
            return False

        folder.is_orphaned = True
        folder.updated_at = datetime.utcnow()
        db.commit()

        logger.info(f"Flagged folder as orphaned: {folder_id}")
        return True

    # ==================== User Favorites Methods ====================

    @staticmethod
    def add_favorite(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> bool:
        """Add a folder to user's favorites.

        Returns:
            True if favorite was added, False if already favorited
        """
        # Check if already favorited
        existing = db.query(UserFolderFavorite).filter(
            UserFolderFavorite.folder_id == folder_id,
            UserFolderFavorite.user_id == user_id
        ).first()

        if existing:
            return False  # Already favorited

        favorite = UserFolderFavorite(
            folder_id=folder_id,
            user_id=user_id
        )
        db.add(favorite)
        db.commit()
        return True

    @staticmethod
    def remove_favorite(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> bool:
        """Remove a folder from user's favorites.

        Returns:
            True if favorite was removed, False if not favorited
        """
        favorite = db.query(UserFolderFavorite).filter(
            UserFolderFavorite.folder_id == folder_id,
            UserFolderFavorite.user_id == user_id
        ).first()

        if not favorite:
            return False  # Not favorited

        db.delete(favorite)
        db.commit()
        return True

    @staticmethod
    def is_favorite(
        db: Session,
        folder_id: UUID,
        user_id: str
    ) -> bool:
        """Check if a folder is favorited by a user."""
        return db.query(UserFolderFavorite).filter(
            UserFolderFavorite.folder_id == folder_id,
            UserFolderFavorite.user_id == user_id
        ).first() is not None

    @staticmethod
    def get_user_favorite_folder_ids(
        db: Session,
        user_id: str,
        organization_id: str
    ) -> Set[UUID]:
        """Get all folder IDs favorited by a user in an organization.

        Returns:
            Set of folder UUIDs that the user has favorited
        """
        favorites = db.query(UserFolderFavorite.folder_id).join(
            Folder, UserFolderFavorite.folder_id == Folder.id
        ).filter(
            UserFolderFavorite.user_id == user_id,
            Folder.organization_id == organization_id,
            not Folder.is_deleted
        ).all()

        return {f[0] for f in favorites}

    # ==================== Company Access Control Methods ====================

    @staticmethod
    def user_has_company_access(
        db: Session,
        company_id: int,
        user_id: str,
        organization_id: str,
        username: str | None = None,
        user_roles: List[str] | None = None
    ) -> bool:
        """Check if a user has access to a company via folder sharing.

        A user has access to a company if they:
        - Are a manager (organization.manage or admin.organizations role)
        - The company belongs to at least one folder the user owns or has been shared with

        Args:
            db: Database session
            company_id: Company ID to check access for
            user_id: Keycloak user UUID to check
            organization_id: Organization UUID for validation
            username: Optional username for legacy fallback when owner_id is NULL
            user_roles: Optional list of user roles for manager check

        Returns:
            True if user has access to the company, False otherwise
        """

        # Managers have access to ALL companies in their organization
        if user_roles and _user_is_manager(user_roles):
            # Verify the company exists in the organization
            company = db.query(Company).filter(
                Company.id == company_id,
                Company.organization_id == organization_id
            ).first()
            if company:
                logger.info(f"Manager access granted to company {company_id} for user {user_id}")
                return True
            return False

        # Find all folders containing this company in the organization
        folder_items = db.query(FolderItem).join(
            Folder, FolderItem.folder_id == Folder.id
        ).filter(
            FolderItem.item_id == str(company_id),
            FolderItem.item_type == 'company',
            Folder.organization_id == organization_id,
            not Folder.is_deleted
        ).all()

        if not folder_items:
            logger.debug(
                f"Company {company_id} not found in any folder in organization {organization_id}"
            )
            return False

        # Check if user has access to any of these folders
        for folder_item in folder_items:
            folder_id = folder_item.folder_id
            if FolderService.has_folder_access(
                db, folder_id, user_id, organization_id, username=username, user_roles=user_roles
            ):
                logger.debug(
                    f"User {user_id} has access to company {company_id} via folder {folder_id}"
                )
                return True

        logger.debug(
            f"User {user_id} does not have access to company {company_id}"
        )
        return False

    @staticmethod
    def get_accessible_company_ids(
        db: Session,
        user_id: str,
        organization_id: str,
        username: str | None = None
    ) -> Set[int]:
        """Get all company IDs that a user can access via folder sharing.

        Returns the set of company IDs from all folders the user owns or
        has been shared with.

        Args:
            db: Database session
            user_id: Keycloak user UUID
            organization_id: Organization UUID
            username: Optional username for legacy fallback when owner_id is NULL

        Returns:
            Set of company IDs the user has access to
        """

        # Get all folders accessible to the user (owned + shared)
        accessible_folders = FolderService.list_folders(
            db=db,
            organization_id=organization_id,
            user_id=user_id,
            archived=False,
            username=username
        )

        if not accessible_folders:
            logger.debug(f"User {user_id} has no accessible folders")
            return set()

        folder_ids = [f.id for f in accessible_folders]

        # Get all company IDs from these folders
        folder_items = db.query(FolderItem.item_id).filter(
            FolderItem.folder_id.in_(folder_ids),
            FolderItem.item_type == 'company'
        ).all()

        company_ids = set()
        for item in folder_items:
            try:
                company_ids.add(int(item[0]))
            except (ValueError, TypeError):
                continue

        logger.debug(
            f"User {user_id} has access to {len(company_ids)} companies "
            f"via {len(accessible_folders)} folders"
        )

        return company_ids
