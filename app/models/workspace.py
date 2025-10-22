from sqlalchemy import Column, Integer, String, Text, DateTime, Boolean, ForeignKey, Enum as SQLEnum, UniqueConstraint
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from enum import Enum
from app.database import Base


class WorkspaceMemberStatus(str, Enum):
    ACTIVE = "active"
    REVOKED = "revoked"


class ModuleName(str, Enum):
    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"


class Workspace(Base):
    __tablename__ = "workspaces"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, nullable=False, index=True)
    description = Column(Text, nullable=True)
    slug = Column(String, unique=True, nullable=False, index=True)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    companies = relationship("Company", back_populates="workspace")
    members = relationship("WorkspaceMember", back_populates="workspace")
    user_permissions = relationship("UserWorkspacePermission", back_populates="workspace")
    modules = relationship("WorkspaceModule", back_populates="workspace")
    folders = relationship("Folder", back_populates="workspace")


class WorkspaceMember(Base):
    __tablename__ = "workspace_members"
    
    id = Column(Integer, primary_key=True, index=True)
    workspace_id = Column(Integer, ForeignKey("workspaces.id"), nullable=False)
    user_id = Column(String, nullable=False)  # Keycloak user ID
    username = Column(String, nullable=False, index=True)
    email = Column(String, nullable=False, index=True)
    first_name = Column(String(100), nullable=True)
    last_name = Column(String(100), nullable=True)
    status = Column(SQLEnum(WorkspaceMemberStatus, name='workspacememberstatus', values_callable=lambda x: [e.value for e in x]), default=WorkspaceMemberStatus.ACTIVE)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    workspace = relationship("Workspace", back_populates="members")


class WorkspaceModule(Base):
    __tablename__ = "workspace_modules"
    
    id = Column(Integer, primary_key=True, index=True)
    workspace_id = Column(Integer, ForeignKey("workspaces.id"), nullable=False)
    module_name = Column(SQLEnum(ModuleName, name='modulename', values_callable=lambda x: [e.value for e in x]), nullable=False)
    enabled = Column(Boolean, default=False, nullable=False)
    token_count = Column(Integer, default=0, nullable=False)
    
    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Constraints
    __table_args__ = (
        UniqueConstraint('workspace_id', 'module_name', name='uq_workspace_modules_workspace_module'),
    )
    
    # Relationships
    workspace = relationship("Workspace", back_populates="modules")