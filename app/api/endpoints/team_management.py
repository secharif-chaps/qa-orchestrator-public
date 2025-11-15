"""Team Management Endpoints.

Provides endpoints for managing users within workspaces.
Route: /api/workspaces/{workspaceId}/users

All endpoints require workspace.write role for access.
"""

from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session
from sqlalchemy import or_

from app.database import get_db
from app.core.keycloak import idp
from app.models.workspace import Workspace, WorkspaceMember, WorkspaceMemberStatus
# NOTE: No User model - user references handled via username strings only
from app.schemas.team_management import (
    WorkspaceUser,
    WorkspaceUserCreate,
    WorkspaceUserUpdate,
    TeamUserStatus,
    TeamUserSortField
)
from app.schemas.pagination import PaginatedResponse, create_pagination_meta
from app.services.permission import PermissionService

router = APIRouter(prefix="/workspaces", tags=["team-management"])


@router.get("/{workspace_id}/users", response_model=PaginatedResponse[WorkspaceUser])
async def list_workspace_users(
    workspace_id: int = Path(..., description="Workspace ID"),
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page"),
    search: Optional[str] = Query(None, max_length=100, description="Search term for name, email, username"),
    sort: TeamUserSortField = Query(TeamUserSortField.CREATED_AT, description="Field to sort by"),
    order: str = Query("desc", pattern="^(asc|desc)$", description="Sort order"),
    status: TeamUserStatus = Query(TeamUserStatus.ACTIVE, description="Filter by user status"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["workspace.write"])),
    db: Session = Depends(get_db)
):
    """List workspace users with pagination, search, and filtering.

    Requires workspace.write role for access.
    """
    
    # Verify workspace exists
    workspace = db.query(Workspace).filter(Workspace.id == workspace_id).first()
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    # Build base query for workspace members
    query = db.query(
        WorkspaceMember.id,
        WorkspaceMember.email,
        WorkspaceMember.username,
        WorkspaceMember.user_id,
        WorkspaceMember.first_name,
        WorkspaceMember.last_name,
        WorkspaceMember.created_at,
        WorkspaceMember.updated_at,
        WorkspaceMember.status
    ).filter(WorkspaceMember.workspace_id == workspace_id)
    
    # Apply status filter
    if status == TeamUserStatus.ACTIVE:
        query = query.filter(WorkspaceMember.status == WorkspaceMemberStatus.ACTIVE)
    elif status == TeamUserStatus.DISABLED:
        query = query.filter(WorkspaceMember.status == WorkspaceMemberStatus.REVOKED)
    # For 'all', no additional filter needed
    
    # Apply search filter
    if search:
        search_pattern = f"%{search}%"
        query = query.filter(
            or_(
                WorkspaceMember.username.ilike(search_pattern),
                WorkspaceMember.email.ilike(search_pattern),
                # For first_name + last_name search, we'd need to join with User table
                # For now, searching by username and email
            )
        )
    
    # Get total count for pagination
    total = query.count()
    
    # Apply sorting
    if sort == TeamUserSortField.USERNAME:
        order_by = WorkspaceMember.username.asc() if order == "asc" else WorkspaceMember.username.desc()
    elif sort == TeamUserSortField.EMAIL:
        order_by = WorkspaceMember.email.asc() if order == "asc" else WorkspaceMember.email.desc()
    elif sort == TeamUserSortField.NAME:
        # For now, sort by username since we don't have first_name/last_name in WorkspaceMember
        order_by = WorkspaceMember.username.asc() if order == "asc" else WorkspaceMember.username.desc()
    else:  # CREATED_AT
        order_by = WorkspaceMember.created_at.asc() if order == "asc" else WorkspaceMember.created_at.desc()
    
    query = query.order_by(order_by)
    
    # Apply pagination
    offset = (page - 1) * limit
    results = query.offset(offset).limit(limit).all()
    
    # Convert to WorkspaceUser objects
    users = []
    for member in results:
        # Use first_name and last_name from database if available
        first_name = getattr(member, 'first_name', None) or ""
        last_name = getattr(member, 'last_name', None) or ""
        
        # Fallback to extracting from username if names are empty
        if not first_name and not last_name:
            full_name_parts = member.username.split('.')
            first_name = full_name_parts[0].capitalize() if full_name_parts else ""
            last_name = full_name_parts[1].capitalize() if len(full_name_parts) > 1 else ""
        
        # Get permissions from database using permission service
        permission_service = PermissionService(db)
        user_permissions = permission_service.get_user_permissions_list(member.user_id, workspace_id)
        
        # If no database permissions found, fall back to JWT roles for current user
        if not user_permissions and member.user_id == current_user.sub:
            user_permissions = current_user.roles or ["organization.read"]
        elif not user_permissions:
            user_permissions = ["organization.read"]  # Default for other users
        
        user = WorkspaceUser(
            id=member.id,
            email=member.email,
            username=member.username,
            first_name=first_name,
            last_name=last_name,
            created_at=member.created_at.isoformat() if member.created_at else "",
            updated_at=member.updated_at.isoformat() if member.updated_at else "",
            is_disabled=(member.status == WorkspaceMemberStatus.REVOKED),
            permissions=user_permissions,
            created_by=None  # Would need to track this in the database
        )
        users.append(user)
    
    # Create pagination metadata
    meta = create_pagination_meta(total=total, page=page, per_page=limit)
    
    return PaginatedResponse(data=users, meta=meta)


@router.post("/{workspace_id}/users", response_model=WorkspaceUser)
async def create_workspace_user(
    workspace_id: int = Path(..., description="Workspace ID"),
    user_data: WorkspaceUserCreate = ...,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["workspace.write"])),
    db: Session = Depends(get_db)
):
    """Create a new user in the workspace.

    Requires workspace.write role for access.
    """
    
    # Verify workspace exists
    workspace = db.query(Workspace).filter(Workspace.id == workspace_id).first()
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    # Check if user already exists in this workspace
    existing_member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_id,
        or_(
            WorkspaceMember.email == user_data.email,
            WorkspaceMember.username == user_data.username
        )
    ).first()
    
    if existing_member:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="User with this email or username already exists in workspace"
        )
    
    try:
        # For now, create a workspace member directly
        # In a full implementation, this should integrate with Keycloak
        new_member = WorkspaceMember(
            workspace_id=workspace_id,
            user_id=f"keycloak_{user_data.username}",  # Would be actual Keycloak ID
            username=user_data.username,
            email=user_data.email,
            first_name=user_data.first_name,
            last_name=user_data.last_name,
            status=WorkspaceMemberStatus.ACTIVE
        )
        
        db.add(new_member)
        db.commit()
        db.refresh(new_member)
        
        # Convert to response format
        return WorkspaceUser(
            id=new_member.id,
            email=new_member.email,
            username=new_member.username,
            first_name=user_data.first_name or "",
            last_name=user_data.last_name or "",
            created_at=new_member.created_at.isoformat(),
            updated_at=new_member.updated_at.isoformat() if new_member.updated_at else new_member.created_at.isoformat(),
            is_disabled=False,
            permissions=user_data.permissions or ["organization.read"],  # Use provided permissions or default
            created_by=None  # Would track the creating user ID
        )
        
    except Exception as e:
        db.rollback()
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create user: {str(e)}"
        )


@router.patch("/{workspace_id}/users/{user_id}", response_model=WorkspaceUser)
async def update_workspace_user(
    workspace_id: int = Path(..., description="Workspace ID"),
    user_id: int = Path(..., description="User ID"),
    user_update: WorkspaceUserUpdate = ...,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["workspace.write"])),
    db: Session = Depends(get_db)
):
    """Update workspace user (disable/enable, permissions).

    Requires workspace.write role for access.
    """
    
    # Get the workspace member
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_id,
        WorkspaceMember.id == user_id
    ).first()
    
    if not member:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found in workspace"
        )
    
    try:
        # Initialize permission service
        permission_service = PermissionService(db)
        
        # Update status if provided
        if user_update.is_disabled is not None:
            member.status = WorkspaceMemberStatus.REVOKED if user_update.is_disabled else WorkspaceMemberStatus.ACTIVE
        
        # Update first_name and last_name if provided
        if user_update.first_name is not None:
            member.first_name = user_update.first_name
        if user_update.last_name is not None:
            member.last_name = user_update.last_name
        
        # Sync permissions if provided
        if user_update.permissions is not None:
            await permission_service.sync_user_workspace_permissions(
                user_id=member.user_id,
                workspace_id=workspace_id,
                new_permissions=user_update.permissions,
                granted_by=user.sub
            )
        
        db.commit()
        db.refresh(member)
        
        # Get updated permissions from database
        user_permissions = permission_service.get_user_permissions_list(member.user_id, workspace_id)

        # Fallback if no permissions found
        if not user_permissions and member.user_id == user.sub:
            user_permissions = user.roles or ["organization.read"]
        elif not user_permissions:
            user_permissions = ["organization.read"]
        
        # Convert to response format
        return WorkspaceUser(
            id=member.id,
            email=member.email,
            username=member.username,
            first_name=member.first_name or "",
            last_name=member.last_name or "",
            created_at=member.created_at.isoformat(),
            updated_at=member.updated_at.isoformat() if member.updated_at else member.created_at.isoformat(),
            is_disabled=(member.status == WorkspaceMemberStatus.REVOKED),
            permissions=user_permissions,
            created_by=None
        )
        
    except Exception as e:
        db.rollback()
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update user: {str(e)}"
        )


@router.get("/{workspace_id}/users/{user_id}", response_model=WorkspaceUser)
async def get_workspace_user(
    workspace_id: int = Path(..., description="Workspace ID"),
    user_id: int = Path(..., description="User ID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["workspace.write"])),
    db: Session = Depends(get_db)
):
    """Get single workspace user details.

    Requires workspace.write role for access.
    """
    
    # Get the workspace member
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_id,
        WorkspaceMember.id == user_id
    ).first()
    
    if not member:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found in workspace"
        )
    
    # Get permissions from database
    permission_service = PermissionService(db)
    user_permissions = permission_service.get_user_permissions_list(member.user_id, workspace_id)
    
    # If no database permissions found, fall back to JWT roles for current user
    if not user_permissions and member.user_id == current_user.sub:
        user_permissions = current_user.roles or ["organization.read"]
    elif not user_permissions:
        user_permissions = ["organization.read"]  # Default
    
    # Convert to response format
    return WorkspaceUser(
        id=member.id,
        email=member.email,
        username=member.username,
        first_name=member.first_name or "",
        last_name=member.last_name or "",
        created_at=member.created_at.isoformat(),
        updated_at=member.updated_at.isoformat() if member.updated_at else member.created_at.isoformat(),
        is_disabled=(member.status == WorkspaceMemberStatus.REVOKED),
        permissions=user_permissions,
        created_by=None  # Would need to track this
    )