from pydantic import BaseModel, Field


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
    ai_preferences: dict | None = Field(None, description="AI preferences injected by global-service gateway")
