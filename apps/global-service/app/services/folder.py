"""Folder service layer for folder operations, sharing, and access control.

This module provides business logic for:
- Folder CRUD operations
- Folder item management
- Folder sharing (share_folder, unshare_folder, get_folder_shares, update_share_role)
- Access control (has_folder_access, get_user_folder_role, is_folder_owner)
- User favorites
- Orphaned folder management
"""

import logging
import math
from datetime import UTC, datetime
from typing import Any, Literal
from uuid import UUID

from sqlalchemy import and_, asc, desc, func, or_, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.folder import Folder, FolderItem, FolderShare, ItemType, ShareRole
from app.models.user_folder_favorite import UserFolderFavorite
from app.schemas.folder import FolderCreate, FolderUpdate
from app.services.backend_client import get_companies_by_ids

logger = logging.getLogger(__name__)

FolderSortBy = Literal["name", "created_at", "updated_at"]
SortOrder = Literal["asc", "desc"]

_SORTABLE_COLUMNS = {
    "name": Folder.name,
    "created_at": Folder.created_at,
    "updated_at": Folder.updated_at,
}


def _build_folder_order_by(sort_by: FolderSortBy, sort_order: SortOrder):
    """Build an ORDER BY clause for folder listing queries.

    ``sort_by`` is constrained by the ``FolderSortBy`` Literal at the API
    boundary, so an unknown value here means the service was misused; a
    KeyError is the intended signal.
    """
    column = _SORTABLE_COLUMNS[sort_by]
    return column.asc() if sort_order == "asc" else column.desc()


def _escape_like(value: str) -> str:
    """Escape LIKE wildcards so user input is matched literally.

    Without escaping, ``%`` and ``_`` in user input are interpreted as
    wildcards by PostgreSQL's ILIKE, allowing unintended matches and
    forcing a full scan on malicious patterns such as ``%%`` or ``__``.
    """
    return value.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_")


def _user_is_manager(user_roles: list[str]) -> bool:
    """Check if user has manager permissions.

    A user is considered a manager if they have either:
    - organization.manage role (can manage their organization)
    - admin.organizations role (global admin)

    Args:
        user_roles: List of realm roles from Keycloak

    Returns:
        True if user is a manager, False otherwise
    """
    return "organization.manage" in user_roles or "admin.organizations" in user_roles


class FolderService:
    """Service class for folder operations."""

    # ==================== Folder CRUD Methods ====================

    @staticmethod
    async def create_folder(
        db: AsyncSession, organization_id: str, owner_id: str, owner_username: str, folder_data: FolderCreate
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
            tags=folder_data.tags or [],
        )
        db.add(folder)
        await db.commit()
        await db.refresh(folder)
        return folder

    @staticmethod
    async def get_folder(
        db: AsyncSession, folder_id: UUID, organization_id: str, include_deleted: bool = False
    ) -> Folder | None:
        """Get a folder by ID.

        Args:
            db: Database session
            folder_id: Folder UUID to retrieve
            organization_id: Organization UUID for access validation
            include_deleted: If True, include soft-deleted folders

        Returns:
            Folder instance if found, None otherwise
        """
        stmt = select(Folder).where(Folder.id == folder_id, Folder.organization_id == organization_id)

        if not include_deleted:
            stmt = stmt.where(Folder.is_deleted.is_(False))

        result = await db.execute(stmt)
        return result.scalars().first()

    @staticmethod
    async def _get_folder_items_summary(
        db: AsyncSession,
        folder_id: UUID,
        item_archived_filter: bool = False,
        user_id: str = "",
        username: str = "",
        org_id: str = "",
        org_name: str = "",
        roles: list[str] | None = None,
        page: int | None = None,
        size: int | None = None,
        sort_by: str | None = None,
        sort_order: str = "asc",
        name_filter: str | None = None,
    ) -> tuple[list[dict[str, Any]], int]:
        """Get complete items for a folder with optional pagination, sorting, and filtering.

        Returns (items, total_count). Because the name filter/sort operates on the
        company name resolved from the backend, ALL folder items are loaded and
        enriched before pagination is applied in memory. Acceptable at current folder
        volumes; if folders grow beyond a few thousand items, revisit by denormalising
        name onto FolderItem or pushing the filter to the backend service.
        """
        base_stmt = select(FolderItem).where(FolderItem.folder_id == folder_id)

        _sort_by = sort_by or "position"
        _order_fn = desc if sort_order == "desc" else asc

        # name sort is applied post-enrichment (name lives in backend, not in FolderItem)
        if _sort_by == "added_at":
            stmt = base_stmt.order_by(_order_fn(FolderItem.added_at))
        elif _sort_by == "name":
            stmt = base_stmt.order_by(FolderItem.position.nullsfirst(), FolderItem.added_at)
        else:
            # Default position sort: nulls first asc, nulls last desc
            position_clause = (
                FolderItem.position.nullsfirst() if sort_order != "desc" else FolderItem.position.nullslast()
            )
            stmt = base_stmt.order_by(position_clause, FolderItem.added_at)

        result = await db.execute(stmt)
        folder_items = result.scalars().all()

        if not folder_items:
            return [], 0

        # Collect all company IDs for batch enrichment (full set, needed for count + name filter)
        all_company_ids = []
        for item in folder_items:
            if item.item_type == ItemType.company:
                try:
                    all_company_ids.append(int(item.item_id))
                except (ValueError, TypeError):
                    continue

        # Fetch company details from backend (all IDs on this query)
        company_map: dict = {}
        if all_company_ids:
            company_map = await get_companies_by_ids(
                company_ids=all_company_ids,
                user_id=user_id,
                username=username,
                org_id=org_id,
                org_name=org_name,
                roles=roles,
                include_archived=item_archived_filter,
            )

        # Hoist filter string ops outside the loop (strip/lower are O(n) per call)
        name_filter_lower = name_filter.strip().lower() if name_filter else ""

        # Build full enriched list (with archived + name filters)
        all_items = []
        for item in folder_items:
            if item.item_type == ItemType.company:
                try:
                    company_id = int(item.item_id)
                except (ValueError, TypeError):
                    continue

                company = company_map.get(company_id)
                if not company:
                    continue

                if company.is_deleted != item_archived_filter:
                    continue

                if name_filter_lower and name_filter_lower not in company.name.lower():
                    continue

                all_items.append(
                    {
                        "id": str(item.item_id),
                        "type": item.item_type.value,
                        "position": item.position,
                        "added_at": item.added_at.isoformat() if item.added_at else None,
                        "name": company.name,
                        "website": company.website,
                        "created_at": company.created_at,
                        "owner": company.owner_username or "Unknown",
                        "is_deleted": company.is_deleted,
                    }
                )
            # Add support for other item types (watchfile, explore) here in the future

        # Apply name sort post-enrichment (name lives in backend, not in FolderItem)
        if _sort_by == "name":
            all_items.sort(key=lambda x: x["name"].lower(), reverse=(sort_order == "desc"))

        total = len(all_items)

        # Apply pagination if requested
        if page is not None and size is not None:
            offset = (page - 1) * size
            paginated = all_items[offset : offset + size]
        else:
            paginated = all_items

        return paginated, total

    @staticmethod
    async def get_folder_with_items(
        db: AsyncSession,
        folder_id: UUID,
        organization_id: str,
        item_archived_filter: bool = False,
        user_id: str = "",
        username: str = "",
        org_name: str = "",
        roles: list[str] | None = None,
        page: int | None = None,
        size: int | None = None,
        sort_by: str | None = None,
        sort_order: str = "asc",
        name_filter: str | None = None,
    ) -> dict[str, Any] | None:
        """Get folder with summary of its items, with optional pagination/sort/filter."""
        folder = await FolderService.get_folder(db, folder_id, organization_id)
        if not folder:
            return None

        items, total = await FolderService._get_folder_items_summary(
            db,
            folder_id,
            item_archived_filter=item_archived_filter,
            user_id=user_id,
            username=username,
            org_id=organization_id,
            org_name=org_name,
            roles=roles,
            page=page,
            size=size,
            sort_by=sort_by,
            sort_order=sort_order,
            name_filter=name_filter,
        )

        pagination = None
        if page is not None and size is not None:
            pagination = {
                "total": total,
                "page": page,
                "limit": size,
                "total_pages": math.ceil(total / size) if size > 0 else 0,
            }

        return {
            "id": str(folder.id),
            "name": folder.name,
            "color": folder.color,
            "icon": folder.icon,
            "tags": folder.tags,
            "is_deleted": folder.is_deleted,
            "created_at": folder.created_at.isoformat() if folder.created_at else None,
            "updated_at": folder.updated_at.isoformat() if folder.updated_at else None,
            "owner": folder.owner,
            "organization_id": folder.organization_id,
            "items": items,
            "pagination": pagination,
            # Note: is_favorite is computed per-user and added by the endpoint
        }

    @staticmethod
    async def list_folders(
        db: AsyncSession,
        organization_id: str,
        user_id: str,
        archived: bool = False,
        favorites_only: bool = False,
        username: str | None = None,
        page: int = 1,
        limit: int = 12,
        name: str | None = None,
        sort_by: FolderSortBy = "created_at",
        sort_order: SortOrder = "desc",
    ) -> tuple[list[Folder], int]:
        """List folders accessible to a user (owned + shared) with pagination.

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
            page: Page number (1-indexed)
            limit: Items per page
            name: Optional case-insensitive substring to filter by folder name
            sort_by: Column to sort by (name, created_at, updated_at)
            sort_order: Sort direction (asc or desc)

        Returns:
            Tuple of (list of Folder instances, total count)
        """
        logger.debug(
            f"list_folders - organization_id: {organization_id}, "
            f"user_id: {user_id}, archived: {archived}, favorites_only: {favorites_only}, "
            f"name: {name}, sort_by: {sort_by}, sort_order: {sort_order}"
        )

        # Build ownership conditions
        # Primary: match by owner_id
        # Fallback: match by owner (username) if owner_id is NULL (legacy data)
        ownership_conditions = [Folder.owner_id == user_id]
        if username:
            ownership_conditions.append(and_(Folder.owner_id.is_(None), Folder.owner == username))

        # Build base filter conditions
        base_conditions = [
            Folder.organization_id == organization_id,
            or_(*ownership_conditions, FolderShare.user_id == user_id),
        ]

        # Build base query with ownership OR sharing filter
        # This uses a LEFT JOIN with folder_shares to include both owned and shared folders
        stmt = select(Folder).outerjoin(FolderShare, FolderShare.folder_id == Folder.id).where(*base_conditions)

        # Apply archived filter
        if archived:
            logger.debug("Filtering for archived (deleted) folders")
            stmt = stmt.where(Folder.is_deleted.is_(True))
        else:
            logger.debug("Filtering for non-archived folders")
            stmt = stmt.where(Folder.is_deleted.is_(False))

        # Apply favorites filter
        if favorites_only:
            stmt = stmt.join(
                UserFolderFavorite,
                (UserFolderFavorite.folder_id == Folder.id) & (UserFolderFavorite.user_id == user_id),
            )

        # Apply name search (case-insensitive substring match)
        if name:
            stmt = stmt.where(Folder.name.ilike(f"%{_escape_like(name)}%", escape="\\"))

        stmt = stmt.distinct()

        # Count total before pagination
        count_stmt = select(func.count()).select_from(stmt.subquery())
        total_result = await db.execute(count_stmt)
        total = total_result.scalar() or 0

        # Apply ordering and pagination
        offset = (page - 1) * limit
        stmt = stmt.order_by(_build_folder_order_by(sort_by, sort_order)).offset(offset).limit(limit)

        result = await db.execute(stmt)
        folders = result.scalars().all()

        logger.info(f"list_folders result: Found {len(folders)} folders (total: {total})")
        return list(folders), total

    @staticmethod
    async def list_all_org_folders(
        db: AsyncSession,
        organization_id: str,
        archived: bool = False,
        favorites_only: bool = False,
        user_id: str | None = None,
        page: int = 1,
        limit: int = 12,
        name: str | None = None,
        sort_by: FolderSortBy = "created_at",
        sort_order: SortOrder = "desc",
    ) -> tuple[list[Folder], int]:
        """List ALL folders in an organization (for managers) with pagination.

        Unlike list_folders which returns only owned/shared folders, this method
        returns ALL folders in the organization regardless of ownership or sharing.
        This is intended for users with organization.manage permission.

        Args:
            db: Database session
            organization_id: Organization to filter by
            archived: If True, show deleted folders; if False, show active folders
            favorites_only: If True, only show folders favorited by this user
            user_id: User ID for favorites filtering (required if favorites_only=True)
            page: Page number (1-indexed)
            limit: Items per page
            name: Optional case-insensitive substring to filter by folder name
            sort_by: Column to sort by (name, created_at, updated_at)
            sort_order: Sort direction (asc or desc)

        Returns:
            Tuple of (list of Folder instances, total count)
        """

        logger.debug(
            f"list_all_org_folders - organization_id: {organization_id}, "
            f"archived: {archived}, favorites_only: {favorites_only}, "
            f"name: {name}, sort_by: {sort_by}, sort_order: {sort_order}"
        )

        stmt = select(Folder).where(Folder.organization_id == organization_id)

        stmt = stmt.where(Folder.is_deleted.is_(True)) if archived else stmt.where(Folder.is_deleted.is_(False))

        if favorites_only:
            if not user_id:
                raise ValueError("user_id is required when favorites_only=True")
            stmt = stmt.join(
                UserFolderFavorite,
                (UserFolderFavorite.folder_id == Folder.id) & (UserFolderFavorite.user_id == user_id),
            )

        # Apply name search (case-insensitive substring match)
        if name:
            stmt = stmt.where(Folder.name.ilike(f"%{_escape_like(name)}%", escape="\\"))

        # Count total before pagination
        count_stmt = select(func.count()).select_from(stmt.subquery())
        total_result = await db.execute(count_stmt)
        total = total_result.scalar() or 0

        # Apply ordering and pagination
        offset = (page - 1) * limit
        stmt = stmt.order_by(_build_folder_order_by(sort_by, sort_order)).offset(offset).limit(limit)

        result = await db.execute(stmt)
        folders = result.scalars().all()

        logger.info(f"list_all_org_folders result: Found {len(folders)} folders (total: {total})")
        return list(folders), total

    @staticmethod
    async def update_folder(db: AsyncSession, folder: Folder, folder_update: FolderUpdate) -> Folder:
        """Update a folder."""
        update_data = folder_update.model_dump(exclude_unset=True)

        for field, value in update_data.items():
            setattr(folder, field, value)

        folder.updated_at = datetime.now(UTC)
        await db.commit()
        await db.refresh(folder)
        return folder

    @staticmethod
    async def soft_delete_folder(db: AsyncSession, folder: Folder) -> Folder:
        """Soft delete a folder."""
        folder.is_deleted = True
        folder.updated_at = datetime.now(UTC)
        await db.commit()
        await db.refresh(folder)
        return folder

    @staticmethod
    async def restore_folder(db: AsyncSession, folder: Folder) -> Folder:
        """Restore a soft-deleted folder."""
        folder.is_deleted = False
        folder.updated_at = datetime.now(UTC)
        await db.commit()
        await db.refresh(folder)
        return folder

    # ==================== Folder Item Methods ====================

    @staticmethod
    async def add_item_to_folder(
        db: AsyncSession, folder_id: UUID, item_id: str, item_type: str, owner: str, position: int | None = None
    ) -> FolderItem:
        """Add an item to a folder."""
        try:
            # Check if item already exists in folder
            stmt = select(FolderItem).where(
                FolderItem.folder_id == folder_id, FolderItem.item_id == item_id, FolderItem.item_type == item_type
            )
            result = await db.execute(stmt)
            existing = result.scalars().first()

            if existing:
                logger.debug("Item already exists in folder, updating position if provided")
                # Update position if provided
                if position is not None:
                    existing.position = position
                    await db.commit()
                    await db.refresh(existing)
                return existing

            logger.debug(
                f"Creating new folder item - folder_id: {folder_id}, "
                f"item_id: {item_id}, item_type: {item_type}, owner: {owner}"
            )

            folder_item = FolderItem(
                folder_id=folder_id, item_id=item_id, item_type=item_type, owner=owner, position=position
            )
            db.add(folder_item)
            await db.commit()
            await db.refresh(folder_item)

            logger.debug(f"Successfully created folder item with id: {folder_item.id}")
            return folder_item
        except Exception as e:
            logger.error(f"Error in add_item_to_folder: {str(e)}", exc_info=True)
            await db.rollback()
            raise

    @staticmethod
    async def remove_item_from_folder(db: AsyncSession, folder_id: UUID, item_id: str, item_type: str) -> bool:
        """Remove an item from a folder."""
        stmt = select(FolderItem).where(
            FolderItem.folder_id == folder_id, FolderItem.item_id == item_id, FolderItem.item_type == item_type
        )
        result = await db.execute(stmt)
        folder_item = result.scalars().first()

        if folder_item:
            await db.delete(folder_item)
            await db.commit()
            return True

        return False

    @staticmethod
    async def update_item_folder(
        db: AsyncSession,
        folder_id: UUID,
        item_id: str,
        item_type: str,
        destination_folder_id: UUID,
        organization_id: str,
    ) -> FolderItem | None:
        """Update the folder_id of an item (move it to a different folder).

        This is a RESTful PATCH operation that updates the folder_id attribute
        of a FolderItem, effectively moving the item to a new folder.

        Args:
            db: Database session
            folder_id: Current folder ID (used to find the item)
            item_id: ID of the item to move
            item_type: ItemType enum (company, watchfile, explore)
            destination_folder_id: New folder ID to move the item to
            organization_id: Organization ID for validation

        Returns:
            Updated FolderItem if successful, None if item not found
        """
        # Find the folder item
        stmt = select(FolderItem).where(
            FolderItem.folder_id == folder_id, FolderItem.item_id == item_id, FolderItem.item_type == item_type
        )
        result = await db.execute(stmt)
        folder_item = result.scalars().first()

        if not folder_item:
            logger.warning(f"Item not found in folder - item_id: {item_id}, folder_id: {folder_id}")
            return None

        # Check if item already exists in destination folder
        stmt = select(FolderItem).where(
            FolderItem.folder_id == destination_folder_id,
            FolderItem.item_id == item_id,
            FolderItem.item_type == item_type,
        )
        result = await db.execute(stmt)
        existing_in_destination = result.scalars().first()

        if existing_in_destination:
            logger.info(
                f"Item already exists in destination folder, removing from source - "
                f"item_id: {item_id}, destination_folder_id: {destination_folder_id}"
            )
            # Delete from source since it's already in destination
            await db.delete(folder_item)
            await db.commit()
            return existing_in_destination

        # Update the folder_id to move the item
        logger.info(
            f"Moving item from folder {folder_id} to {destination_folder_id} - "
            f"item_id: {item_id}, item_type: {item_type}"
        )
        folder_item.folder_id = destination_folder_id
        folder_item.added_at = datetime.now(UTC)
        await db.commit()
        await db.refresh(folder_item)

        return folder_item

    @staticmethod
    async def get_folders_for_item(
        db: AsyncSession, item_id: str, item_type: str, organization_id: str
    ) -> list[Folder]:
        """Get all folders containing a specific item.

        Results are ordered by added_at descending (most recently added first)
        to ensure deterministic ordering.
        """
        stmt = (
            select(FolderItem)
            .where(
                FolderItem.item_id == item_id,
                FolderItem.item_type == item_type,
            )
            .order_by(FolderItem.added_at.desc())
        )
        result = await db.execute(stmt)
        folder_items = result.scalars().all()

        folder_ids = [fi.folder_id for fi in folder_items]

        if not folder_ids:
            return []

        stmt = select(Folder).where(
            Folder.id.in_(folder_ids),
            Folder.organization_id == organization_id,
            Folder.is_deleted.is_(False),
        )
        result = await db.execute(stmt)
        folders_by_id = {f.id: f for f in result.scalars().all()}

        # Preserve added_at descending order from folder_items
        return [folders_by_id[fid] for fid in folder_ids if fid in folders_by_id]

    # ==================== Folder Sharing Methods ====================

    @staticmethod
    async def share_folder(
        db: AsyncSession, folder_id: UUID, user_id: str, user_username: str, role: ShareRole
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
        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id, FolderShare.user_id == user_id)
        result = await db.execute(stmt)
        existing = result.scalars().first()

        if existing:
            logger.warning(f"Share already exists for folder {folder_id} and user {user_id}")
            raise ValueError(f"Folder is already shared with user {user_username}")

        # Create new share
        share = FolderShare(folder_id=folder_id, user_id=user_id, user_username=user_username, role=role)
        db.add(share)
        await db.commit()
        await db.refresh(share)

        logger.info(f"Created folder share: folder={folder_id}, user={user_id}, role={role.value}")
        return share

    @staticmethod
    async def unshare_folder(db: AsyncSession, folder_id: UUID, user_id: str) -> bool:
        """Remove a user's access to a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID to remove access for

        Returns:
            True if share was removed, False if no share existed
        """

        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id, FolderShare.user_id == user_id)
        result = await db.execute(stmt)
        share = result.scalars().first()

        if not share:
            logger.debug(f"No share found for folder {folder_id} and user {user_id}")
            return False

        await db.delete(share)
        await db.commit()

        logger.info(f"Removed folder share: folder={folder_id}, user={user_id}")
        return True

    @staticmethod
    async def get_folder_shares(db: AsyncSession, folder_id: UUID) -> list[FolderShare]:
        """Get all shares for a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder

        Returns:
            List of FolderShare instances for the folder
        """
        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id).order_by(FolderShare.created_at.desc())

        result = await db.execute(stmt)
        return list(result.scalars().all())

    @staticmethod
    async def update_share_role(db: AsyncSession, folder_id: UUID, user_id: str, role: ShareRole) -> FolderShare | None:
        """Update a user's share role for a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID
            role: New ShareRole to set

        Returns:
            Updated FolderShare instance, or None if no share exists
        """

        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id, FolderShare.user_id == user_id)
        result = await db.execute(stmt)
        share = result.scalars().first()

        if not share:
            logger.debug(f"No share found for folder {folder_id} and user {user_id}")
            return None

        old_role = share.role
        share.role = role
        await db.commit()
        await db.refresh(share)

        logger.info(
            f"Updated folder share role: folder={folder_id}, user={user_id}, "
            f"old_role={old_role.value}, new_role={role.value}"
        )
        return share

    # ==================== Access Control Methods ====================

    @staticmethod
    async def has_folder_access(
        db: AsyncSession,
        folder_id: UUID,
        user_id: str,
        organization_id: str,
        username: str | None = None,
        user_roles: list[str] | None = None,
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
        stmt = select(Folder).where(Folder.id == folder_id, Folder.organization_id == organization_id)
        result = await db.execute(stmt)
        folder = result.scalars().first()

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
        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id, FolderShare.user_id == user_id)
        result = await db.execute(stmt)
        share = result.scalars().first()

        return share is not None

    @staticmethod
    async def get_user_folder_role(db: AsyncSession, folder_id: UUID, user_id: str) -> str | None:
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
        stmt = select(Folder).where(Folder.id == folder_id)
        result = await db.execute(stmt)
        folder = result.scalars().first()

        if not folder:
            return None

        if folder.owner_id == user_id:
            return "owner"

        stmt = select(FolderShare).where(FolderShare.folder_id == folder_id, FolderShare.user_id == user_id)
        result = await db.execute(stmt)
        share = result.scalars().first()

        if not share:
            return None

        return share.role.value  # Returns 'reader' or 'writer'

    @staticmethod
    async def is_folder_owner(db: AsyncSession, folder_id: UUID, user_id: str) -> bool:
        """Check if a user is the owner of a folder.

        Args:
            db: Database session
            folder_id: UUID of the folder
            user_id: Keycloak user UUID

        Returns:
            True if user is the folder owner, False otherwise
        """
        stmt = select(Folder).where(Folder.id == folder_id, Folder.owner_id == user_id)
        result = await db.execute(stmt)
        return result.scalars().first() is not None

    # ==================== Orphaned Folder Methods ====================

    @staticmethod
    async def flag_folder_orphaned(db: AsyncSession, folder_id: UUID) -> bool:
        """Flag a folder as orphaned.

        Used when a folder owner leaves the organization or is disabled.
        Orphaned folders require admin action to claim or reassign.

        Args:
            db: Database session
            folder_id: UUID of the folder to flag

        Returns:
            True if folder was flagged, False if folder not found
        """

        stmt = select(Folder).where(Folder.id == folder_id)
        result = await db.execute(stmt)
        folder = result.scalars().first()

        if not folder:
            logger.debug(f"Folder {folder_id} not found for orphan flagging")
            return False

        folder.is_orphaned = True
        folder.updated_at = datetime.now(UTC)
        await db.commit()

        logger.info(f"Flagged folder as orphaned: {folder_id}")
        return True

    # ==================== User Favorites Methods ====================

    @staticmethod
    async def add_favorite(db: AsyncSession, folder_id: UUID, user_id: str) -> bool:
        """Add a folder to user's favorites.

        Returns:
            True if favorite was added, False if already favorited
        """
        # Check if already favorited
        stmt = select(UserFolderFavorite).where(
            UserFolderFavorite.folder_id == folder_id, UserFolderFavorite.user_id == user_id
        )
        result = await db.execute(stmt)
        existing = result.scalars().first()

        if existing:
            return False

        favorite = UserFolderFavorite(folder_id=folder_id, user_id=user_id)
        db.add(favorite)
        await db.commit()
        return True

    @staticmethod
    async def remove_favorite(db: AsyncSession, folder_id: UUID, user_id: str) -> bool:
        """Remove a folder from user's favorites.

        Returns:
            True if favorite was removed, False if not favorited
        """
        stmt = select(UserFolderFavorite).where(
            UserFolderFavorite.folder_id == folder_id, UserFolderFavorite.user_id == user_id
        )
        result = await db.execute(stmt)
        favorite = result.scalars().first()

        if not favorite:
            return False

        await db.delete(favorite)
        await db.commit()
        return True

    @staticmethod
    async def is_favorite(db: AsyncSession, folder_id: UUID, user_id: str) -> bool:
        """Check if a folder is favorited by a user."""
        stmt = select(UserFolderFavorite).where(
            UserFolderFavorite.folder_id == folder_id, UserFolderFavorite.user_id == user_id
        )
        result = await db.execute(stmt)
        return result.scalars().first() is not None

    @staticmethod
    async def get_user_favorite_folder_ids(db: AsyncSession, user_id: str, organization_id: str) -> set[UUID]:
        """Get all folder IDs favorited by a user in an organization."""
        """Get all folder IDs favorited by a user in an organization.

        Returns:
            Set of folder UUIDs that the user has favorited
        """
        stmt = (
            select(UserFolderFavorite.folder_id)
            .join(Folder, UserFolderFavorite.folder_id == Folder.id)
            .where(
                UserFolderFavorite.user_id == user_id,
                Folder.organization_id == organization_id,
                Folder.is_deleted.is_(False),
            )
        )
        result = await db.execute(stmt)
        return {row[0] for row in result.all()}

    # ==================== Company Access Control Methods ====================

    @staticmethod
    async def user_has_company_access(
        db: AsyncSession,
        company_id: int,
        user_id: str,
        organization_id: str,
        username: str | None = None,
        user_roles: list[str] | None = None,
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
            # Check if the company exists in any folder of the organization
            stmt = (
                select(FolderItem)
                .join(Folder, FolderItem.folder_id == Folder.id)
                .where(
                    FolderItem.item_id == str(company_id),
                    FolderItem.item_type == "company",
                    Folder.organization_id == organization_id,
                )
            )
            result = await db.execute(stmt)
            if result.scalars().first():
                logger.info(f"Manager access granted to company {company_id} for user {user_id}")
                return True
            return False

        # Find all folders containing this company in the organization
        stmt = (
            select(FolderItem)
            .join(Folder, FolderItem.folder_id == Folder.id)
            .where(
                FolderItem.item_id == str(company_id),
                FolderItem.item_type == "company",
                Folder.organization_id == organization_id,
                Folder.is_deleted.is_(False),
            )
        )
        result = await db.execute(stmt)
        folder_items = result.scalars().all()

        if not folder_items:
            logger.debug(f"Company {company_id} not found in any folder in organization {organization_id}")
            return False

        for folder_item in folder_items:
            folder_id = folder_item.folder_id
            if await FolderService.has_folder_access(
                db, folder_id, user_id, organization_id, username=username, user_roles=user_roles
            ):
                logger.debug(f"User {user_id} has access to company {company_id} via folder {folder_id}")
                return True

        logger.debug(f"User {user_id} does not have access to company {company_id}")
        return False

    @staticmethod
    async def _get_accessible_folder_ids(
        db: AsyncSession,
        user_id: str,
        organization_id: str,
        username: str | None = None,
    ) -> list:
        """Get all folder IDs accessible to a user (owned + shared).

        Lightweight query that returns only folder IDs without loading
        full Folder objects or pagination overhead.

        Args:
            db: Database session
            user_id: Keycloak user UUID
            organization_id: Organization UUID
            username: Optional username for legacy fallback when owner_id is NULL

        Returns:
            List of folder IDs the user has access to
        """
        ownership_conditions = [Folder.owner_id == user_id]
        if username:
            ownership_conditions.append(and_(Folder.owner_id.is_(None), Folder.owner == username))

        stmt = (
            select(Folder.id)
            .outerjoin(FolderShare, FolderShare.folder_id == Folder.id)
            .where(
                Folder.organization_id == organization_id,
                Folder.is_deleted.is_(False),
                or_(*ownership_conditions, FolderShare.user_id == user_id),
            )
            .distinct()
        )

        result = await db.execute(stmt)
        return [row[0] for row in result.all()]

    @staticmethod
    async def get_accessible_company_ids(
        db: AsyncSession, user_id: str, organization_id: str, username: str | None = None
    ) -> set[int]:
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
        folder_ids = await FolderService._get_accessible_folder_ids(
            db=db,
            organization_id=organization_id,
            user_id=user_id,
            username=username,
        )

        if not folder_ids:
            logger.debug(f"User {user_id} has no accessible folders")
            return set()

        # Get all company IDs from these folders
        stmt = select(FolderItem.item_id).where(FolderItem.folder_id.in_(folder_ids), FolderItem.item_type == "company")
        result = await db.execute(stmt)

        company_ids = set()
        for item in result.all():
            try:
                company_ids.add(int(item[0]))
            except (ValueError, TypeError):
                continue

        logger.debug(f"User {user_id} has access to {len(company_ids)} companies via {len(folder_ids)} folders")

        return company_ids
