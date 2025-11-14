from datetime import datetime
from typing import List, Optional, Literal, Union
from pydantic import BaseModel, Field
from app.models.workspace import WorkspaceMemberStatus


class WorkspaceBase(BaseModel):
    name: str = Field(..., min_length=1, max_length=100)
    description: Optional[str] = Field(None, max_length=500)
    slug: str = Field(..., min_length=1, max_length=50)


class WorkspaceCreate(WorkspaceBase):
    pass


class WorkspaceUpdate(BaseModel):
    name: Optional[str] = Field(None, min_length=1, max_length=100)
    description: Optional[str] = Field(None, max_length=500)
    slug: Optional[str] = Field(None, min_length=1, max_length=50)


class WorkspaceResponse(WorkspaceBase):
    id: int
    created_at: datetime
    updated_at: Optional[datetime] = None
    
    class Config:
        from_attributes = True


class WorkspaceWithMemberCount(WorkspaceResponse):
    member_count: int = Field(..., description="Number of active members in the workspace")


class WorkspaceMemberBase(BaseModel):
    user_id: str
    username: str
    email: str
    status: WorkspaceMemberStatus = WorkspaceMemberStatus.ACTIVE


class WorkspaceMemberCreate(BaseModel):
    email: str = Field(..., pattern=r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$')
    username: Optional[str] = None
    temporary_password: Optional[str] = None


class WorkspaceMemberUpdate(BaseModel):
    status: Optional[WorkspaceMemberStatus] = None


class WorkspaceMemberResponse(WorkspaceMemberBase):
    id: int
    workspace_id: int
    created_at: datetime
    updated_at: Optional[datetime] = None
    
    class Config:
        from_attributes = True


class WorkspaceWithMembersResponse(WorkspaceResponse):
    members: List[WorkspaceMemberResponse] = []


class WorkspaceListResponse(BaseModel):
    workspaces: List[WorkspaceResponse]
    total: int


# Keycloak Organization Schemas (for /organizations endpoints)
class OrganizationResponse(BaseModel):
    """Response schema for Keycloak organizations.

    Organizations are managed in Keycloak and have different structure than database workspaces:
    - id is a UUID string (not integer)
    - created_at/updated_at are optional (may not be available from Keycloak API)
    - member_count is calculated from Keycloak membership
    """
    id: str  # Keycloak organization UUID
    name: str
    description: Optional[str] = None
    slug: str  # Alias or derived from name
    created_at: Optional[datetime] = None  # May not be available from Keycloak
    updated_at: Optional[datetime] = None
    member_count: int = 0

    class Config:
        from_attributes = True


class ActivityResponse(BaseModel):
    """Response model for workspace activity items (companies and folders)"""
    type: Literal["company", "folder"]
    id: Union[int, str]  # int for companies, str for folders (UUID)
    name: str
    owner_username: str
    created_at: datetime

    class Config:
        from_attributes = True