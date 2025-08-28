from sqlalchemy import Column, String, Integer, Boolean, DateTime, ForeignKey, text
from sqlalchemy.dialects.postgresql import UUID, ARRAY
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.database import Base


class Folder(Base):
    __tablename__ = "folders"

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))
    workspace_id = Column(Integer, ForeignKey("workspaces.id", ondelete="CASCADE"), nullable=False)
    owner_id = Column(UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=True)
    owner_username = Column(String, nullable=True)
    name = Column(String, nullable=False)
    color = Column(String, nullable=True)
    icon = Column(String, nullable=True)
    tags = Column(ARRAY(String), server_default=text("'{}'::text[]"), nullable=False)
    is_favorite = Column(Boolean, server_default=text("false"), nullable=False)
    is_deleted = Column(Boolean, server_default=text("false"), nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationships
    workspace = relationship("Workspace", back_populates="folders")
    owner = relationship("User", back_populates="folders")
    items = relationship("FolderItem", back_populates="folder", cascade="all, delete-orphan")


class FolderItem(Base):
    __tablename__ = "folder_items"

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))
    folder_id = Column(UUID(as_uuid=True), ForeignKey("folders.id", ondelete="CASCADE"), nullable=False)
    item_id = Column(String, nullable=False)
    item_type = Column(String, nullable=False)  # 'company', 'contact', etc.
    position = Column(Integer, nullable=True)
    added_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    added_by = Column(UUID(as_uuid=True), ForeignKey("users.id", ondelete="CASCADE"), nullable=True)
    added_by_username = Column(String, nullable=True)

    # Relationships
    folder = relationship("Folder", back_populates="items")