"""
Token validation endpoints for workspace modules
"""

from fastapi import APIRouter, Depends, HTTPException, status
from app.services.token_manager import TokenManager
from app.core.dependencies import get_token_manager, get_current_user
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.schemas.module import ModuleTokensResponse
from app.schemas.user import TokenData
from app.models.workspace import ModuleName

router = APIRouter(
    prefix="/workspaces",
    tags=["modules"]
)


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