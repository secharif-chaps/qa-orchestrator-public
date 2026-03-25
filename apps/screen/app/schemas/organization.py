"""
Pydantic schemas for Keycloak Organizations.

Organizations are managed in Keycloak, not in the application database.
These schemas represent organization context extracted from JWT tokens.
"""

from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field


class OrganizationResponse(BaseModel):
    """
    Organization information from Keycloak.

    Organizations are managed in Keycloak. This represents organization data
    either from JWT token or Keycloak Admin API.
    """

    id: str = Field(..., description="Keycloak organization UUID")
    name: str = Field(..., description="Organization name")
    description: str | None = Field(None, description="Organization description")
    slug: str | None = Field(None, description="Organization URL-friendly slug")
    created_at: datetime | None = Field(None, description="When organization was created")
    updated_at: datetime | None = Field(None, description="When organization was last updated")
    member_count: int | None = Field(None, description="Number of members in organization")

    model_config = ConfigDict(from_attributes=True)


class OrganizationUserDetailResponse(BaseModel):
    """Response for single organization user detail."""

    id: str | None = None
    username: str | None = None
    email: str | None = None
    firstName: str | None = None
    lastName: str | None = None
    enabled: bool = True
    emailVerified: bool = False
    createdTimestamp: int | None = None


class OrganizationUserItem(OrganizationUserDetailResponse):
    """Single user item in organization user list."""

    permission_tier: str | None = None


class SuccessMessageResponse(BaseModel):
    """Generic success response with message."""

    success: bool
    message: str


class ActivityResponse(BaseModel):
    """
    Activity item for organization activity feed.

    Represents a recent action (company or folder creation) by other users
    in the organization.
    """

    type: str = Field(..., description="Activity type: 'company' or 'folder'")
    name: str = Field(..., description="Name of the created item")
    owner: str = Field(..., description="Username of the creator")
    created_at: datetime = Field(..., description="When the item was created")
    id: str = Field(..., description="ID of the item (company or folder)")
    folder_id: str | None = Field(None, description="Folder ID (for companies, the folder containing them)")

    model_config = ConfigDict(from_attributes=True)
