from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.database import get_db
from app.core.workspace import (
    get_user_workspace, 
    WorkspaceContext
)
from app.models.workspace import Workspace, WorkspaceMember, WorkspaceMemberStatus
from app.models.user import User
from app.models.company import Company
from app.schemas.workspace import (
    WorkspaceResponse,
    WorkspaceWithMembersResponse,
    WorkspaceMemberResponse,
    WorkspaceMemberCreate,
    WorkspaceMemberUpdate,
    WorkspaceCreate,
    WorkspaceUpdate
)
from app.schemas.user import TokenData
from app.core.dependencies import get_current_user
from app.core.security import verify_workspace_admin_access

router = APIRouter(prefix="/workspace", tags=["workspace"])


@router.get("/current", response_model=WorkspaceResponse)
async def get_current_workspace(
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get current workspace information"""
    return workspace_context.workspace


@router.get("/current/members", response_model=List[WorkspaceMemberResponse])
async def get_workspace_members(
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get all members of the current workspace"""
    members = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == 1
    ).all()
    
    return members


@router.post("/current/members", response_model=WorkspaceMemberResponse)
async def add_workspace_member(
    member_data: WorkspaceMemberCreate,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Add a new member to the current workspace"""
    
    # Check if user already exists in this workspace
    existing_member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == 1,
        WorkspaceMember.email == member_data.email
    ).first()
    
    if existing_member:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="User is already a member of this workspace"
        )
    
    # TODO: Create Keycloak user account and get user_id
    # For now, we'll use a placeholder user_id
    user_id = f"keycloak_{member_data.email.replace('@', '_').replace('.', '_')}"
    username = member_data.username or member_data.email.split('@')[0]
    
    # Create workspace member
    member = WorkspaceMember(
        workspace_id=1,
        user_id=user_id,
        username=username,
        email=member_data.email,
        status=WorkspaceMemberStatus.ACTIVE
    )
    
    db.add(member)
    db.commit()
    db.refresh(member)
    
    return member


@router.put("/current/members/{member_user_id}", response_model=WorkspaceMemberResponse)
async def update_workspace_member(
    member_user_id: str,
    member_update: WorkspaceMemberUpdate,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update a workspace member"""
    
    # Get the member to update
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == 1,
        WorkspaceMember.user_id == member_user_id
    ).first()
    
    if not member:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Member not found in workspace"
        )
    
    # Update member status
    if member_update.status is not None:
        member.status = member_update.status
    
    db.commit()
    db.refresh(member)
    
    return member


@router.delete("/current/members/{member_user_id}")
async def remove_workspace_member(
    member_user_id: str,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Remove a member from the current workspace"""
    
    # Get the member to remove
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == 1,
        WorkspaceMember.user_id == member_user_id
    ).first()
    
    if not member:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Member not found in workspace"
        )
    
    # Set status to revoked instead of deleting
    member.status = WorkspaceMemberStatus.REVOKED
    
    db.commit()
    
    return {"message": "Member access revoked successfully"}


@router.post("/join", response_model=WorkspaceMemberResponse)
async def join_workspace(
    user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Allow a user to join the default workspace (ChapsVision)"""
    
    # For now, always join the ChapsVision workspace (id=1)
    workspace_id = 1
    
    # Check if user is already a member
    existing_member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_id,
        WorkspaceMember.user_id == user.sub
    ).first()
    
    if existing_member:
        if existing_member.status == WorkspaceMemberStatus.REVOKED:
            # Reactivate the member
            existing_member.status = WorkspaceMemberStatus.ACTIVE
            db.commit()
            db.refresh(existing_member)
            return existing_member
        else:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="User is already a member of this workspace"
            )
    
    # Create new workspace member
    member = WorkspaceMember(
        workspace_id=workspace_id,
        user_id=user.sub,
        username=user.username or user.sub,
        email=f"{user.username}@example.com" if user.username else f"{user.sub}@example.com",
        status=WorkspaceMemberStatus.ACTIVE
    )
    
    db.add(member)
    db.commit()
    db.refresh(member)
    
    return member


@router.get("/current/with-members", response_model=WorkspaceWithMembersResponse)
async def get_workspace_with_members(
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get workspace with all members"""
    
    # Get workspace with members
    workspace = db.query(Workspace).filter(
        Workspace.id == 1
    ).first()
    
    members = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == 1
    ).all()
    
    return {
        **workspace.__dict__,
        "members": members
    }


# Admin endpoints for workspace management (requires admin.workspaces role)

@router.get("/admin/all", response_model=List[WorkspaceResponse])
async def get_all_workspaces(
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get all workspaces (admin only)"""
    # Verify admin access
    verify_workspace_admin_access(current_user)
    
    workspaces = db.query(Workspace).all()
    return workspaces


@router.post("/admin", response_model=WorkspaceResponse)
async def create_workspace(
    workspace_data: WorkspaceCreate,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Create a new workspace (admin only)"""
    # Verify admin access
    verify_workspace_admin_access(current_user)
    
    # Check if slug already exists
    existing_workspace = db.query(Workspace).filter(
        Workspace.slug == workspace_data.slug
    ).first()
    
    if existing_workspace:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Workspace with this slug already exists"
        )
    
    # Create new workspace
    workspace = Workspace(
        name=workspace_data.name,
        description=workspace_data.description,
        slug=workspace_data.slug
    )
    
    db.add(workspace)
    db.commit()
    db.refresh(workspace)
    
    return workspace


@router.put("/admin/{workspace_id}", response_model=WorkspaceResponse)
async def update_workspace(
    workspace_id: int,
    workspace_data: WorkspaceUpdate,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update a workspace (admin only)"""
    # Verify admin access
    verify_workspace_admin_access(current_user)
    
    # Get workspace to update
    workspace = db.query(Workspace).filter(Workspace.id == workspace_id).first()
    
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    # Check if new slug conflicts with existing workspace
    if workspace_data.slug and workspace_data.slug != workspace.slug:
        existing_workspace = db.query(Workspace).filter(
            Workspace.slug == workspace_data.slug,
            Workspace.id != workspace_id
        ).first()
        
        if existing_workspace:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Workspace with this slug already exists"
            )
    
    # Update workspace fields
    if workspace_data.name is not None:
        workspace.name = workspace_data.name
    if workspace_data.description is not None:
        workspace.description = workspace_data.description
    if workspace_data.slug is not None:
        workspace.slug = workspace_data.slug
    
    db.commit()
    db.refresh(workspace)
    
    return workspace


@router.delete("/admin/{workspace_id}")
async def delete_workspace(
    workspace_id: int,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete a workspace (admin only)"""
    # Verify admin access
    verify_workspace_admin_access(current_user)
    
    # Get workspace to delete
    workspace = db.query(Workspace).filter(Workspace.id == workspace_id).first()
    
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    # Don't allow deleting the default workspace (ChapsVision)
    if workspace_id == 1:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot delete the default workspace"
        )
    
    # Check if workspace has companies or members
    companies_count = db.query(Company).filter(Company.workspace_id == workspace_id).count()
    members_count = db.query(WorkspaceMember).filter(WorkspaceMember.workspace_id == workspace_id).count()
    
    if companies_count > 0 or members_count > 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Cannot delete workspace with {companies_count} companies and {members_count} members. Remove all content first."
        )
    
    # Delete the workspace
    db.delete(workspace)
    db.commit()
    
    return {"message": f"Workspace '{workspace.name}' deleted successfully"}


@router.get("/admin/{workspace_id}/members", response_model=List[WorkspaceMemberResponse])
async def get_workspace_members_admin(
    workspace_id: int,
    current_user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get members of any workspace (admin only)"""
    # Verify admin access
    verify_workspace_admin_access(current_user)
    
    # Verify workspace exists
    workspace = db.query(Workspace).filter(Workspace.id == workspace_id).first()
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    members = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_id
    ).all()
    
    return members