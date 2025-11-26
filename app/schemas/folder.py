from typing import List, Optional
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
    owner: Optional[str]

    class Config:
        from_attributes = True


class FolderItemSimple(BaseModel):
    id: str
    type: str
    position: Optional[int]
    added_at: Optional[str]
    name: str
    website: Optional[str]
    created_at: Optional[str]
    owner: str


class FolderResponse(FolderBase):
    id: UUID
    organization_id: str
    owner: str
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
    website: Optional[str]
    created_at: Optional[str]
    owner: str
    is_deleted: bool = False


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
    owner: str
    organization_id: str
    items: List[FolderItemSummary]