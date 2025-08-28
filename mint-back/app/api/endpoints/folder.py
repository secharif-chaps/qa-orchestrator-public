from typing import List, Optional
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session

from app.database import get_db
from app.models import User
from app.core.dependencies import get_current_user
from app.schemas.user import TokenData
from app.schemas.folder import (
    FolderCreate, 
    FolderUpdate, 
    FolderResponse, 
    FolderWithItemsResponse,
    FolderItemAdd,
    FolderItemResponse
)
from app.services.folder import FolderService
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.core.security import verify_workspace_permission_with_db


router = APIRouter()


@router.post("/", response_model=FolderResponse)
def create_folder(
    folder: FolderCreate,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Create a new folder in the workspace"""
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        logger.info(f"📁 POST /folders - START - User: {workspace_context.username}, Folder: {folder.name}")
        
        # Check workspace write permission
        logger.debug(f"📁 Checking workspace write permission for user {current_user.username} in workspace {workspace_context.workspace_id}")
        verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
        logger.debug(f"✅ Permission check passed")
        
        logger.debug(f"📁 Creating folder with owner_username={workspace_context.username}, workspace_id={workspace_context.workspace_id}")
        folder_obj = FolderService.create_folder(
            db=db,
            workspace_id=workspace_context.workspace_id,
            owner_username=workspace_context.username,
            folder_data=folder
        )
        logger.info(f"✅ Folder created successfully - ID: {folder_obj.id}, Name: {folder_obj.name}")
        
        # Debug the folder object before returning
        logger.debug(f"📁 Folder object type: {type(folder_obj)}")
        logger.debug(f"📁 Folder attributes: id={folder_obj.id}, name={folder_obj.name}, owner_username={getattr(folder_obj, 'owner_username', 'MISSING')}")
        
        # Check if owner_id is None (could cause serialization issues)
        if hasattr(folder_obj, 'owner_id'):
            logger.debug(f"📁 owner_id value: {folder_obj.owner_id}")
        
        logger.info(f"📁 About to return folder object")
        return folder_obj
        
    except Exception as e:
        logger.error(f"❌ Error in create_folder: {type(e).__name__}: {str(e)}")
        logger.error(f"❌ Full exception details:", exc_info=True)
        raise


@router.get("/", response_model=List[FolderResponse])
def list_folders(
    archived: Optional[str] = Query(None, pattern="^(include|only)$"),
    favorites: bool = Query(False),
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """List all folders in the workspace"""
    # Check workspace read permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.read", db)
    
    include_deleted = False
    if archived == "include":
        include_deleted = True
    elif archived == "only":
        include_deleted = "only"
    
    folders = FolderService.list_folders(
        db=db,
        workspace_id=workspace_context.workspace_id,
        include_deleted=include_deleted,
        favorites_only=favorites
    )
    
    # Build the response with items for each folder
    response_folders = []
    for folder in folders:
        folder_items = FolderService._get_folder_items_summary(db, folder.id)
        
        folder_dict = {
            "id": folder.id,
            "workspace_id": folder.workspace_id,
            "owner_id": folder.owner_id,
            "owner_username": folder.owner_username or "Unknown",
            "name": folder.name,
            "color": folder.color,
            "icon": folder.icon,
            "tags": folder.tags,
            "is_favorite": folder.is_favorite,
            "is_deleted": folder.is_deleted,
            "created_at": folder.created_at,
            "updated_at": folder.updated_at,
            "items": folder_items
        }
        response_folders.append(folder_dict)
    
    return response_folders


@router.get("/{folder_id}", response_model=FolderWithItemsResponse)
def get_folder(
    folder_id: UUID,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Get a folder with its items"""
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        logger.info(f"📁 GET /folders/{folder_id} - START - User: {workspace_context.username}")
        
        # Check workspace read permission
        logger.debug(f"📁 Checking workspace read permission")
        verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.read", db)
        logger.debug(f"✅ Permission check passed")
        
        # Fix: Use workspace_context.workspace_id instead of workspace_context.workspace_id
        logger.debug(f"📁 Getting folder with items - folder_id={folder_id}, workspace_id={workspace_context.workspace_id}")
        folder_data = FolderService.get_folder_with_items(
            db=db,
            folder_id=folder_id,
            workspace_id=workspace_context.workspace_id  # Fixed: was workspace_context.workspace_id
        )
        
        if not folder_data:
            logger.warning(f"❌ Folder {folder_id} not found in workspace {workspace_context.workspace_id}")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )
        
        logger.debug(f"📁 Folder data retrieved: {type(folder_data)}")
        logger.debug(f"📁 Folder data keys: {folder_data.keys() if isinstance(folder_data, dict) else 'Not a dict'}")
        logger.info(f"✅ Returning folder data for {folder_id}")
        return folder_data
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ Error in get_folder: {type(e).__name__}: {str(e)}")
        logger.error(f"❌ Full exception details:", exc_info=True)
        raise


@router.put("/{folder_id}", response_model=FolderResponse)
def update_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Update a folder"""
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace_id
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )
    
    return updated_folder


@router.patch("/{folder_id}", response_model=FolderResponse)
def patch_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Partially update a folder (for favorite toggle, etc.)"""
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace_id
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )
    
    return updated_folder


@router.delete("/{folder_id}", response_model=FolderResponse)
def delete_folder(
    folder_id: UUID,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Soft delete a folder"""
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace_id
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    deleted_folder = FolderService.soft_delete_folder(db=db, folder=folder)
    
    return deleted_folder


@router.post("/{folder_id}/restore", response_model=FolderResponse)
def restore_folder(
    folder_id: UUID,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Restore a soft-deleted folder"""
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace_id,
        include_deleted=True
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    if not folder.is_deleted:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder is not deleted"
        )
    
    restored_folder = FolderService.restore_folder(db=db, folder=folder)
    
    return restored_folder


@router.post("/{folder_id}/items", response_model=FolderItemResponse)
def add_item_to_folder(
    folder_id: UUID,
    item: FolderItemAdd,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Add an item to a folder"""
    import logging
    logger = logging.getLogger(__name__)
    
    try:
        logger.debug(f"Adding item to folder - folder_id: {folder_id}, item_id: {item.item_id}, item_type: {item.item_type}")
        logger.debug(f"User context - user_id: {current_user.sub}, username: {workspace_context.username}")
        
        # Check workspace write permission
        verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
        
        folder = FolderService.get_folder(
            db=db,
            folder_id=folder_id,
            workspace_id=workspace_context.workspace_id
        )
        
        if not folder:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )
        
        folder_item = FolderService.add_item_to_folder(
            db=db,
            folder_id=folder_id,
            item_id=item.item_id,
            item_type=item.item_type,
            owner=workspace_context.username,
            position=item.position
        )
        
        logger.debug(f"Successfully added item to folder - folder_item_id: {folder_item.id}")
        return folder_item
    except Exception as e:
        logger.error(f"Error adding item to folder: {str(e)}", exc_info=True)
        raise


@router.delete("/{folder_id}/items/{item_id}")
def remove_item_from_folder(
    folder_id: UUID,
    item_id: UUID,
    item_type: str = Query(..., pattern="^(company|contact|document)$"),
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Remove an item from a folder"""
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace_id
    )
    
    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    removed = FolderService.remove_item_from_folder(
        db=db,
        folder_id=folder_id,
        item_id=item_id,
        item_type=item_type
    )
    
    if not removed:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Item not found in folder"
        )
    
    return {"message": "Item removed from folder"}