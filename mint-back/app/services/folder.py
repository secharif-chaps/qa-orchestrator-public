from typing import List, Optional, Dict, Any
from uuid import UUID
from sqlalchemy.orm import Session
from sqlalchemy import and_, or_
from datetime import datetime

from app.models import Folder, FolderItem, Company, User
from app.schemas.folder import FolderCreate, FolderUpdate


class FolderService:
    
    @staticmethod
    def create_folder(
        db: Session,
        workspace_id: int,
        owner: str,
        folder_data: FolderCreate
    ) -> Folder:
        """Create a new folder"""
        folder = Folder(
            workspace_id=workspace_id,
            owner=owner,
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
        workspace_id: int,
        include_deleted: bool = False
    ) -> Optional[Folder]:
        """Get a folder by ID"""
        query = db.query(Folder).filter(
            Folder.id == folder_id,
            Folder.workspace_id == workspace_id
        )
        
        if not include_deleted:
            query = query.filter(Folder.is_deleted == False)
        
        return query.first()
    
    @staticmethod
    def _get_folder_items_summary(db: Session, folder_id: UUID) -> List[Dict[str, Any]]:
        """Get complete items for a folder - returns dicts for internal use"""
        items = []
        folder_items = db.query(FolderItem).filter(
            FolderItem.folder_id == folder_id
        ).order_by(FolderItem.position.nullsfirst(), FolderItem.added_at).all()
        
        for item in folder_items:
            if item.item_type == 'company':
                try:
                    company_id = int(item.item_id)
                    company = db.query(Company).filter(
                        Company.id == company_id,
                        Company.is_deleted == False
                    ).first()
                    
                    if company:
                        items.append({
                            'id': str(item.item_id),
                            'type': item.item_type,
                            'position': item.position,
                            'added_at': item.added_at.isoformat() if item.added_at else None,
                            'name': company.name,
                            'website': company.website,
                            'created_at': company.created_at.isoformat() if company.created_at else None,
                            'owner': company.owner_username or 'Unknown'
                        })
                except (ValueError, TypeError):
                    continue
            # Add support for other item types (contact, document) here in the future
        
        return items
    
    @staticmethod
    def get_folder_with_items(
        db: Session,
        folder_id: UUID,
        workspace_id: int
    ) -> Optional[Dict[str, Any]]:
        """Get folder with summary of its items"""
        folder = FolderService.get_folder(db, folder_id, workspace_id)
        if not folder:
            return None
        
        # Get folder items with company details
        items = []
        folder_items = db.query(FolderItem).filter(
            FolderItem.folder_id == folder_id
        ).order_by(FolderItem.position.nullsfirst(), FolderItem.added_at).all()
        
        for item in folder_items:
            if item.item_type == 'company':
                try:
                    company_id = int(item.item_id)
                    company = db.query(Company).filter(
                        Company.id == company_id,
                        Company.is_deleted == False
                    ).first()
                except (ValueError, TypeError):
                    # Skip invalid item_id
                    continue
                
                if company:
                    items.append({
                        'id': str(item.item_id),
                        'type': item.item_type,
                        'position': item.position,
                        'added_at': item.added_at.isoformat() if item.added_at else None,
                        'name': company.name,
                        'website': company.website,
                        'created_at': company.created_at.isoformat() if company.created_at else None,
                        'owner': company.owner_username
                    })
        
        return {
            'id': str(folder.id),
            'name': folder.name,
            'color': folder.color,
            'icon': folder.icon,
            'tags': folder.tags,
            'is_favorite': folder.is_favorite,
            'is_deleted': folder.is_deleted,
            'created_at': folder.created_at.isoformat() if folder.created_at else None,
            'updated_at': folder.updated_at.isoformat() if folder.updated_at else None,
            'owner': folder.owner,
            'workspace_id': folder.workspace_id,
            'items': items
        }
    
    @staticmethod
    def list_folders(
        db: Session,
        workspace_id: int,
        archived: bool = False,
        favorites_only: bool = False
    ) -> List[Folder]:
        """List all folders in a workspace with their items"""
        query = db.query(Folder).filter(Folder.workspace_id == workspace_id)
        
        if archived:
            query = query.filter(Folder.is_deleted == True)
        else:
            query = query.filter(Folder.is_deleted == False)
        
        if favorites_only:
            query = query.filter(Folder.is_favorite == True)
        
        folders = query.order_by(Folder.created_at.desc()).all()
        
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
                logger.debug(f"Item already exists in folder, updating position if provided")
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
        workspace_id: int
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
            Folder.workspace_id == workspace_id,
            Folder.is_deleted == False
        ).all()