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

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "name": "Acme Corp",
                    "description": "Competitive intelligence team",
                    "slug": "acme-corp",
                    "created_at": "2025-01-10T09:00:00Z",
                    "updated_at": "2025-03-15T14:20:00Z",
                    "member_count": 12,
                },
            ],
        },
    )


class OrganizationUserItem(BaseModel):
    """User item in organization member list."""

    id: str | None = None
    username: str | None = None
    email: str | None = None
    firstName: str | None = None
    lastName: str | None = None
    enabled: bool = True
    emailVerified: bool = False
    createdTimestamp: int | None = None
    permission_tier: str | None = None

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "550e8400-e29b-41d4-a716-446655440000",
                    "username": "jean.dupont",
                    "email": "jean.dupont@example.com",
                    "firstName": "Jean",
                    "lastName": "Dupont",
                    "enabled": True,
                    "emailVerified": True,
                    "createdTimestamp": 1704880000000,
                    "permission_tier": "writer",
                },
            ],
        },
    )


class OrganizationUserDetailResponse(BaseModel):
    """Detailed user response for organization admin endpoints."""

    id: str | None = None
    username: str | None = None
    email: str | None = None
    firstName: str | None = None
    lastName: str | None = None
    enabled: bool = True
    emailVerified: bool = False
    createdTimestamp: int | None = None

    model_config = ConfigDict(from_attributes=True)


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
    id: str = Field(..., description="ID of the item (company or folder)")
    type: str = Field(..., description="Activity type: 'company' or 'folder'")
    name: str = Field(..., description="Name of the created item")
    owner: str = Field(..., description="Username of the creator")
    created_at: datetime = Field(..., description="When the item was created")
    folder_id: str | None = Field(None, description="Folder ID (for companies, the folder containing them)")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": "d4e5f6a7-b8c9-0123-def0-456789abcdef",
                    "type": "company",
                    "name": "Acme Corp",
                    "owner": "jean.dupont",
                    "created_at": "2025-03-15T10:30:00Z",
                    "folder_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
                },
            ],
        },
    )
