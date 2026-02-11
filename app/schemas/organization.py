"""
Pydantic schemas for Keycloak Organizations.

Organizations are managed in Keycloak, not in the application database.
These schemas represent organization context extracted from JWT tokens.
"""
from datetime import datetime
from typing import Optional
from pydantic import BaseModel, Field


class OrganizationResponse(BaseModel):
    """
    Organization information from Keycloak.

    Organizations are managed in Keycloak. This represents organization data
    either from JWT token or Keycloak Admin API.
    """
    id: str = Field(..., description="Keycloak organization UUID")
    name: str = Field(..., description="Organization name")
    description: Optional[str] = Field(None, description="Organization description")
    slug: Optional[str] = Field(None, description="Organization URL-friendly slug")
    created_at: Optional[datetime] = Field(None, description="When organization was created")
    updated_at: Optional[datetime] = Field(None, description="When organization was last updated")
    member_count: Optional[int] = Field(None, description="Number of members in organization")

    class Config:
        from_attributes = True


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
    folder_id: Optional[str] = Field(None, description="Folder ID (for companies, the folder containing them)")

    class Config:
        from_attributes = True
