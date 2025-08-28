from typing import List, Optional, Dict, Any
from datetime import datetime
from pydantic import BaseModel, Field
from uuid import UUID


class FolderBase(BaseModel):
    name: str = Field(..., min_length=1, max_length=255)
    color: Optional[str] = Field(None, max_length=50)
    icon: Optional[str] = Field(None, max_length=50)
    tags: Optional[List[str]] = Field(default_factory=list)


class FolderCreate(FolderBase):
    pass


class FolderUpdate(BaseModel):
    name: Optional[str] = Field(None, min_length=1, max_length=255)
    color: Optional[str] = Field(None, max_length=50)
    icon: Optional[str] = Field(None, max_length=50)
    tags: Optional[List[str]] = None
    is_favorite: Optional[bool] = None


class FolderItemBase(BaseModel):
    item_id: str
    item_type: str = Field(..., pattern="^(company|contact|document)$")  # Validate item type
    position: Optional[int] = None


class FolderItemAdd(FolderItemBase):
    pass


class FolderItemResponse(FolderItemBase):
    id: UUID
    folder_id: UUID
    added_at: datetime
    added_by: UUID

    class Config:
        from_attributes = True


class FolderItemSimple(BaseModel):
    type: str
    name: str
    created_at: Optional[str]
    owner_username: str


class FolderResponse(FolderBase):
    id: UUID
    workspace_id: int
    owner_id: Optional[UUID]  # Now optional since we use owner_username
    owner_username: str  # New required field
    is_favorite: bool
    is_deleted: bool
    created_at: datetime
    updated_at: datetime
    items: Optional[List[FolderItemSimple]] = Field(default_factory=list)  # Add items summary

    class Config:
        from_attributes = True


class FolderItemSummary(BaseModel):
    id: str
    type: str
    position: Optional[int]
    added_at: Optional[str]
    name: str
    created_at: Optional[str]
    owner: str


class FolderWithItemsResponse(BaseModel):
    id: str
    name: str
    color: Optional[str]
    icon: Optional[str]
    tags: List[str]
    is_favorite: bool
    is_deleted: bool
    created_at: Optional[str]
    updated_at: Optional[str]
    owner_username: str  # Changed from owner_id to owner_username
    workspace_id: int
    items: List[FolderItemSummary]