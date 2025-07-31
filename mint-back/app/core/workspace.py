from typing import Optional, List
from fastapi import HTTPException, status, Depends
from sqlalchemy.orm import Session
from app.core.dependencies import get_current_user
from app.database import get_db
from app.models.workspace import Workspace, WorkspaceMember, WorkspaceMemberStatus
from app.schemas.user import TokenData


class WorkspaceContext:
    """Context object that holds workspace and user information"""
    
    def __init__(self, workspace: Workspace, user: TokenData, member: WorkspaceMember):
        self.workspace = workspace
        self.user = user
        self.member = member
    
    @property
    def workspace_id(self) -> int:
        return self.workspace.id
    
    @property
    def user_id(self) -> str:
        return self.user.sub or ""
    
    @property
    def username(self) -> str:
        return self.user.username or ""
    
    @property
    def is_active_member(self) -> bool:
        return self.member.status == WorkspaceMemberStatus.ACTIVE


def get_user_workspace(
    user: TokenData = Depends(get_current_user),
    db: Session = Depends(get_db)
) -> WorkspaceContext:
    """
    Get the current user's workspace context.
    For now, we assume users belong to the chapsvision workspace (id=1).
    In the future, this will be more sophisticated with workspace switching.
    """
    
    # For now, always use chapsvision workspace (id=1)
    workspace = db.query(Workspace).filter(Workspace.id == 1).first()
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Default workspace not found"
        )
    
    # Check if user is a member of this workspace
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace.id,
        WorkspaceMember.user_id == user.sub
    ).first()
    
    if not member:
        # Fallback: Create a new workspace member automatically for ChapsVision workspace
        member = WorkspaceMember(
            workspace_id=workspace.id,
            user_id=user.sub,
            username=user.username or user.sub,
            email=f"{user.username}@example.com" if user.username else f"{user.sub}@example.com",
            status=WorkspaceMemberStatus.ACTIVE
        )
        db.add(member)
        db.commit()
        db.refresh(member)
    
    if member.status != WorkspaceMemberStatus.ACTIVE:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="User access to workspace has been revoked"
        )
    
    return WorkspaceContext(workspace, user, member)


def get_workspace_member_by_id(
    member_user_id: str,
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
) -> WorkspaceMember:
    """Get a workspace member by user ID within the current workspace"""
    
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id,
        WorkspaceMember.user_id == member_user_id
    ).first()
    
    if not member:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Member not found in workspace"
        )
    
    return member


def require_workspace_permission(required_permission: str):
    """
    Decorator factory to check if user has required permission in workspace.
    This integrates with Keycloak permissions.
    """
    def permission_checker(workspace_context: WorkspaceContext = Depends(get_user_workspace)):
        user_permissions = get_user_permissions(workspace_context.user)
        
        if required_permission not in user_permissions:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Permission denied: {required_permission} required"
            )
        
        return workspace_context
    
    return permission_checker


def get_user_permissions(user: TokenData) -> List[str]:
    """
    Extract permissions from user's Keycloak token.
    This is a placeholder - in real implementation, this would parse
    the JWT token to extract permissions.
    
    TEMPORARY: Returning all permissions to bypass permission checks.
    """
    # TODO: Implement actual permission extraction from Keycloak token
    # For now, return all permissions to bypass checks
    return [
        "workspace.management",
        "screen.create",
        "screen.update", 
        "screen.delete",
        "screen.view",
    ]