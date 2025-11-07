"""Admin endpoints requiring admin role.

This module contains all admin-only endpoints that require specific admin roles.
Uses fastapi-keycloak for automatic role-based access control via dependency injection.
"""

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.core.dependencies import get_company_service, get_token_manager
from app.core.keycloak import idp
from app.database import get_db
from app.models.task import Task, TaskStatus
from app.models.workspace import ModuleName
from app.schemas.company import CompanyResponse
from app.schemas.module import (
    AddTokensRequest,
    ModuleTokensResponse,
    ModuleUpdateRequest,
    WorkspaceModuleResponse,
    WorkspaceModulesResponse,
)
from app.services.company import CompanyService
from app.services.token_manager import TokenManager
from app.services.workflow_config import (
    WorkflowConfigResponse,
    WorkflowConfigService,
    WorkflowConfigUpdate,
)

router = APIRouter(
    prefix="/admin",
    tags=["admin"]
)


@router.get("/companies", response_model=list[CompanyResponse])
async def get_all_companies_admin(
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))
):
    """Get all companies (admin only).

    Requires admin role for access.
    """
    return service.get_all_companies()  # No username filter for admin


@router.get("/companies/{company_id}", response_model=CompanyResponse)
async def get_company_admin(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))
):
    """Get any company by ID (admin only).

    Requires admin role for access.
    """
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))
):
    """Delete any company (admin only).

    Requires admin role for access.
    """
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True}


@router.get("/users/{username}/companies", response_model=list[CompanyResponse])
async def get_user_companies_admin(
    username: str,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin"]))
):
    """Get companies for a specific user (admin only).

    Requires admin role for access.
    """
    return service.get_all_companies(username=username)


# Module Management Endpoints

@router.get("/workspaces/{workspace_id}/modules", response_model=WorkspaceModulesResponse)
async def get_workspace_modules_admin(
    workspace_id: int,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"]))
):
    """Get workspace module configurations (workspace admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"]))
):
    """Update module enablement and token counts (workspace admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"]))
):
    """Add tokens to specific module (workspace admin only).

    Requires admin.workspaces role for access.
    """
    
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
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"]))
):
    """Enable/disable module (workspace admin only).

    Requires admin.workspaces role for access.
    """
    
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

@router.get("/workflows", response_model=list[WorkflowConfigResponse])
async def get_all_workflow_configs(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"]))
):
    """Get all workflow configurations with obfuscated API keys (workflow admin only).

    Requires admin.workflows role for access.
    """
    
    service = WorkflowConfigService(db)
    return service.get_all_configs()


@router.put("/workflows/{task_type}", response_model=WorkflowConfigResponse)
async def update_workflow_config(
    task_type: str,
    update_data: WorkflowConfigUpdate,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"]))
):
    """Update workflow configuration (workflow admin only).

    Requires admin.workflows role for access.
    """
    
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
        has_api_key=bool(updated_config.api_key),
        llm=updated_config.llm
    )


# Task Management Endpoints

@router.post("/tasks/fail-stuck")
async def fail_stuck_tasks(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workspaces"]))
):
    """Fail all pending and running tasks and clear the task queue.

    This is used to unlock the system when tasks get stuck.
    Requires admin.workspaces permission.
    """
    
    # Get all pending and running tasks
    stuck_tasks = db.query(Task).filter(
        Task.status.in_([TaskStatus.PENDING, TaskStatus.RUNNING])
    ).all()
    
    failed_count = 0
    for task in stuck_tasks:
        task.status = TaskStatus.ERROR
        task.error = "Task failed by admin to unlock stuck queue"
        task.updated_at = datetime.utcnow()
        failed_count += 1
    
    # Commit the database changes
    db.commit()
    
    # Clear the Celery queue
    # Purge all messages from the dify_workflows queue
    try:
        # Get the queue with the same parameters as defined in celery_app
        from kombu import Connection, Queue as KombuQueue
        from app.core.celery_app import RABBITMQ_URL
        
        with Connection(RABBITMQ_URL) as conn:
            channel = conn.channel()
            # Declare the queue with the same arguments as in celery_app
            queue = KombuQueue(
                'dify_workflows', 
                channel=channel, 
                durable=True,
                queue_arguments={'x-max-priority': 10}  # Match the celery config
            )
            queue.declare()
            # Purge the queue
            purged_count = queue.purge()
            
        queue_cleared = True
        queue_message = f"Purged {purged_count} messages from queue"
    except Exception as e:
        queue_cleared = False
        queue_message = f"Failed to clear queue: {str(e)}"
    
    return {
        "success": True,
        "tasks_failed": failed_count,
        "queue_cleared": queue_cleared,
        "queue_status": queue_message,
        "message": f"Failed {failed_count} stuck tasks and {'successfully cleared' if queue_cleared else 'attempted to clear'} the queue"
    }