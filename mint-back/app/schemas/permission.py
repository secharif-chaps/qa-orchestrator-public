"""
Schemas for granular permission system
"""

from datetime import datetime
from typing import List, Optional
from pydantic import BaseModel, Field, validator
from enum import Enum


class PermissionType(str, Enum):
    """Standard permission types"""
    # Workspace permissions (workspace-specific only)
    WORKSPACE_READ = "workspace.read"
    WORKSPACE_WRITE = "workspace.write"
    
    # Company permissions (workspace-specific)
    COMPANY_VIEW = "company.view"
    COMPANY_CREATE = "company.create"
    COMPANY_DELETE = "company.delete"
    
    # Admin permissions (global only)
    ADMIN_WORKSPACES = "admin.workspaces"


class UserPermissionBase(BaseModel):
    """Base schema for user permissions"""
    user_id: str = Field(..., description="Keycloak user ID")
    workspace_id: Optional[int] = Field(None, description="Workspace ID (null for global permissions)")
    permission: str = Field(..., description="Permission string")
    granted_by: Optional[str] = Field(None, description="Who granted this permission")


class UserPermissionCreate(UserPermissionBase):
    """Schema for creating user permissions"""
    
    @validator('permission')
    def validate_permission(cls, v):
        """Validate permission format"""
        if not v or not isinstance(v, str):
            raise ValueError('Permission must be a non-empty string')
        
        # Check if it's a known permission type
        valid_permissions = [perm.value for perm in PermissionType]
        if v not in valid_permissions:
            # Allow custom permissions but warn
            pass
            
        return v
    
    @validator('workspace_id')
    def validate_workspace_permission(cls, v, values):
        """Validate workspace_id based on permission type"""
        permission = values.get('permission')
        if permission:
            if permission.startswith('workspace.') or permission.startswith('company.'):
                if v is None:
                    raise ValueError('Workspace permissions require a workspace_id')
            elif permission.startswith('admin.'):
                if v is not None:
                    raise ValueError('Admin permissions must be global (workspace_id should be null)')
        return v


class UserPermissionUpdate(BaseModel):
    """Schema for updating user permissions"""
    granted_by: Optional[str] = Field(None, description="Who granted this permission")


class UserPermissionResponse(UserPermissionBase):
    """Schema for user permission response"""
    id: int = Field(..., description="Permission ID")
    created_at: datetime = Field(..., description="When permission was granted")
    updated_at: Optional[datetime] = Field(None, description="When permission was last updated")
    
    class Config:
        from_attributes = True


class UserPermissionsListResponse(BaseModel):
    """Schema for listing user permissions"""
    user_id: str = Field(..., description="User ID")
    workspace_permissions: List[UserPermissionResponse] = Field(..., description="Workspace-specific permissions")
    global_permissions: List[UserPermissionResponse] = Field(..., description="Global permissions")
    total_permissions: int = Field(..., description="Total number of permissions")


class PermissionGrantRequest(BaseModel):
    """Schema for granting permissions to users"""
    user_id: str = Field(..., description="User to grant permission to")
    permissions: List[str] = Field(..., min_items=1, description="List of permissions to grant")
    workspace_id: Optional[int] = Field(None, description="Workspace ID for workspace permissions")
    
    @validator('permissions')
    def validate_permissions_list(cls, v):
        """Validate list of permissions"""
        if not v:
            raise ValueError('At least one permission must be specified')
        
        # Remove duplicates while preserving order
        seen = set()
        unique_permissions = []
        for perm in v:
            if perm not in seen:
                seen.add(perm)
                unique_permissions.append(perm)
        
        return unique_permissions


class PermissionRevokeRequest(BaseModel):
    """Schema for revoking permissions from users"""
    user_id: str = Field(..., description="User to revoke permission from")
    permissions: List[str] = Field(..., min_items=1, description="List of permissions to revoke")
    workspace_id: Optional[int] = Field(None, description="Workspace ID for workspace permissions")


class WorkspacePermissionsSummary(BaseModel):
    """Summary of permissions for a workspace"""
    workspace_id: int = Field(..., description="Workspace ID")
    total_users: int = Field(..., description="Total users with permissions")
    permission_counts: dict = Field(..., description="Count of each permission type")
    recent_changes: List[UserPermissionResponse] = Field(..., description="Recent permission changes")