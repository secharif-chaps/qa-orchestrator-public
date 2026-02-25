from typing import Dict, List, Any
from pydantic import BaseModel, Field

class ChatMessage(BaseModel):
    role: str = Field(..., description="Role of the message sender (user or assistant)")
    content: str = Field(..., description="Content of the message")

class ChatRequest(BaseModel):
    message: str = Field(..., min_length=1, max_length=1000, description="User's chat message")
    company_context: Dict[str, Any] = Field(..., description="Company context data")
    chat_history: List[ChatMessage] = Field(default_factory=list, description="Previous chat messages")

class ChatResponse(BaseModel):
    response: str = Field(..., description="AI response message")
    status: str = Field(default="success", description="Response status")