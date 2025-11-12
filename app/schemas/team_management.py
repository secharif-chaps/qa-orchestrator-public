"""
Schemas for team management feature
"""

from typing import List, Optional
from pydantic import BaseModel, Field, EmailStr, validator
from enum import Enum


class TeamUserStatus(str, Enum):
    ACTIVE = "active"
    DISABLED = "disabled"
    ALL = "all"


class TeamUserSortField(str, Enum):
    NAME = "name"
    EMAIL = "email"
    CREATED_AT = "created_at"
    USERNAME = "username"


class WorkspaceUser(BaseModel):
    """Response model for workspace user"""
    id: int = Field(..., description="User ID")
    email: str = Field(..., description="Email address")
    username: str = Field(..., description="Username")
    first_name: str = Field(..., description="First name")
    last_name: str = Field(..., description="Last name")
    created_at: str = Field(..., description="ISO date when user was created")
    updated_at: str = Field(..., description="ISO date when user was last updated")
    is_disabled: bool = Field(..., description="Whether user is disabled")
    permissions: List[str] = Field(..., description="List of user permissions")
    created_by: Optional[int] = Field(None, description="ID of user who created this user")

    class Config:
        from_attributes = True


class WorkspaceUserCreate(BaseModel):
    """Schema for creating a new workspace user"""
    email: EmailStr = Field(..., description="Email address for the new user")
    username: str = Field(..., min_length=3, max_length=50, description="Username for the new user")
    password: str = Field(..., min_length=8, max_length=128, description="Password for the new user")
    first_name: Optional[str] = Field(None, max_length=100, description="First name")
    last_name: Optional[str] = Field(None, max_length=100, description="Last name")
    permissions: Optional[List[str]] = Field(default_factory=list, description="Initial permissions for the user")
    
    @validator('username')
    def validate_username(cls, v):
        if not v.replace('_', '').replace('-', '').replace('.', '').isalnum():
            raise ValueError('Username can only contain letters, numbers, hyphens, underscores, and dots')
        return v.lower()


class WorkspaceUserUpdate(BaseModel):
    """Schema for updating workspace user details"""
    is_disabled: Optional[bool] = Field(None, description="Whether to disable/enable the user")
    permissions: Optional[List[str]] = Field(None, description="Updated permissions for the user")
    first_name: Optional[str] = Field(None, max_length=100, description="Updated first name")
    last_name: Optional[str] = Field(None, max_length=100, description="Updated last name")


class WorkspaceUserListParams(BaseModel):
    """Query parameters for listing workspace users"""
    page: int = Field(1, ge=1, description="Page number (starting from 1)")
    limit: int = Field(20, ge=1, le=100, description="Items per page")
    search: Optional[str] = Field(None, max_length=100, description="Search term for name, email, username")
    sort: TeamUserSortField = Field(TeamUserSortField.CREATED_AT, description="Field to sort by")
    order: str = Field("desc", pattern="^(asc|desc)$", description="Sort order")
    status: TeamUserStatus = Field(TeamUserStatus.ACTIVE, description="Filter by user status")

    class Config:
        use_enum_values = True