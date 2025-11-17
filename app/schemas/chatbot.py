"""
Schemas for global Chaps-e chatbot
"""
from typing import Dict, List, Any, Optional
from pydantic import BaseModel, Field

class ChatMessage(BaseModel):
    """Individual chat message in conversation history"""
    role: str = Field(..., description="Role of the message sender (user or assistant)")
    content: str = Field(..., description="Content of the message")

class GlobalChatRequest(BaseModel):
    """Request for global Chaps-e chat with multiple context types"""
    message: str = Field(..., min_length=1, max_length=2000, description="User's chat message")
    contexts: Optional[Dict[str, Dict[str, Any]]] = Field(
        default=None,
        description="Context data keyed by type (company, folder, organization)"
    )
    chat_history: List[ChatMessage] = Field(
        default_factory=list,
        description="Previous chat messages for conversation continuity"
    )
    language: Optional[str] = Field(
        default="fr",
        description="User's preferred language (fr or en)"
    )

    class Config:
        json_schema_extra = {
            "example": {
                "message": "Quelle est la stratégie digitale de cette entreprise ?",
                "contexts": {
                    "company": {
                        "id": 123,
                        "name": "Example Company"
                    },
                    "organization": {
                        "id": "uuid-string",
                        "name": "My Organization"
                    }
                },
                "chat_history": [
                    {
                        "role": "user",
                        "content": "Bonjour"
                    },
                    {
                        "role": "assistant",
                        "content": "Bonjour ! Comment puis-je vous aider ?"
                    }
                ],
                "language": "fr"
            }
        }

class ChatResponse(BaseModel):
    """Response from chat endpoint"""
    response: str = Field(..., description="AI response message")
    status: str = Field(default="success", description="Response status (success or error)")
    action: Optional[str] = Field(default=None, description="Action to perform (Phase 3)")
    action_params: Optional[Dict[str, Any]] = Field(default=None, description="Parameters for action (Phase 3)")
    quick_actions: Optional[List[Dict[str, Any]]] = Field(default=None, description="Quick action buttons (Phase 3)")

    class Config:
        json_schema_extra = {
            "example": {
                "response": "Cette entreprise a une forte présence digitale avec...",
                "status": "success"
            }
        }
