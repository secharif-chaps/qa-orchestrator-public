"""
Token validation endpoints for workspace modules
"""

from fastapi import APIRouter, Depends, HTTPException, status
from app.services.token_manager import TokenManager
from app.core.dependencies import get_token_manager, get_current_user
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.schemas.module import ModuleTokensResponse, WorkspaceModulesResponse, WorkspaceModuleResponse
from app.schemas.user import TokenData
from app.models.workspace import ModuleName

router = APIRouter(
    prefix="/workspaces",
    tags=["modules"]
)


@router.get("/{workspace_id}/modules", response_model=WorkspaceModulesResponse)
async def get_workspace_modules(
    workspace_id: int,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get all workspace module configurations (workspace members can view their own)"""
    # Verify user has access to this workspace
    if workspace_context.workspace.id != workspace_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this workspace"
        )

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


@router.get("/{workspace_id}/modules/{module}/tokens", response_model=ModuleTokensResponse)
async def get_module_tokens(
    workspace_id: int,
    module: ModuleName,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get current token count for module"""
    # Verify user has access to this workspace
    if workspace_context.workspace.id != workspace_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this workspace"
        )

    module_config = token_manager.get_module_tokens(workspace_id, module)
    if not module_config:
        # Create default module if it doesn't exist
        module_config = token_manager.get_or_create_module(workspace_id, module)

    return ModuleTokensResponse(
        module=module_config.module_name,
        token_count=module_config.token_count,
        enabled=module_config.enabled
    )