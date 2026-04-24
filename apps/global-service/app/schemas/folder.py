"""Pydantic schemas for Folder and FolderShare operations.

This module defines:
- Folder CRUD schemas (create, update, response)
- FolderItem schemas for junction table operations
- FolderShare schemas for user-level sharing operations
- ShareRole enum for share permission levels
"""

from datetime import datetime
from enum import StrEnum
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field

from app.models.folder import ItemType

# ==============================================================================
# Share Role Enum
# ==============================================================================


class FolderShareRole(StrEnum):
    """Share role enum for folder sharing permissions.

    Attributes:
        reader: Can view folder and its contents, but cannot modify
        writer: Can view folder and add items (if has module permission),
                but cannot edit/delete folder or manage sharing
    """

    reader = "reader"
    writer = "writer"


# ==============================================================================
# Folder Schemas
# ==============================================================================


class FolderBase(BaseModel):
    name: str = Field(..., min_length=1, max_length=255)
    color: str | None = Field(None, max_length=50)
    icon: str | None = Field(None, max_length=50)
    tags: list[str] | None = Field(default_factory=list)


class FolderCreate(FolderBase):
    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "name": "Competitive Intelligence Q1",
                    "color": "#4A90D9",
                    "icon": "fa-briefcase",
                    "tags": ["finance", "competitors"],
                },
            ],
        },
    )


class FolderUpdate(BaseModel):
    name: str | None = Field(None, min_length=1, max_length=255)
    color: str | None = Field(None, max_length=50)
    icon: str | None = Field(None, max_length=50)
    tags: list[str] | None = None


# ==============================================================================
# Folder Item Schemas
# ==============================================================================


class FolderItemBase(BaseModel):
    item_id: str
    item_type: ItemType
    position: int | None = None


class FolderItemAdd(FolderItemBase):
    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "item_id": "d4e5f6a7-b8c9-0123-def0-456789abcdef",
                    "item_type": "company",
                    "position": 0,
                },
            ],
        },
    )


class FolderItemMove(BaseModel):
    """Schema for moving an item between folders."""

    folder_id: UUID = Field(..., description="Destination folder ID")


class FolderItemResponse(FolderItemBase):
    id: UUID
    folder_id: UUID
    added_at: datetime
    owner: str | None

    model_config = ConfigDict(from_attributes=True)


class FolderItemMoveResponse(BaseModel):
    """Response schema for moving an item to a folder."""

    message: str
    item: FolderItemResponse

    model_config = ConfigDict(from_attributes=True)


class FolderItemSimple(BaseModel):
    id: str
    type: str
    position: int | None
    added_at: str | None
    name: str
    website: str | None
    created_at: str | None
    owner: str


class FolderItemSummary(BaseModel):
    id: str
    type: str
    position: int | None
    added_at: str | None
    name: str
    website: str | None
    created_at: str | None
    owner: str
    is_deleted: bool = False


# ==============================================================================
# Folder Response Schemas
# ==============================================================================


class FolderResponse(FolderBase):
    """Standard folder response with basic information and access control fields.

    Attributes:
        id: Folder UUID
        organization_id: Organization UUID
        owner: Owner username (display name)
        owner_id: Owner Keycloak UUID (for access control checks)
        owner_username: Owner username (for global view display)
        is_owner: True if current user is the folder owner
        share_role: User's share role ('owner', 'writer', 'reader', or None)
        is_favorite: Whether current user has favorited this folder
        is_deleted: Whether folder is soft-deleted
        created_at: Creation timestamp
        updated_at: Last update timestamp
        items: List of items in the folder
    """

    id: UUID
    organization_id: str
    owner: str
    owner_id: str | None = Field(None, description="Owner Keycloak UUID")
    owner_username: str = Field(..., description="Owner username for display in global view")
    is_owner: bool = Field(False, description="True if current user is folder owner")
    share_role: str | None = Field(
        None, description="User's role for this folder: 'owner', 'writer', 'reader', or null"
    )
    is_favorite: bool
    is_deleted: bool
    created_at: datetime
    updated_at: datetime
    items: list[FolderItemSimple] | None = Field(default_factory=list)

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
                    "name": "Competitive Intelligence Q1",
                    "color": "#4A90D9",
                    "icon": "fa-briefcase",
                    "tags": ["finance", "competitors"],
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "owner": "jean.dupont",
                    "owner_id": "550e8400-e29b-41d4-a716-446655440000",
                    "owner_username": "jean.dupont",
                    "is_owner": True,
                    "share_role": "owner",
                    "is_favorite": False,
                    "is_deleted": False,
                    "created_at": "2025-03-10T09:00:00Z",
                    "updated_at": "2025-03-15T14:20:00Z",
                    "items": [],
                },
            ],
        },
    )


class FolderListResponse(BaseModel):
    """Paginated response for folder list."""

    data: list[FolderResponse]
    pagination: dict = Field(..., description="Pagination metadata with total, page, limit, total_pages")


class FolderWithItemsResponse(BaseModel):
    """Folder response with complete item details and access control fields.

    Attributes:
        id: Folder UUID (as string)
        name: Folder name
        color: Optional color
        icon: Optional icon
        tags: List of tags
        owner: Owner username (display name)
        owner_id: Owner Keycloak UUID (for access control checks)
        is_owner: True if current user is the folder owner
        share_role: User's share role ('owner', 'writer', 'reader', or None)
        is_favorite: Whether current user has favorited this folder
        is_deleted: Whether folder is soft-deleted
        created_at: Creation timestamp
        updated_at: Last update timestamp
        organization_id: Organization UUID
        items: List of items with details
    """

    id: str
    name: str
    color: str | None
    icon: str | None
    tags: list[str]
    owner: str
    owner_id: str | None = Field(None, description="Owner Keycloak UUID")
    is_owner: bool = Field(False, description="True if current user is folder owner")
    share_role: str | None = Field(
        None, description="User's role for this folder: 'owner', 'writer', 'reader', or null"
    )
    is_favorite: bool
    is_deleted: bool
    created_at: str | None
    updated_at: str | None
    organization_id: str
    items: list[FolderItemSummary]


# ==============================================================================
# Folder Share Schemas
# ==============================================================================


class FolderShareCreate(BaseModel):
    """Schema for creating a folder share.

    Attributes:
        user_id: Keycloak user UUID to share with
        user_username: Username for display (denormalized)
        role: Share role (reader or writer)
    """

    user_id: str = Field(..., description="Keycloak user UUID to share with")
    user_username: str = Field(..., description="Username for display")
    role: FolderShareRole = Field(
        default=FolderShareRole.reader, description="Share role - reader (view only) or writer (can add items)"
    )

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "user_id": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
                    "user_username": "marie.martin",
                    "role": "reader",
                },
            ],
        },
    )


class FolderShareUpdate(BaseModel):
    """Schema for updating a folder share role.

    Attributes:
        role: New share role (reader or writer)
    """

    role: FolderShareRole = Field(..., description="New share role - reader (view only) or writer (can add items)")


class FolderShareResponse(BaseModel):
    """Schema for folder share response.

    Attributes:
        id: Share record UUID
        folder_id: UUID of the shared folder
        user_id: Keycloak user UUID who has access
        user_username: Username for display
        role: Share role (reader or writer)
        created_at: Timestamp when share was created
        has_write_permission: Whether user has organization.write permission
    """

    id: UUID
    folder_id: UUID
    user_id: str
    user_username: str
    role: FolderShareRole
    created_at: datetime
    has_write_permission: bool = Field(
        default=False, description="Whether user has organization.write permission (can be assigned Writer role)"
    )

    model_config = ConfigDict(from_attributes=True)


# ==============================================================================
# User Search Schemas (for share modal autocomplete)
# ==============================================================================


class CompanyFolderInfoResponse(BaseModel):
    """Response containing folder info for a company (internal API)."""

    folder_id: str = Field(..., description="Folder UUID")
    folder_name: str = Field(..., description="Folder name")
    is_owner: bool = Field(..., description="Whether the requesting user owns the folder")
    share_role: str | None = Field(None, description="Share role if folder is shared with user (reader/writer)")


class UserSearchResult(BaseModel):
    """Schema for user search result in share modal.

    Attributes:
        user_id: Keycloak user UUID
        username: Username for display
        email: User's email address
        has_write_permission: Whether user has organization.write permission
    """

    user_id: str = Field(..., description="Keycloak user UUID")
    username: str = Field(..., description="Username for display")
    email: str | None = Field(None, description="User's email address")
    has_write_permission: bool = Field(
        False, description="Whether user has organization.write permission (can be Writer)"
    )
