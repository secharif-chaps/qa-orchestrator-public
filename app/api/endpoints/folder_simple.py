from typing import List, Optional
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session

from app.database import get_db
from app.models import User
from app.core.dependencies import get_current_user
from app.schemas.user import TokenData
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.core.security import verify_workspace_permission_with_db
from app.schemas.folder import (
    FolderCreate, 
    FolderUpdate, 
    FolderResponse
)
from app.services.folder import FolderService


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
    
    # Get user from database
    user = db.query(User).filter(User.keycloak_id == workspace_context.user_id).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User not found in database"
        )
    
    folder_obj = FolderService.create_folder(
        db=db,
        workspace_id=workspace_context.workspace_id,
        owner_id=user.id,
        folder_data=folder
    )
    
    return folder_obj


@router.get("/", response_model=List[FolderResponse])
def list_folders(
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """List all folders in the workspace"""
    # Check workspace read permission
    verify_workspace_permission_with_db(current_user, workspace_context.workspace_id, "workspace.read", db)
    
    folders = FolderService.list_folders(
        db=db,
        workspace_id=workspace_context.workspace_id,
        include_deleted=False,
        favorites_only=False
    )
    
    return folders