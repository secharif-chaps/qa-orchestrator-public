"""
Admin user management schemas
"""

from pydantic import BaseModel
from typing import Optional, List
from datetime import datetime
from app.models.workspace import WorkspaceMemberStatus
from app.schemas.pagination import PaginationMeta


class AdminUserResponse(BaseModel):
    """Response model for a single user in admin user list"""
    user_id: str
    username: str
    email: str
    workspace_id: Optional[int]
    workspace_name: Optional[str]
    workspace_slug: Optional[str]
    status: WorkspaceMemberStatus
    created_at: datetime

    class Config:
        from_attributes = True


class AdminUserListResponse(BaseModel):
    """Response model for paginated admin user list"""
    data: List[AdminUserResponse]
    pagination: PaginationMeta


class AdminUserQueryParams(BaseModel):
    """Query parameters for filtering and sorting admin user list"""
    page: int = 1
    limit: int = 20
    search: Optional[str] = None
    workspace_filter: Optional[str] = None
    sort: str = 'created_at'
    order: str = 'desc'


class AssignWorkspaceRequest(BaseModel):
    """Request body for assigning a user to a workspace"""
    workspace_id: int
