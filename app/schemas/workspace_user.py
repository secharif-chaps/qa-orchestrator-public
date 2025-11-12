"""
Schemas for workspace user management
"""

from typing import List, Optional
from pydantic import BaseModel, Field, EmailStr, validator
from enum import Enum


class UserStatus(str, Enum):
    ACTIVE = "ACTIVE"
    INACTIVE = "INACTIVE"
    PENDING = "PENDING"


class WorkspaceUserCreate(BaseModel):
    """Schema for creating a new workspace user"""
    username: str = Field(..., min_length=3, max_length=50, description="Username for the new user")
    email: EmailStr = Field(..., description="Email address for the new user")
    firstName: Optional[str] = Field(None, max_length=100, description="First name")
    lastName: Optional[str] = Field(None, max_length=100, description="Last name")
    temporaryPassword: Optional[str] = Field(None, min_length=8, max_length=128, description="Temporary password (auto-generated if not provided)")
    
    @validator('username')
    def validate_username(cls, v):
        if not v.replace('_', '').replace('-', '').replace('.', '').isalnum():
            raise ValueError('Username can only contain letters, numbers, hyphens, underscores, and dots')
        return v.lower()


class WorkspaceUserUpdate(BaseModel):
    """Schema for updating workspace user details"""
    username: Optional[str] = Field(None, min_length=3, max_length=50)
    email: Optional[EmailStr] = None
    firstName: Optional[str] = Field(None, max_length=100)
    lastName: Optional[str] = Field(None, max_length=100)
    enabled: Optional[bool] = None
    
    @validator('username')
    def validate_username(cls, v):
        if v and not v.replace('_', '').replace('-', '').replace('.', '').isalnum():
            raise ValueError('Username can only contain letters, numbers, hyphens, underscores, and dots')
        return v.lower() if v else v


class WorkspaceUserResponse(BaseModel):
    """Schema for workspace user response"""
    id: str = Field(..., description="Keycloak user ID")
    username: str = Field(..., description="Username")
    email: str = Field(..., description="Email address")
    firstName: Optional[str] = Field(None, description="First name")
    lastName: Optional[str] = Field(None, description="Last name")
    enabled: bool = Field(..., description="Whether user account is enabled")
    emailVerified: bool = Field(..., description="Whether email is verified")
    createdAt: str = Field(..., description="ISO date when user was created")
    lastLogin: Optional[str] = Field(None, description="ISO date of last login")
    status: UserStatus = Field(..., description="User status")
    
    class Config:
        use_enum_values = True


class WorkspaceUserListResponse(BaseModel):
    """Schema for paginated user list response"""
    users: List[WorkspaceUserResponse] = Field(..., description="List of users")
    total: int = Field(..., description="Total number of users")
    page: int = Field(..., description="Current page number (0-based)")
    limit: int = Field(..., description="Number of items per page")
    
    @validator('page')
    def validate_page(cls, v):
        if v < 0:
            raise ValueError('Page must be non-negative')
        return v
    
    @validator('limit')
    def validate_limit(cls, v):
        if v < 1 or v > 100:
            raise ValueError('Limit must be between 1 and 100')
        return v


class UserStatusRequest(BaseModel):
    """Schema for user status toggle request"""
    enabled: bool = Field(..., description="Whether to enable or disable the user")


class PasswordResetResponse(BaseModel):
    """Schema for password reset response"""
    message: str = Field(..., description="Success message")
    userId: str = Field(..., description="User ID")


class WorkspaceUserCreateResponse(WorkspaceUserResponse):
    """Schema for user creation response (includes temporary password)"""
    temporaryPassword: Optional[str] = Field(None, description="Temporary password (only returned on creation)")


class UserQueryParams(BaseModel):
    """Schema for user list query parameters"""
    page: int = Field(0, ge=0, description="Page number (0-based)")
    limit: int = Field(20, ge=1, le=100, description="Number of items per page")
    search: Optional[str] = Field(None, max_length=100, description="Search term for username or email")
    status: Optional[UserStatus] = Field(None, description="Filter by user status")
    
    class Config:
        use_enum_values = True


class WorkspaceUserStats(BaseModel):
    """Schema for workspace user statistics"""
    totalUsers: int = Field(..., description="Total number of users")
    activeUsers: int = Field(..., description="Number of active users")
    inactiveUsers: int = Field(..., description="Number of inactive users")
    pendingUsers: int = Field(..., description="Number of pending users")


class BulkUserAction(BaseModel):
    """Schema for bulk user actions"""
    userIds: List[str] = Field(..., min_items=1, max_items=50, description="List of user IDs")
    action: str = Field(..., description="Action to perform (enable, disable, delete)")
    
    @validator('action')
    def validate_action(cls, v):
        allowed_actions = ['enable', 'disable', 'delete']
        if v not in allowed_actions:
            raise ValueError(f'Action must be one of: {", ".join(allowed_actions)}')
        return v


class BulkUserActionResponse(BaseModel):
    """Schema for bulk user action response"""
    success: List[str] = Field(..., description="List of user IDs that were successfully processed")
    failed: List[dict] = Field(..., description="List of failed operations with error details")
    total: int = Field(..., description="Total number of operations attempted")