"""Schemas for Chapse chatbot with conversation persistence and company context.

This module defines Pydantic models for the Chapse API that manages
conversations while storing company context locally.
"""

from typing import Literal

from pydantic import BaseModel, Field, field_validator

# =============================================================================
# Company Context Schemas
# =============================================================================


class CompanySummary(BaseModel):
    """Minimal company data for context display."""

    id: int
    name: str


class ContextResponse(BaseModel):
    """Response containing company context for a conversation."""

    company_ids: list[int] = Field(default_factory=list)
    companies: list[CompanySummary] = Field(default_factory=list)


class UpdateContextRequest(BaseModel):
    """Request to update company context for a conversation."""

    company_ids: list[int] = Field(default_factory=list, description="List of company IDs to set as context (max 3)")

    @field_validator("company_ids")
    @classmethod
    def validate_max_companies(cls, v: list[int]) -> list[int]:
        """Ensure maximum 3 companies in context."""
        if len(v) > 3:
            raise ValueError("Maximum 3 companies allowed in context")
        return v


# =============================================================================
# Chat Request/Response Schemas
# =============================================================================


class ChatMessageInput(BaseModel):
    """A single prior chat message sent by the frontend for conversation history."""

    role: Literal["user", "assistant"] = Field(..., description="Message role")
    content: str = Field(..., max_length=8000, description="Message content")


class ChapseChatRequest(BaseModel):
    """Request to send a chat message."""

    query: str = Field(..., min_length=1, max_length=4000, description="User's message")
    conversation_id: str | None = Field(default=None, description="Conversation ID (omit for new conversation)")
    company_ids: list[int] | None = Field(default=None, description="Company IDs to add to context (max 3)")
    messages: list[ChatMessageInput] | None = Field(
        default=None, description="Prior conversation messages for context (max 20, newest kept)"
    )

    @field_validator("company_ids")
    @classmethod
    def validate_max_companies(cls, v: list[int] | None) -> list[int] | None:
        """Ensure maximum 3 companies in context."""
        if v is not None and len(v) > 3:
            raise ValueError("Maximum 3 companies allowed in context")
        return v

    @field_validator("messages")
    @classmethod
    def cap_messages(cls, v: list[ChatMessageInput] | None) -> list[ChatMessageInput] | None:
        """Keep only the last 20 messages if exceeded."""
        if v is not None and len(v) > 20:
            return v[-20:]
        return v


# =============================================================================
# Conversation Schemas
# =============================================================================


class Conversation(BaseModel):
    """Single conversation in list response."""

    id: str = Field(..., description="Conversation ID")
    name: str = Field(..., description="Auto-generated or custom name")
    created_at: int = Field(..., description="Unix timestamp")
    updated_at: int = Field(..., description="Unix timestamp")
    company_ids: list[int] = Field(default_factory=list)
    companies: list[CompanySummary] = Field(default_factory=list)


class ConversationsResponse(BaseModel):
    """Response containing list of conversations."""

    data: list[Conversation]
    has_more: bool
    limit: int


class Message(BaseModel):
    """Single message in conversation history."""

    id: str
    query: str = Field(..., description="User's message")
    answer: str = Field(..., description="AI response")
    created_at: int = Field(..., description="Unix timestamp")
    feedback: dict | None = Field(default=None, description="User feedback")


class ConversationDetailResponse(BaseModel):
    """Detailed conversation response with messages."""

    id: str
    name: str
    created_at: int
    updated_at: int
    company_ids: list[int] = Field(default_factory=list)
    companies: list[CompanySummary] = Field(default_factory=list)
    messages: list[Message] = Field(default_factory=list)
    has_more_messages: bool = False


# =============================================================================
# Rename Schemas
# =============================================================================


class RenameRequest(BaseModel):
    """Request to rename a conversation."""

    name: str | None = Field(default=None, max_length=255, description="Manual name (optional)")
    auto_generate: bool = Field(default=False, description="If true, auto-generate name from content")


class RenameResponse(BaseModel):
    """Response after renaming a conversation."""

    id: str
    name: str


# =============================================================================
# Error Schemas
# =============================================================================


class ChapseErrorResponse(BaseModel):
    """Error response format for Chapse endpoints."""

    detail: str
    code: str | None = None
