"""
Admin endpoints requiring admin role
"""

from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.services.company import CompanyService
from app.services.token_manager import TokenManager
from app.services.workflow_config import WorkflowConfigService, WorkflowConfigResponse, WorkflowConfigUpdate
from app.core.dependencies import get_company_service, get_current_user, get_token_manager
from app.core.security import verify_admin_access, verify_workspace_admin_access, verify_workflow_admin_access
from app.schemas.company import CompanyResponse
from app.schemas.user import TokenData
from app.schemas.module import (
    WorkspaceModulesResponse, WorkspaceModuleResponse, ModuleUpdateRequest, 
    AddTokensRequest, ModuleTokensResponse
)
from app.models.workspace import ModuleName
from app.database import get_db

router = APIRouter(
    prefix="/admin",
    tags=["admin"]
)


@router.get("/companies", response_model=List[CompanyResponse])
async def get_all_companies_admin(
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get all companies (admin only)"""
    verify_admin_access(current_user)
    return service.get_all_companies()  # No username filter for admin


@router.get("/companies/{company_id}", response_model=CompanyResponse)
async def get_company_admin(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get any company by ID (admin only)"""
    verify_admin_access(current_user)
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return company


@router.delete("/companies/{company_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_company_admin(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Delete any company (admin only)"""
    verify_admin_access(current_user)
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True}


@router.get("/users/{username}/companies", response_model=List[CompanyResponse])
async def get_user_companies_admin(
    username: str,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get companies for a specific user (admin only)"""
    verify_admin_access(current_user)
    return service.get_all_companies(username=username)


# Module Management Endpoints

@router.get("/workspaces/{workspace_id}/modules", response_model=WorkspaceModulesResponse)
async def get_workspace_modules_admin(
    workspace_id: int,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user)
):
    """Get workspace module configurations (admin only)"""
    verify_workspace_admin_access(current_user)
    
    if not token_manager.validate_workspace_access(workspace_id):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workspace {workspace_id} not found"
        )
    
    modules = token_manager.get_all_workspace_modules(workspace_id)
    module_responses = [
        WorkspaceModuleResponse(
            name=module.module_name,
            enabled=module.enabled,
            token_count=module.token_count,
            created_at=module.created_at,
            updated_at=module.updated_at
        )
        for module in modules
    ]
    
    return WorkspaceModulesResponse(modules=module_responses)


@router.put("/workspaces/{workspace_id}/modules", response_model=WorkspaceModulesResponse)
async def update_workspace_modules_admin(
    workspace_id: int,
    updates: dict[ModuleName, ModuleUpdateRequest],
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user)
):
    """Update module enablement and token counts (admin only)"""
    verify_workspace_admin_access(current_user)
    
    if not token_manager.validate_workspace_access(workspace_id):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workspace {workspace_id} not found"
        )
    
    # Update each module
    for module_name, update_request in updates.items():
        token_manager.update_module_config(
            workspace_id=workspace_id,
            module_name=module_name,
            enabled=update_request.enabled,
            token_count=update_request.token_count
        )
    
    # Return updated modules
    modules = token_manager.get_all_workspace_modules(workspace_id)
    module_responses = [
        WorkspaceModuleResponse(
            name=module.module_name,
            enabled=module.enabled,
            token_count=module.token_count,
            created_at=module.created_at,
            updated_at=module.updated_at
        )
        for module in modules
    ]
    
    return WorkspaceModulesResponse(modules=module_responses)


@router.post("/workspaces/{workspace_id}/modules/{module}/tokens", response_model=ModuleTokensResponse)
async def add_module_tokens_admin(
    workspace_id: int,
    module: ModuleName,
    request: AddTokensRequest,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user)
):
    """Add tokens to specific module (admin only)"""
    verify_workspace_admin_access(current_user)
    
    if not token_manager.validate_workspace_access(workspace_id):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workspace {workspace_id} not found"
        )
    
    updated_module = token_manager.add_tokens(
        workspace_id=workspace_id,
        module_name=module,
        tokens=request.tokens
    )
    
    return ModuleTokensResponse(
        module=updated_module.module_name,
        token_count=updated_module.token_count,
        enabled=updated_module.enabled
    )


@router.put("/workspaces/{workspace_id}/modules/{module}/toggle", response_model=ModuleTokensResponse)
async def toggle_module_admin(
    workspace_id: int,
    module: ModuleName,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user)
):
    """Enable/disable module (admin only)"""
    verify_workspace_admin_access(current_user)
    
    if not token_manager.validate_workspace_access(workspace_id):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workspace {workspace_id} not found"
        )
    
    current_module = token_manager.get_module_tokens(workspace_id, module)
    if not current_module:
        current_module = token_manager.get_or_create_module(workspace_id, module)
    
    updated_module = token_manager.update_module_config(
        workspace_id=workspace_id,
        module_name=module,
        enabled=not current_module.enabled
    )
    
    return ModuleTokensResponse(
        module=updated_module.module_name,
        token_count=updated_module.token_count,
        enabled=updated_module.enabled
    )


# Workflow Configuration Endpoints

@router.get("/workflows", response_model=List[WorkflowConfigResponse])
async def get_all_workflow_configs(
    db: Session = Depends(get_db),
    current_user: TokenData = Depends(get_current_user)
):
    """Get all workflow configurations with obfuscated API keys (admin only)"""
    verify_workflow_admin_access(current_user)
    
    service = WorkflowConfigService(db)
    return service.get_all_configs()


@router.put("/workflows/{task_type}", response_model=WorkflowConfigResponse)
async def update_workflow_config(
    task_type: str,
    update_data: WorkflowConfigUpdate,
    db: Session = Depends(get_db),
    current_user: TokenData = Depends(get_current_user)
):
    """Update workflow configuration (admin only)"""
    verify_workflow_admin_access(current_user)
    
    service = WorkflowConfigService(db)
    updated_config = service.update_config(task_type, update_data)
    
    if not updated_config:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workflow configuration for task type '{task_type}' not found"
        )
    
    # Return response with obfuscated API key
    return WorkflowConfigResponse(
        task_type=updated_config.task_type,
        title=updated_config.title,
        workflow_id=updated_config.workflow_id,
        api_key_obfuscated=service.obfuscate_api_key(updated_config.api_key),
        has_api_key=bool(updated_config.api_key)
    )