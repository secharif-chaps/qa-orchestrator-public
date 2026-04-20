"""Pydantic schemas for AI preferences endpoints.

Defines request/response models for the Chapse Assist AI preferences feature.
Field names and types match the monolith schemas for frontend compatibility.
"""


from pydantic import BaseModel, ConfigDict, Field


class AiPreferencesCreate(BaseModel):
    """Schema for creating/updating AI preferences."""

    role: str = Field(..., min_length=1, max_length=255)
    goals_text: str = Field(..., min_length=1, max_length=2000)
    desired_output_text: str = Field(..., min_length=1, max_length=2000)
    documentation_text: str | None = Field(None, max_length=5000)

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "role": "Competitive Intelligence Analyst",
                    "goals_text": "Identify market trends and competitor strategies in the SaaS industry",
                    "desired_output_text": "Concise bullet-point summaries with actionable insights and source links",
                    "documentation_text": "Focus on European B2B SaaS companies with ARR above 10M EUR",
                },
            ],
        },
    )


class AiPreferencesResponse(BaseModel):
    """Schema for AI preferences response.

    Returns the AI preference fields directly (extracted from JSONB).
    """

    role: str
    goals_text: str
    desired_output_text: str
    documentation_text: str | None = None

    model_config = ConfigDict(
        from_attributes=False,
        json_schema_extra={
            "examples": [
                {
                    "role": "Competitive Intelligence Analyst",
                    "goals_text": "Identify market trends and competitor strategies in the SaaS industry",
                    "desired_output_text": "Concise bullet-point summaries with actionable insights and source links",
                    "documentation_text": "Focus on European B2B SaaS companies with ARR above 10M EUR",
                },
            ],
        },
    )
