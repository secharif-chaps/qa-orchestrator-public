from pydantic import BaseModel, Field
from typing import List
from datetime import datetime
from app.models.workspace import ModuleName


class WorkspaceModuleResponse(BaseModel):
    name: ModuleName
    enabled: bool
    token_count: int = Field(ge=0, description="Number of tokens available for this module")
    created_at: datetime
    updated_at: datetime | None = None

    class Config:
        from_attributes = True


class WorkspaceModulesResponse(BaseModel):
    modules: List[WorkspaceModuleResponse]


class ModuleUpdateRequest(BaseModel):
    enabled: bool | None = None
    token_count: int | None = Field(None, ge=0)


class ModuleTokensResponse(BaseModel):
    module: ModuleName
    token_count: int = Field(ge=0)
    enabled: bool


class AddTokensRequest(BaseModel):
    tokens: int = Field(gt=0, description="Number of tokens to add")


class TokenError(BaseModel):
    error: str = "insufficient_tokens"
    message: str
    current_tokens: int
    required_tokens: int
    module: ModuleName