"""Chapse conversation context model.

This module defines the table for storing company context linked to chat conversations.
Each row links a conversation ID to the companies the user added as context.
"""

from sqlalchemy import Column, DateTime, Integer, String
from sqlalchemy.dialects.postgresql import ARRAY, UUID
from sqlalchemy.sql import func, text

from app.database import SCREEN_SCHEMA, Base


class ChapseConversationContext(Base):
    """Store company context for Chapse conversations.

    Each row links a conversation to the companies the user added as context.
    Azure OpenAI manages the conversation; we only store which companies
    are linked to each conversation.

    Attributes:
        id: Unique identifier for the context record
        conversation_id: Conversation UUID (unique per record)
        user_id: Keycloak user UUID who owns this conversation
        organization_id: Organization UUID for scoping company lookups
        company_ids: Array of company IDs in context (max 3)
        created_at: Timestamp when the context was created
        updated_at: Timestamp when the context was last updated
    """

    __tablename__ = "chapse_conversation_context"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(UUID(as_uuid=True), primary_key=True, server_default=text("gen_random_uuid()"))

    # Conversation reference (unique - one context per conversation)
    conversation_id = Column(String(255), unique=True, nullable=False, index=True)

    # User reference (Keycloak user ID from JWT sub claim)
    user_id = Column(String, nullable=False, index=True)

    # Organization reference (for scoping company lookups)
    organization_id = Column(String, nullable=False, index=True)

    # Company context (array of company IDs, max 3 enforced at application level)
    company_ids = Column(ARRAY(Integer), nullable=False, server_default="{}")

    # User-facing conversation name (auto-generated or user-set)
    name = Column(String(255), nullable=True)

    # Timestamps
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
