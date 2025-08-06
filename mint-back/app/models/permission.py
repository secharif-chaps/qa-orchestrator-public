"""
Permission models for granular permission system
"""

from sqlalchemy import Column, Integer, String, DateTime, ForeignKey, UniqueConstraint, Index
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from enum import Enum
from app.database import Base


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


class UserWorkspacePermission(Base):
    """
    Granular permission system
    - user_id: Keycloak user ID
    - workspace_id: Workspace ID (NULL for global permissions)
    - permission: Specific permission string
    """
    __tablename__ = "user_workspace_permissions"
    
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(String, nullable=False, index=True)  # Keycloak user ID
    workspace_id = Column(Integer, ForeignKey("workspaces.id"), nullable=True, index=True)  # NULL = global
    permission = Column(String, nullable=False, index=True)  # Permission string
    granted_by = Column(String, nullable=True)  # Who granted this permission
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    workspace = relationship("Workspace", back_populates="user_permissions")
    
    # Constraints
    __table_args__ = (
        UniqueConstraint('user_id', 'workspace_id', 'permission', name='unique_user_workspace_permission'),
        Index('idx_user_workspace_permissions_lookup', 'user_id', 'workspace_id', 'permission'),
    )
    
    def __repr__(self):
        workspace_str = f"workspace_{self.workspace_id}" if self.workspace_id else "global"
        return f"<UserPermission(user={self.user_id}, {workspace_str}, {self.permission})>"
    
    @classmethod
    def is_workspace_permission(cls, permission: str) -> bool:
        """Check if a permission is workspace-specific"""
        return permission.startswith("workspace.") or permission.startswith("company.")
    
    @classmethod
    def is_global_permission(cls, permission: str) -> bool:
        """Check if a permission is global"""
        return permission.startswith("admin.")