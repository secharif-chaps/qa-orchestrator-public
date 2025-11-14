"""
Token validation endpoints for organization modules
"""

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser
from app.services.token_manager import TokenManager
from app.core.dependencies import get_token_manager, get_current_user
from app.core.organization import get_user_organization, OrganizationContext
from app.core.keycloak import idp
from app.schemas.module import (
    ModuleTokensResponse,
    WorkspaceModulesResponse,
    WorkspaceModuleResponse,
    ModuleUpdateRequest,
    AddTokensRequest
)
from app.schemas.user import TokenData
from app.models.organization import ModuleName

router = APIRouter(
    prefix="/organizations",
    tags=["modules"]
)


@router.get("/{organization_id}/modules", response_model=WorkspaceModulesResponse)
async def get_organization_modules(
    organization_id: str,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Get all organization module configurations.

    Organization members can view their own organization.
    Users with admin.organizations role can view any organization.
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = current_user.roles and "admin.organizations" in current_user.roles
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization"
        )

    modules = token_manager.get_all_organization_modules(organization_id)
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


@router.get("/{organization_id}/modules/{module}/tokens", response_model=ModuleTokensResponse)
async def get_module_tokens(
    organization_id: str,
    module: ModuleName,
    token_manager: TokenManager = Depends(get_token_manager),
    current_user: TokenData = Depends(get_current_user),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Get current token count for module.

    Organization members can view their own organization modules.
    Users with admin.organizations role can view any organization modules.
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = current_user.roles and "admin.organizations" in current_user.roles
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization"
        )

    module_config = token_manager.get_module_tokens(organization_id, module)
    if not module_config:
        # Create default module if it doesn't exist
        module_config = token_manager.get_or_create_module(organization_id, module)

    return ModuleTokensResponse(
        module=module_config.module_name,
        token_count=module_config.token_count,
        enabled=module_config.enabled
    )


# Admin endpoints for module management


@router.put("/{organization_id}/modules", response_model=WorkspaceModulesResponse)
async def update_organization_modules(
    organization_id: str,
    updates: dict[ModuleName, ModuleUpdateRequest],
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Update module enablement and token counts.

    Requires admin.organizations role for access.
    """
    # Update each module
    for module_name, update_request in updates.items():
        token_manager.update_module_config(
            organization_id=organization_id,
            module_name=module_name,
            enabled=update_request.enabled,
            token_count=update_request.token_count
        )

    # Return updated modules
    modules = token_manager.get_all_organization_modules(organization_id)
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


@router.post("/{organization_id}/modules/{module}/tokens", response_model=ModuleTokensResponse)
async def add_module_tokens(
    organization_id: str,
    module: ModuleName,
    request: AddTokensRequest,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Add tokens to specific module.

    Requires admin.organizations role for access.
    """
    updated_module = token_manager.add_tokens(
        organization_id=organization_id,
        module_name=module,
        tokens=request.tokens
    )

    return ModuleTokensResponse(
        module=updated_module.module_name,
        token_count=updated_module.token_count,
        enabled=updated_module.enabled
    )


@router.put("/{organization_id}/modules/{module}/toggle", response_model=ModuleTokensResponse)
async def toggle_module(
    organization_id: str,
    module: ModuleName,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Enable/disable module.

    Requires admin.organizations role for access.
    """
    current_module = token_manager.get_module_tokens(organization_id, module)
    if not current_module:
        current_module = token_manager.get_or_create_module(organization_id, module)

    updated_module = token_manager.update_module_config(
        organization_id=organization_id,
        module_name=module,
        enabled=not current_module.enabled
    )

    return ModuleTokensResponse(
        module=updated_module.module_name,
        token_count=updated_module.token_count,
        enabled=updated_module.enabled
    )