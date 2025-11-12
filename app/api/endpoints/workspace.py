from typing import List, Optional
from datetime import timezone
from fastapi import APIRouter, Depends, HTTPException, status, Query
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session
from sqlalchemy import func, case
from app.database import get_db
from app.core.workspace import (
    get_user_workspace,
    WorkspaceContext
)
from app.core.keycloak import idp
from app.models.workspace import Workspace, WorkspaceMember, WorkspaceMemberStatus
# NOTE: No User model - user references handled via username strings only
from app.models.company import Company
from app.models.folder import Folder
from app.schemas.workspace import (
    WorkspaceResponse,
    WorkspaceWithMembersResponse,
    WorkspaceMemberResponse,
    WorkspaceMemberCreate,
    WorkspaceMemberUpdate,
    WorkspaceCreate,
    WorkspaceUpdate,
    WorkspaceWithMemberCount,
    ActivityResponse
)
from app.schemas.pagination import PaginatedResponse, SortOrder, create_pagination_meta
from app.schemas.workspace_user import (
    WorkspaceUserCreate,
    WorkspaceUserUpdate,
    WorkspaceUserResponse,
    WorkspaceUserListResponse,
    WorkspaceUserCreateResponse,
    UserQueryParams,
    UserStatusRequest,
    PasswordResetResponse
)
from app.schemas.admin_user import (
    AdminUserResponse,
    AdminUserListResponse,
    AssignWorkspaceRequest
)
from app.schemas.user import TokenData
from app.core.dependencies import get_current_user
from app.services.workspace_user import WorkspaceUserService

router = APIRouter(prefix="/workspace", tags=["workspace"])


@router.get("/current", response_model=WorkspaceResponse)
async def get_current_workspace(
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get current workspace information"""
    return workspace_context.workspace


@router.get("/{workspace_id}/activities", response_model=List[ActivityResponse])
async def get_workspace_activities(
    workspace_id: int,
    db: Session = Depends(get_db),
    current_user: TokenData = Depends(get_current_user)
) -> List[ActivityResponse]:
    """
    Get recent creation activities in the workspace (companies and folders created by other users).

    Returns:
        List of activities sorted by creation date (newest first), maximum 10 items
    """
    # Get current username
    current_username = current_user.username

    # Query last 10 companies created in workspace (exclude current user)
    companies = (
        db.query(Company)
        .filter(
            Company.workspace_id == workspace_id,
            Company.owner_username != current_username,
            Company.is_deleted == False
        )
        .order_by(Company.created_at.desc())
        .limit(10)
        .all()
    )

    # Query last 10 folders created in workspace (exclude current user)
    folders = (
        db.query(Folder)
        .filter(
            Folder.workspace_id == workspace_id,
            Folder.owner != current_username,
            Folder.is_deleted == False
        )
        .order_by(Folder.created_at.desc())
        .limit(10)
        .all()
    )

    # Merge and create activity objects
    activities = []

    for company in companies:
        # Make naive datetime timezone-aware (UTC) for consistent sorting
        created_at = company.created_at
        if created_at.tzinfo is None:
            created_at = created_at.replace(tzinfo=timezone.utc)

        activities.append({
            "type": "company",
            "id": company.id,
            "name": company.name,
            "owner_username": company.owner_username,
            "created_at": created_at
        })

    for folder in folders:
        # Folders already have timezone-aware datetimes
        activities.append({
            "type": "folder",
            "id": str(folder.id),  # UUID to string
            "name": folder.name,
            "owner_username": folder.owner,
            "created_at": folder.created_at
        })

    # Sort by created_at descending and return top 10
    activities.sort(key=lambda x: x["created_at"], reverse=True)
    return activities[:10]


@router.get("/current/members", response_model=List[WorkspaceMemberResponse])
async def get_workspace_members(
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Get all members of the current workspace"""
    members = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id
    ).all()
    
    return members


@router.post("/current/members", response_model=WorkspaceMemberResponse)
async def add_workspace_member(
    member_data: WorkspaceMemberCreate,
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Add a new member to the current workspace"""
    
    # Check if user already exists in this workspace
    existing_member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id,
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
        workspace_id=workspace_context.workspace_id,
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
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Update a workspace member"""
    
    # Get the member to update
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id,
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
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Remove a member from the current workspace"""
    
    # Get the member to remove
    member = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id,
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
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    db: Session = Depends(get_db)
):
    """Get workspace with all members"""
    
    # Get workspace with members
    workspace = workspace_context.workspace
    
    members = db.query(WorkspaceMember).filter(
        WorkspaceMember.workspace_id == workspace_context.workspace_id
    ).all()
    
    return {
        **workspace.__dict__,
        "members": members
    }


# Admin endpoints for workspace management (requires admin.workspaces role)

@router.get("/admin/all", response_model=PaginatedResponse[WorkspaceWithMemberCount])
async def get_all_workspaces(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    sort: str = Query('created_at', description="Field to sort by (name, created_at, member_count)"),
    order: SortOrder = Query(SortOrder.DESC, description="Sort order"),
    search: Optional[str] = Query(None, description="Search workspaces by name or slug"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get all workspaces with member counts (admin only).

    Requires admin.workspaces role for access.
    """
    
    # Build the base query with member count
    query = db.query(
        Workspace.id,
        Workspace.name,
        Workspace.description,
        Workspace.slug,
        Workspace.created_at,
        Workspace.updated_at,
        func.coalesce(func.count(WorkspaceMember.user_id), 0).label('member_count')
    ).outerjoin(
        WorkspaceMember,
        (Workspace.id == WorkspaceMember.workspace_id) & 
        (WorkspaceMember.status == WorkspaceMemberStatus.ACTIVE)
    ).group_by(
        Workspace.id,
        Workspace.name,
        Workspace.description,
        Workspace.slug,
        Workspace.created_at,
        Workspace.updated_at
    )
    
    # Apply search filter if provided
    if search:
        search_pattern = f"%{search}%"
        query = query.filter(
            (Workspace.name.ilike(search_pattern)) | 
            (Workspace.slug.ilike(search_pattern))
        )
    
    # Get total count before pagination
    total = query.count()
    
    # Apply sorting
    if sort == 'name':
        query = query.order_by(Workspace.name.asc() if order == SortOrder.ASC else Workspace.name.desc())
    elif sort == 'member_count':
        query = query.order_by(
            func.count(WorkspaceMember.user_id).asc() if order == SortOrder.ASC 
            else func.count(WorkspaceMember.user_id).desc()
        )
    else:  # Default to created_at
        query = query.order_by(
            Workspace.created_at.asc() if order == SortOrder.ASC 
            else Workspace.created_at.desc()
        )
    
    # Apply pagination
    offset = (page - 1) * limit
    results = query.offset(offset).limit(limit).all()
    
    # Convert results to WorkspaceWithMemberCount objects
    workspaces = []
    for row in results:
        workspace_dict = {
            'id': row.id,
            'name': row.name,
            'description': row.description,
            'slug': row.slug,
            'created_at': row.created_at,
            'updated_at': row.updated_at,
            'member_count': row.member_count
        }
        workspaces.append(WorkspaceWithMemberCount(**workspace_dict))
    
    # Create pagination metadata
    meta = create_pagination_meta(total=total, page=page, per_page=limit)
    
    return PaginatedResponse(data=workspaces, meta=meta)


@router.get("/admin/{workspace_id}/details", response_model=WorkspaceWithMemberCount)
async def get_workspace_details(
    workspace_id: int,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get single workspace with member count (admin only).

    Requires admin.workspaces role for access.
    """
    
    # Query workspace with member count using efficient JOIN
    result = db.query(
        Workspace.id,
        Workspace.name,
        Workspace.description,
        Workspace.slug,
        Workspace.created_at,
        Workspace.updated_at,
        func.count(WorkspaceMember.user_id.distinct()).label('member_count')
    ).outerjoin(
        WorkspaceMember,
        (Workspace.id == WorkspaceMember.workspace_id) & 
        (WorkspaceMember.status == WorkspaceMemberStatus.ACTIVE)
    ).filter(
        Workspace.id == workspace_id
    ).group_by(
        Workspace.id,
        Workspace.name,
        Workspace.description,
        Workspace.slug,
        Workspace.created_at,
        Workspace.updated_at
    ).first()
    
    # Check if workspace exists
    if not result:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )
    
    # Convert result to WorkspaceWithMemberCount
    workspace_dict = {
        'id': result.id,
        'name': result.name,
        'description': result.description,
        'slug': result.slug,
        'created_at': result.created_at,
        'updated_at': result.updated_at,
        'member_count': result.member_count
    }
    
    return WorkspaceWithMemberCount(**workspace_dict)


@router.post("/admin", response_model=WorkspaceResponse)
async def create_workspace(
    workspace_data: WorkspaceCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Create a new workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Update a workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Delete a workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get members of any workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
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


# Keycloak User Management Endpoints (requires admin.workspaces role)

@router.post("/admin/{workspace_id}/users", response_model=WorkspaceUserCreateResponse)
async def create_workspace_user(
    workspace_id: int,
    user_data: WorkspaceUserCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Create a new Keycloak user and add to workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    return await user_service.create_user(workspace_id, user_data)


@router.get("/admin/{workspace_id}/users", response_model=WorkspaceUserListResponse)
async def get_workspace_users(
    workspace_id: int,
    page: int = 0,
    limit: int = 20,
    search: Optional[str] = None,
    status: Optional[str] = None,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get paginated list of workspace users (admin only).

    Requires admin.workspaces role for access.
    """
    
    # Create query parameters
    query = UserQueryParams(
        page=page,
        limit=limit,
        search=search,
        status=status
    )
    
    user_service = WorkspaceUserService(db)
    return await user_service.get_users(workspace_id, query)


@router.get("/admin/{workspace_id}/users/{user_id}", response_model=WorkspaceUserResponse)
async def get_workspace_user(
    workspace_id: int,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get specific workspace user details (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    user = await user_service.get_user(workspace_id, user_id)
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )
    
    return user


@router.put("/admin/{workspace_id}/users/{user_id}", response_model=WorkspaceUserResponse)
async def update_workspace_user(
    workspace_id: int,
    user_id: str,
    user_data: WorkspaceUserUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Update workspace user details (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    return await user_service.update_user(workspace_id, user_id, user_data)


@router.patch("/admin/{workspace_id}/users/{user_id}/status", response_model=WorkspaceUserResponse)
async def toggle_user_status(
    workspace_id: int,
    user_id: str,
    status_data: UserStatusRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Enable or disable user account (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    return await user_service.toggle_user_status(workspace_id, user_id, status_data.enabled)


@router.delete("/admin/{workspace_id}/users/{user_id}")
async def remove_workspace_user(
    workspace_id: int,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Remove user from workspace (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    success = await user_service.delete_user(workspace_id, user_id)
    
    if success:
        return {"message": "User removed from workspace successfully"}
    else:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to remove user from workspace"
        )


@router.post("/admin/{workspace_id}/users/{user_id}/reset-password", response_model=PasswordResetResponse)
async def send_password_reset(
    workspace_id: int,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Send password reset email to user (admin only).

    Requires admin.workspaces role for access.
    """
    
    user_service = WorkspaceUserService(db)
    success = await user_service.send_password_reset(workspace_id, user_id)
    
    if success:
        return PasswordResetResponse(
            message="Password reset email sent successfully",
            userId=user_id
        )
    else:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to send password reset email"
        )


# Admin User Management Endpoints


@router.get("/admin/users", response_model=AdminUserListResponse)
async def get_all_users(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    search: Optional[str] = Query(None, description="Search by username or email"),
    workspace_filter: Optional[str] = Query(None, description="Filter by workspace: 'none', workspace_id, or null for all"),
    sort: str = Query('created_at', description="Sort field: username, workspace, created_at"),
    order: SortOrder = Query(SortOrder.DESC, description="Sort order"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Get all users across all workspaces with filtering and sorting (admin only).

    Requires admin.workspaces role for access.
    """

    # Build query with workspace join
    query = db.query(
        WorkspaceMember.user_id,
        WorkspaceMember.username,
        WorkspaceMember.email,
        WorkspaceMember.workspace_id,
        Workspace.name.label('workspace_name'),
        Workspace.slug.label('workspace_slug'),
        WorkspaceMember.status,
        WorkspaceMember.created_at
    ).outerjoin(Workspace, WorkspaceMember.workspace_id == Workspace.id)

    # Apply search filter
    if search:
        search_pattern = f"%{search}%"
        query = query.filter(
            (WorkspaceMember.username.ilike(search_pattern)) |
            (WorkspaceMember.email.ilike(search_pattern))
        )

    # Apply workspace filter
    if workspace_filter:
        if workspace_filter.lower() == 'none':
            query = query.filter(WorkspaceMember.workspace_id.is_(None))
        else:
            try:
                workspace_id = int(workspace_filter)
                query = query.filter(WorkspaceMember.workspace_id == workspace_id)
            except ValueError:
                pass  # Invalid workspace_filter, ignore

    # Get total count before pagination
    total = query.count()

    # Apply sorting
    if sort == 'username':
        query = query.order_by(
            WorkspaceMember.username.asc() if order == SortOrder.ASC
            else WorkspaceMember.username.desc()
        )
    elif sort == 'workspace':
        # Sort by workspace name, handling NULL values
        query = query.order_by(
            case(
                (Workspace.name.is_(None), ''),
                else_=Workspace.name
            ).asc() if order == SortOrder.ASC
            else case(
                (Workspace.name.is_(None), ''),
                else_=Workspace.name
            ).desc()
        )
    else:  # created_at
        query = query.order_by(
            WorkspaceMember.created_at.asc() if order == SortOrder.ASC
            else WorkspaceMember.created_at.desc()
        )

    # Apply pagination
    offset = (page - 1) * limit
    results = query.offset(offset).limit(limit).all()

    # Convert to response objects
    users = []
    for row in results:
        users.append(AdminUserResponse(
            user_id=row.user_id,
            username=row.username,
            email=row.email,
            workspace_id=row.workspace_id,
            workspace_name=row.workspace_name,
            workspace_slug=row.workspace_slug,
            status=row.status,
            created_at=row.created_at
        ))

    # Create pagination metadata
    meta = create_pagination_meta(total=total, page=page, per_page=limit)

    return AdminUserListResponse(data=users, pagination=meta)


@router.put("/admin/users/{user_id}/workspace", response_model=WorkspaceMemberResponse)
async def assign_user_workspace(
    user_id: str,
    request: AssignWorkspaceRequest,
    current_user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"])),
    db: Session = Depends(get_db)
):
    """Assign user to workspace or change their workspace (admin only).

    Requires admin.workspaces role for access.
    """

    # Verify workspace exists
    workspace = db.query(Workspace).filter(Workspace.id == request.workspace_id).first()
    if not workspace:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Workspace not found"
        )

    # Check if user already has workspace_member entry
    existing_member = db.query(WorkspaceMember).filter(
        WorkspaceMember.user_id == user_id
    ).first()

    if existing_member:
        # Update existing entry
        existing_member.workspace_id = request.workspace_id
        existing_member.status = WorkspaceMemberStatus.ACTIVE
        # Update the updated_at timestamp
        from sqlalchemy.sql import func
        existing_member.updated_at = func.now()
        db.commit()
        db.refresh(existing_member)
        return existing_member
    else:
        # Create new entry
        # Try to get username and email from current_user if this is the admin themselves
        if user_id == current_user.sub:
            username = getattr(current_user, 'username', None) or current_user.sub
            email = getattr(current_user, 'email', None) or f"{username}@example.com"
        else:
            # For other users, use basic info (ideally would fetch from Keycloak)
            username = user_id
            email = f"{user_id}@example.com"

        member = WorkspaceMember(
            workspace_id=request.workspace_id,
            user_id=user_id,
            username=username,
            email=email,
            status=WorkspaceMemberStatus.ACTIVE
        )
        db.add(member)
        db.commit()
        db.refresh(member)
        return member
