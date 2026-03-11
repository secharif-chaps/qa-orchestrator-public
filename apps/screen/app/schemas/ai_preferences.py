from typing import Optional

from pydantic import BaseModel, ConfigDict, Field


class AiPreferencesCreate(BaseModel):
    """Schema for creating/updating AI preferences"""
    role: str = Field(..., min_length=1, max_length=255)
    goals_text: str = Field(..., min_length=1, max_length=2000)
    desired_output_text: str = Field(..., min_length=1, max_length=2000)
    documentation_text: Optional[str] = Field(None, max_length=5000)


class AiPreferencesResponse(BaseModel):
    """Schema for AI preferences response (extracted from JSONB)"""
    role: str
    goals_text: str
    desired_output_text: str
    documentation_text: Optional[str] = None

    model_config = ConfigDict(from_attributes=True)


class QuickActionResponse(BaseModel):
    """Schema for a single quick action"""
    id: str = Field(..., description="Unique action identifier")
    label: str = Field(..., description="Display label for the action")
    description: str = Field(..., description="Brief description of what the action does")
    icon: str = Field(..., description="Font Awesome icon class")


class QuickActionsResponse(BaseModel):
    """Schema for quick actions API response"""
    actions: list[QuickActionResponse] = Field(..., max_length=3, description="List of 3 quick actions")


class QuickActionsRequest(BaseModel):
    """Schema for quick actions generation request"""
    company_id: int = Field(..., gt=0, description="ID of the company to generate actions for")
