from typing import Dict, List, Optional, Any
from pydantic import BaseModel, Field, ConfigDict
from datetime import datetime
from app.models.task import TaskStatus, TaskType

class CompanyBase(BaseModel):
    name: str
    website: str

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

class TaskResponse(BaseModel):
    id: int
    company_id: int
    type: TaskType
    status: TaskStatus
    error: Optional[str] = None
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)

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
    error: Optional[str] = None
    created_at: datetime
    updated_at: datetime
    tasks: List[TaskResponse] = Field(default_factory=list)
    
    model_config = ConfigDict(from_attributes=True) 