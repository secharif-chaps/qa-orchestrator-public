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
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.create_folder(
        db=db,
        workspace_id=workspace_context.workspace_id,
        owner_username=workspace_context.username,
        folder_data=folder
    )
    
    return folder


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
        workspace_id=workspace_context.workspace.id,
        include_deleted=include_deleted,
        favorites_only=favorites
    )
    
    return folders


@router.get("/{folder_id}", response_model=FolderWithItemsResponse)
def get_folder(
    folder_id: UUID,
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Get a folder with its items"""
    # Check workspace read permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.read", db)
    
    folder_data = FolderService.get_folder_with_items(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace.id
    )
    
    if not folder_data:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )
    
    return folder_data


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
        workspace_id=workspace_context.workspace.id
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
        workspace_id=workspace_context.workspace.id
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
        workspace_id=workspace_context.workspace.id
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
        workspace_id=workspace_context.workspace.id,
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
    # Check workspace write permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.write", db)
    
    folder = FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        workspace_id=workspace_context.workspace.id
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
        added_by_username=workspace_context.username,
        position=item.position
    )
    
    return folder_item


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
        workspace_id=workspace_context.workspace.id
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