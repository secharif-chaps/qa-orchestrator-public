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
    Organization context from JWT token.

    Organizations are managed in Keycloak. This represents the organization
    information extracted from the user's JWT token.
    """
    id: str = Field(..., description="Keycloak organization UUID")
    name: str = Field(..., description="Organization name")
    user_id: str = Field(..., description="Current user's Keycloak UUID")
    username: str = Field(..., description="Current user's username")

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

    class Config:
        from_attributes = True
