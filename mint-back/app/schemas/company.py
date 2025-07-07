from typing import Dict, List, Optional, Any
from pydantic import BaseModel, Field, ConfigDict, field_validator
from datetime import datetime
from app.models.task import TaskStatus, TaskType
from app.core.validators import InputValidator

class CompanyBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=100, description="Company name")
    website: str = Field(..., min_length=4, max_length=255, description="Company website URL")
    
    @field_validator('name')
    @classmethod
    def validate_name(cls, v):
        """Validate and sanitize company name"""
        return InputValidator.validate_company_name(v)
    
    @field_validator('website')
    @classmethod
    def validate_website(cls, v):
        """Validate and sanitize website URL"""
        return InputValidator.validate_website_url(v)

class CompanyCreate(CompanyBase):
    pass  # Only inherits name and website from CompanyBase

class CompanyUpdate(BaseModel):
    name: Optional[str] = Field(None, min_length=2, max_length=100, description="Company name")
    website: Optional[str] = Field(None, min_length=4, max_length=255, description="Company website URL")
    profile: Optional[Dict[str, Any]] = Field(None, description="Company profile data")
    digital: Optional[Dict[str, Any]] = Field(None, description="Digital presence data")
    timeline: Optional[Dict[str, Any]] = Field(None, description="Company timeline data")
    products: Optional[Dict[str, Any]] = Field(None, description="Products data")
    jobs: Optional[Dict[str, Any]] = Field(None, description="Jobs data")
    csr: Optional[Dict[str, Any]] = Field(None, description="CSR data")
    press: Optional[Dict[str, Any]] = Field(None, description="Press data")
    team: Optional[List[Dict[str, Any]]] = Field(None, description="Team data")
    
    @field_validator('name')
    @classmethod
    def validate_name(cls, v):
        """Validate and sanitize company name"""
        if v is not None:
            return InputValidator.validate_company_name(v)
        return v
    
    @field_validator('website')
    @classmethod
    def validate_website(cls, v):
        """Validate and sanitize website URL"""
        if v is not None:
            return InputValidator.validate_website_url(v)
        return v
    
    @field_validator('profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press')
    @classmethod
    def validate_json_fields(cls, v, info):
        """Validate JSON field data"""
        if v is not None:
            return InputValidator.validate_json_field(v, info.field_name)
        return v
    
    @field_validator('team')
    @classmethod
    def validate_team(cls, v):
        """Validate team data"""
        if v is not None:
            return InputValidator.validate_json_field(v, 'team')
        return v

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
    owner_username: str
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