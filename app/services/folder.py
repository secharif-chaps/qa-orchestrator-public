from typing import List, Optional, Dict, Any, Set
from uuid import UUID
from sqlalchemy.orm import Session
from datetime import datetime

from app.models import Folder, FolderItem, Company, UserFolderFavorite
from app.schemas.folder import FolderCreate, FolderUpdate


class FolderService:
    
    @staticmethod
    def create_folder(
        db: Session,
        organization_id: str,
        owner_id: str,
        owner_username: str,
        folder_data: FolderCreate
    ) -> Folder:
        """Create a new folder"""
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
        """Get a folder by ID"""
        query = db.query(Folder).filter(
            Folder.id == folder_id,
            Folder.organization_id == organization_id
        )

        if not include_deleted:
            query = query.filter(Folder.is_deleted == False)

        return query.first()
    
    @staticmethod
    def _get_folder_items_summary(
        db: Session, 
        folder_id: UUID,
        item_archived_filter: bool = False
    ) -> List[Dict[str, Any]]:
        """Get complete items for a folder - returns dicts for internal use"""
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
        """Get folder with summary of its items"""
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
        favorites_only: bool = False
    ) -> List[Folder]:
        """List all folders in an organization.

        Args:
            db: Database session
            organization_id: Organization to filter by
            user_id: Current user ID (required for favorites filtering)
            archived: If True, show deleted folders; if False, show active folders
            favorites_only: If True, only show folders favorited by this user
        """
        import logging
        logger = logging.getLogger(__name__)

        logger.debug(f"📁 list_folders - organization_id: {organization_id}, user_id: {user_id}, archived: {archived}, favorites_only: {favorites_only}")

        query = db.query(Folder).filter(Folder.organization_id == organization_id)

        if archived:
            logger.debug("📁 Filtering for archived (deleted) folders")
            query = query.filter(Folder.is_deleted == True)
        else:
            logger.debug("📁 Filtering for non-archived folders")
            query = query.filter(Folder.is_deleted == False)

        if favorites_only:
            logger.debug("📁 Filtering for user's favorites only")
            # Join with user_folder_favorites to filter by current user's favorites
            query = query.join(
                UserFolderFavorite,
                (UserFolderFavorite.folder_id == Folder.id) &
                (UserFolderFavorite.user_id == user_id)
            )

        # Log the SQL query
        logger.debug(f"📁 SQL Query: {query}")

        folders = query.order_by(Folder.created_at.desc()).all()

        logger.info(f"📁 list_folders result: Found {len(folders)} folders")
        for folder in folders:
            logger.debug(f"📁   - Folder: {folder.id} | {folder.name} | org: {folder.organization_id} | deleted: {folder.is_deleted}")

        # Don't assign items to the SQLAlchemy models directly
        # The endpoint will handle the response serialization
        return folders
    
    @staticmethod
    def update_folder(
        db: Session,
        folder: Folder,
        folder_update: FolderUpdate
    ) -> Folder:
        """Update a folder"""
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
        """Soft delete a folder"""
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
        """Restore a soft-deleted folder"""
        folder.is_deleted = False
        folder.updated_at = datetime.utcnow()
        db.commit()
        db.refresh(folder)
        return folder
    
    @staticmethod
    def add_item_to_folder(
        db: Session,
        folder_id: UUID,
        item_id: str,
        item_type: str,
        owner: str,  # Username
        position: Optional[int] = None
    ) -> FolderItem:
        """Add an item to a folder"""
        import logging
        logger = logging.getLogger(__name__)
        
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
            
            logger.debug(f"Creating new folder item - folder_id: {folder_id}, item_id: {item_id}, item_type: {item_type}, owner: {owner}")
            
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
        """Remove an item from a folder"""
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
    def get_folders_for_item(
        db: Session,
        item_id: str,
        item_type: str,
        organization_id: str
    ) -> List[Folder]:
        """Get all folders containing a specific item"""
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
            Folder.is_deleted == False
        ).all()

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
            Folder.is_deleted == False
        ).all()

        return {f[0] for f in favorites}