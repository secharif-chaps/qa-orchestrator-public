from typing import Dict, List, Optional, Any
from pydantic import BaseModel, Field
from datetime import datetime

# Base Pydantic models
class SourcedValue(BaseModel):
    value: Any
    source: str

class PendingState(BaseModel):
    pending: bool
    error: Optional[str] = None

class CompanyBase(BaseModel):
    name: str
    website: str

# Company creation and update models
class CompanyCreate(CompanyBase):
    pass

class CompanyUpdate(BaseModel):
    name: Optional[str] = None
    website: Optional[str] = None
    profile: Optional[Dict[str, Any]] = None
    digital: Optional[Dict[str, Any]] = None
    timeline: Optional[Dict[str, Any]] = None
    products: Optional[Dict[str, Any]] = None
    jobs: Optional[Dict[str, Any]] = None
    csr: Optional[Dict[str, Any]] = None
    press: Optional[Dict[str, Any]] = None
    team: Optional[List[Dict[str, Any]]] = None

# N8N related models
class N8nWorkflowRequest(BaseModel):
    company: str
    website: str
    query: str

class N8nWorkflowResponse(BaseModel):
    output: Dict[str, Any]

# Full Company response model
class CompanyResponse(CompanyBase):
    id: int
    profile: Dict[str, Any] = Field(default_factory=dict)
    digital: Dict[str, Any] = Field(default_factory=dict)
    timeline: Dict[str, Any] = Field(default_factory=dict)
    products: Dict[str, Any] = Field(default_factory=dict)
    jobs: Dict[str, Any] = Field(default_factory=dict)
    csr: Dict[str, Any] = Field(default_factory=dict)
    press: Dict[str, Any] = Field(default_factory=dict)
    team: List[Dict[str, Any]] = Field(default_factory=list)
    pending_states: Dict[str, PendingState] = Field(default_factory=dict)
    error: Optional[str] = None
    created_at: datetime
    updated_at: datetime
    
    class Config:
        orm_mode = True 