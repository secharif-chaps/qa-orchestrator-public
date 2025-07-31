from datetime import datetime
from typing import List, Optional
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